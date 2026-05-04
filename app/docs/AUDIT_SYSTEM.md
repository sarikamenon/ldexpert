# Audit System

Canonical reference for how model changes are tracked, queried, and extended.

## 1. Mental model

A single append-only table (`audits`) records *what changed*, *when*, *by whom*, *from where*, and *as part of what action*. Each row points to a domain record via a polymorphic FK (`auditable_type` + `auditable_id`).

The `App\Models\Concerns\HasAudits` trait is the opt-in: any model that uses it produces audit rows on `updating` and `deleting`. The `App\Domain\Audit\Services\AuditRecorder` service is for **custom events** (pivot syncs, domain events) that bypass model events.

## 2. What is audited

| Event | Trigger | Captured |
|---|---|---|
| `updated` | Eloquent `updating` hook on an opted-in model | Dirty diff only — fields that actually changed |
| `deleted` | Eloquent `deleting` hook | Full snapshot in `old_values`; `new_values = null` |
| `services_synced` (or any custom name) | Manual call to `AuditRecorder::record()` | Whatever you pass as `oldValues` / `newValues` |

**Not audited** (intentional):

- `created` / `restored` events
- Import staging tables (`SSAImportRow`, `StudentImportRow`, etc.) — transient
- Gateway log tables (`PaymentGatewayLog`, `PaymentGatewayTransaction`) — already log-shaped
- The `audits` table itself

## 3. Currently audited models

| Model | Notes |
|---|---|
| `SchoolContract` | All fillable fields. Service-rate changes recorded as `services_synced` on the parent contract. |
| `SchoolContractService` | Direct edits audit on update/delete. Note: bulk rewrites via `syncServices()` audit on the parent (see §5). |
| `TherapistContract` | Same pattern as `SchoolContract`. |
| `TherapistContractService` | Same pattern as `SchoolContractService`. |
| `ServiceSupportAgreement` | All fillable fields. Pivot service changes recorded as `services_synced` on the parent SSA. |
| `LedgerEntry` | All fields, **including `balance_after`**. Chain recomputes will produce audits — accepted as the cost of complete ledger history. |
| `School` | All fillable fields (contact info, address, status, manager, timezone, etc.). |

## 4. How to opt a model in

One line:

```php
use App\Models\Concerns\HasAudits;

class Invoice extends Model
{
    use HasAudits;
    // ... rest of the model
}
```

That's it. The trait hooks `updating` and `deleting`, captures the dirty diff, and writes one audit row per save. By default it audits every column on the table (minus the global ignore list — see §6).

### Narrowing the audited field set

```php
class Invoice extends Model
{
    use HasAudits;

    /** @var array<int, string> */
    protected array $auditFields = ['status', 'amount', 'due_date'];
}
```

### Adding extra ignores on top of the global list

```php
class Invoice extends Model
{
    use HasAudits;

    /** @var array<int, string> */
    protected array $auditIgnoreFields = ['internal_cache_key'];
}
```

### Stripping or transforming values before they're stored (PII)

```php
class User extends Model
{
    use HasAudits;

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @return array{0: array<string, mixed>|null, 1: array<string, mixed>|null}
     */
    protected function sanitizeAuditValues(?array $oldValues, ?array $newValues): array
    {
        foreach (['ssn', 'date_of_birth'] as $field) {
            if (isset($oldValues[$field])) {
                $oldValues[$field] = '[REDACTED]';
            }
            if (isset($newValues[$field])) {
                $newValues[$field] = '[REDACTED]';
            }
        }

        return [$oldValues, $newValues];
    }
}
```

## 5. Pivot / related-row sync pattern (MANDATORY)

Eloquent `updating` / `deleting` hooks **do not fire** for:

- Pivot syncs: `$model->relation()->sync(...)`, `attach`, `detach`
- Builder mass deletes: `$model->relation()->delete()` (bypasses per-row events)
- Bulk inserts: `createMany(...)`, `::insert(...)`

Without intervention, those writes are silently invisible to the audit log — the parent has no dirty fields and the children's events never fire.

### The fix — applied uniformly across the codebase

1. Add a **`*Snapshot()` method on the parent model** that returns a stable, ordered `array<int, array<string, mixed>>` of the related rows' audit-relevant fields. Order by a stable key (e.g. `service_id`) so the diff is insensitive to row insertion order.
2. In the repository method that performs the sync, capture `$old` snapshot, run the sync, then capture `$new` snapshot.
3. If `$old === $new`, return early — no audit.
4. Otherwise call `AuditRecorder::record()` with a descriptive event name on the **parent** model.

### Reference implementation

**Model** — [`SchoolContract::serviceRatesSnapshot()`](../app/Models/SchoolContract.php):

```php
/**
 * Stable snapshot of contract service rates for audit diffs.
 * Ordered by service_id so the diff is insensitive to insertion order.
 *
 * @return array<int, array<string, mixed>>
 */
public function serviceRatesSnapshot(): array
{
    return $this->services()
        ->orderBy('service_id')
        ->get()
        ->map(static fn (SchoolContractService $row): array => [
            'service_id' => $row->service_id,
            'rate' => $row->rate,
            'rate_type' => $row->rate_type->value,
            'no_show_rate' => $row->no_show_rate,
            'no_show_rate_type' => $row->no_show_rate_type?->value,
        ])
        ->values()
        ->all();
}
```

**Repository** — [`EloquentSchoolContractRepository::syncServices()`](../app/Infrastructure/Repositories/EloquentSchoolContractRepository.php):

```php
public function syncServices(SchoolContract $contract, array $services): void
{
    $oldSnapshot = $contract->serviceRatesSnapshot();

    $contract->services()->delete();
    $contract->services()->createMany(/* ... */);

    $newSnapshot = $contract->refresh()->serviceRatesSnapshot();

    if ($oldSnapshot === $newSnapshot) {
        return;
    }

    $this->auditRecorder->record(
        auditable: $contract,
        event: 'services_synced',
        oldValues: ['services' => $oldSnapshot],
        newValues: ['services' => $newSnapshot],
    );
}
```

### Why audits attach to the parent (not the children)

- **Timeline coherence.** One query — `$contract->audits` — returns the full history.
- **Children's IDs are ephemeral.** `delete()` + `createMany()` rotates child IDs every save. Audits on child IDs would orphan immediately.
- **Business intent vs. storage mechanics.** "User changed service rates" is one business action; the underlying `DELETE 4 + INSERT 4` is implementation detail.

### Other reference cases

- `SSA` services pivot — [`EloquentSSARepository::syncSsaServices()`](../app/Infrastructure/Repositories/EloquentSSARepository.php). Simpler form: only the `service_ids` set matters, no rate snapshot needed.
- `TherapistContract` services — same shape as `SchoolContract`.

## 6. Always-stripped (global) fields

Baked into the trait, applied to every auditable model regardless of overrides:

- `id`, `created_at`, `updated_at`, `deleted_at`
- `password`, `remember_token`, `api_token`, `two_factor_secret`, `two_factor_recovery_codes`

Add per-model ignores via `$auditIgnoreFields`; add per-model transforms via `sanitizeAuditValues()` (§4).

## 7. Custom-event audits via `AuditRecorder`

Use the recorder when you need to audit something that bypasses model events: pivot syncs, domain summaries, batch operations, manual rollups.

```php
use App\Domain\Audit\Services\AuditRecorder;

final class CloseFiscalYearService
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function close(School $school, int $year): void
    {
        // ... do the work ...

        $this->auditRecorder->record(
            auditable: $school,
            event: 'fiscal_year_closed',
            oldValues: ['year' => $year, 'status' => 'open'],
            newValues: ['year' => $year, 'status' => 'closed'],
        );
    }
}
```

`record()` resolves `created_by`, `source`, `url`, `ip_address`, `user_agent`, and `batch_uuid` automatically using the same logic as the trait.

## 8. Querying audits

Audit relation is provided by the trait — every auditable model has it for free:

```php
// All audits for one contract, newest first
$contract->audits()->latest()->get();

// Just the events that changed services
$contract->audits()->where('event', 'services_synced')->get();

// Who edited this SSA today?
$ssa->audits()
    ->whereDate('created_at', today())
    ->with('user')
    ->get()
    ->pluck('user.name')
    ->unique();

// Cross-model: every audit by one user this week
\App\Models\Audit::query()
    ->where('created_by', $userId)
    ->where('created_at', '>=', now()->subWeek())
    ->latest()
    ->get();
```

The `Audit` model exposes:

- `auditable()` — `MorphTo` to whichever model produced the row
- `user()` — `BelongsTo<User>` on `created_by` (nullable: console writes have no user)

## 9. Schema

```sql
CREATE TABLE audits (
    id              BIGINT UNSIGNED PK,
    created_by      BIGINT UNSIGNED NULL FK -> users.id (nullOnDelete),
    auditable_type  VARCHAR,
    auditable_id    BIGINT UNSIGNED,
    event           VARCHAR(32),                  -- updated | deleted | services_synced | etc.
    old_values      JSON NULL,
    new_values      JSON NULL,
    batch_uuid      UUID NULL,                    -- groups N rows from one user action
    source          VARCHAR(32) NULL,             -- web | console | job | import
    url             VARCHAR(2048) NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      TEXT NULL,
    created_at      TIMESTAMP                     -- no updated_at; append-only
);
```

Indexes:

- `(auditable_type, auditable_id, created_at)` — primary lookup pattern
- `(created_by, created_at)` — "what did user X do?"
- `batch_uuid` — group N audits from one action
- `(event, created_at)` — filter by event type

## 10. Operational notes

### `batch_uuid` is currently always `NULL`

`AuditBatchContext` exists and is a registered singleton, but middleware/job hooks that auto-populate it are deferred to Phase 2. Until then, the only audits with `batch_uuid` set are ones written inside an explicit `AuditBatchContext::start()` block.

When you do need grouping right now (e.g. a one-off backfill command):

```php
use App\Domain\Audit\Services\AuditBatchContext;

$ctx = app(AuditBatchContext::class);
$ctx->start(source: 'import');
try {
    foreach ($rows as $row) {
        $ssa->update([...]);  // every audit row in here shares the same batch_uuid
    }
} finally {
    $ctx->clear();
}
```

### `source` resolution

Without an explicit override:

- `console` if `app()->runningInConsole()` and not unit tests
- `web` otherwise (HTTP request, queued job in sync driver, test, etc.)

Override per-batch via `AuditBatchContext::start(source: 'import')`.

### Seeders, factories

Seed and factory data should NOT pollute the audit log. Wrap bulk seeding in `Model::withoutEvents()`:

```php
SchoolContract::withoutEvents(function () {
    SchoolContract::factory()->count(50)->create();
});
```

### Backfills / data migrations that re-write audited rows

Same as seeders — wrap the migration in `Model::withoutEvents()` if the rewrite is mechanical and not a real business action. Otherwise, write the audits intentionally via `AuditRecorder` with a descriptive event name (e.g. `'backfilled_recorded_at'`).

## 11. Files

| Path | Purpose |
|---|---|
| [`app/Models/Concerns/HasAudits.php`](../app/Models/Concerns/HasAudits.php) | Opt-in trait |
| [`app/Models/Audit.php`](../app/Models/Audit.php) | The audit model |
| [`app/Domain/Audit/Services/AuditRecorder.php`](../app/Domain/Audit/Services/AuditRecorder.php) | Manual audit writer |
| [`app/Domain/Audit/Services/AuditBatchContext.php`](../app/Domain/Audit/Services/AuditBatchContext.php) | Batch UUID + source override |
| [`database/migrations/2026_05_04_115018_create_audits_table.php`](../database/migrations/2026_05_04_115018_create_audits_table.php) | Schema |
| [`tests/Feature/Audit/`](../tests/Feature/Audit/) | Trait + recorder + pivot-sync tests |

## 12. Decisions (locked)

- **Custom code, no package.** Owen-it / Spatie patterns followed but no external dep.
- **Single polymorphic table.** Adding a model = adding the trait. No per-model audit tables.
- **`updating` and `deleting` only.** No `created`, no `restored`.
- **`balance_after` IS audited on `LedgerEntry`.** Recompute side-effect accepted.
- **Pivot / related-row writes audit on the parent**, never on the child rows (see §5).
