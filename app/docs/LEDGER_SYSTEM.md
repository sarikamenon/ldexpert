# Ledger System

Canonical reference for how `ledger_entries` is read, written, and maintained.

## 1. Mental model

The ledger is a single append-mostly table (`ledger_entries`) that records every event that changes a counterparty's balance. Two kinds of accounts share the table via `ledgerable_type` + `ledgerable_id`:

- **AR (accounts receivable)**: `ledgerable_type = App\Models\School`. Positive balance = the school owes us money.
- **AP (accounts payable)**: `ledgerable_type = App\Models\User` (therapist). Positive balance = we owe the therapist.

Each row stores `amount` (always positive) and `transaction_type` (an enum). The sign of its effect on the running balance is derived from `TransactionType::balanceDelta()`, never hardcoded.

## 2. Sign convention (single source of truth)

`App\Enums\TransactionType::balanceDelta()` returns +1 or -1 per case:

| transaction_type | delta | Direction |
|---|---|---|
| `invoice_generated` | +1 | School owes more |
| `bill_generated` | +1 | We owe therapist more |
| `payment_received` | -1 | School owes less |
| `payment_made` | -1 | We owe therapist less |
| `credit_note` | -1 | Forgive what counterparty owes |
| `refund` | +1 | Cash sent back / reverses prior credit position |
| `expense` | -1 | Money out (matches `ExpenseDemoSeeder` semantics) |

`LedgerEntry::signedAmount()` returns `amount * balanceDelta()`. Use it everywhere that needs a signed contribution to the balance.

## 3. The `balance_after` invariant

For every account, sorted by `(recorded_at, id)`:

```
balance_after = SUM(signedAmount of all earlier non-soft-deleted rows for the same account)
```

The invariant holds **per row**, not just at the tail: every row's stored `balance_after` must equal the running signed-amount total up to and including itself. `php artisan ledger:verify` checks this row-by-row.

## 4. `recorded_at` vs `created_at`

Two timestamps with very different meanings:

- **`recorded_at`** — when the underlying transaction *occurred*. User-controlled date (the form field on credit notes/refunds; backfilled from source-document dates for invoice/bill/payment/expense rows). May be backdated. Adjustments combine the user-picked **date** with the **current time-of-day** at submit so rows sort deterministically by insertion moment within their date and so the new row interleaves naturally with same-day invoices/payments. See **Backdating caveats** below for the implication this has on intra-day ordering of backdated rows.
- **`created_at`** — when the row was inserted into the database. DB-controlled. Never user-controlled.

**Naming gotcha**: `recorded_by_id` (the user who clicked Save) is unrelated to `recorded_at` (when the underlying transaction happened). Read these as two independent fields.

Chain ordering and balance computations always use `(recorded_at, id)`. `id` resolves intra-day ties deterministically.

## 5. Write rule

**All writes to `ledger_entries` MUST go through `App\Domain\Finance\Services\LedgerService`.** Direct `LedgerEntry::create(...)` calls outside the service are forbidden in production code (seeders excepted).

`LedgerService::createEntry(...)` is the single insertion path. It:

1. Locks the latest row of the account (`SELECT ... FOR UPDATE`).
2. If the new `recorded_at` is strictly greater than every existing row, inserts at the end with `balance_after = previousBalance + signed`. (Forward-dated insert — fast path.)
3. Otherwise (backdated): inserts with a placeholder `balance_after = 0`, then calls `recomputeChainFrom(recorded_at)` to rewrite the chain.

The four credit-note / refund methods (`createCreditNoteForSchool`, etc.) and the two source-document writers (`createInvoiceGeneratedEntry`, `createBillGeneratedEntry`) all funnel through `createEntry`. Payment writers (`InvoicePaymentService`, `TherapistBillPaymentService`) inject `LedgerService` and call `createEntry` directly.

## 6. The recompute walker

`LedgerChainService::recomputeChain(string $ledgerableType, int $ledgerableId): void`

Called from inside the calling service's `DB::transaction` (createEntry, editAdjustment, deleteAdjustment all open one):

1. `SELECT … FROM ledger_entries WHERE ledgerable_type = ? AND ledgerable_id = ? ORDER BY recorded_at, id FOR UPDATE`. Locks the entire chain (typically 50–350 rows for an active account).
2. Walk from row 1, accumulating `running += row.signedAmount()`.
3. For **every** row whose stored `balance_after` differs from `running` by ≥ 0.005 (half a cent — the storage precision is `decimal(*,2)`), write the corrected value. Float equality is **not** used; rounding noise within ½ cent is treated as no drift.

The walker is fully self-healing: it rewrites any row in the chain that drifts, regardless of position.

`LedgerService::recomputeChainFrom($type, $id, $from = null)` is kept as a backwards-compatible alias that opens its own transaction and delegates to `recomputeChain`. The `$from` cursor is **ignored** — historical drift older than `$from` was never load-bearing and would have been silently skipped before. New code should use `LedgerChainService::recomputeChain` directly inside its own transaction.

Soft-deleted rows are excluded by Eloquent's `SoftDeletes` scope — they don't contribute and don't get rewritten.

The walker fires on:
- Backdated `createEntry` calls.
- `editAdjustment` (recomputes the entire chain after save).
- `deleteAdjustment` (recomputes after soft-delete).
- `InvoicePaymentService::deletePayment` and `TherapistBillPaymentService::deletePayment` (cascade soft-delete + recompute).

### Backdating caveats

Two non-obvious consequences of how `recorded_at` is stamped:

1. **Backdated rows take *today's* time-of-day.** `LedgerService::resolveDateOnlyRecordedAt` overlays `Carbon::now()` onto the user-picked date. A row backdated to *2026-03-01* recorded today at 14:37 will sort *after* a real same-day historical row recorded at 09:00 on 2026-03-01. This is intentional — it preserves natural insertion order for new same-day rows — but means the chain order is "submit moment within date", not "real-world moment within date". For a strict moment-of-truth view, sort by `(recorded_at, created_at)` instead of `(recorded_at, id)` in your query, or extend the form to accept time too.
2. **Editing twice in the same day moves the row's time-of-day.** Each edit re-stamps the time portion from `now()`, so a row edited at 09:00 then again at 14:00 will end up at 14:00 even if its date didn't change. Cosmetic — never affects balances — but slightly surprising in audit logs.

### Caller responsibility: never query the table raw

`SoftDeletes` lives on the Eloquent model. `DB::table('ledger_entries')->…` and `LedgerEntry::withTrashed()->…` both bypass the global scope and **will return tombstoned rows**. Any aggregator (stats, reports, exports) that uses raw queries here will silently double-count deleted entries. The audit-trail value of soft-deletes is preserved by intent: when in doubt, query through the Eloquent model.

## 7. Edit / delete matrix

Only `credit_note` and `refund` rows are mutable from the ledger UI. All other types must be edited via their source-document page.

| transaction_type | Edit on ledger UI | Source-document page |
|---|---|---|
| `invoice_generated` | ❌ | Invoices |
| `bill_generated` | ❌ | Therapist Bills |
| `payment_received` | ❌ | Invoices → Payments |
| `payment_made` | ❌ | Therapist Bills → Payments |
| `expense` | ❌ | Expenses |
| `credit_note` | ✅ | — |
| `refund` | ✅ | — |

The guard has three layers, in order:
1. **`role:admin` middleware** on the route group — non-admins never reach the controller.
2. **`LedgerEntryPolicy::update` / `delete`** invoked via `$this->authorize(...)` in `LedgerAdjustmentController` — returns **403** on a non-credit-note/refund row before the action runs.
3. **`LedgerService::editAdjustment` / `deleteAdjustment`** — throws `InvalidArgumentException` if a service caller bypasses the controller. Defence in depth.

## 8. Soft-delete semantics

`LedgerEntry` uses `Illuminate\Database\Eloquent\SoftDeletes`. Soft-deleted rows are conceptually erased: they don't contribute to `signedAmount` sums, the recompute walker skips them, and the verify command ignores them. The audit trail (who/when, the original row) is preserved.

`InvoicePaymentService::deletePayment` and `TherapistBillPaymentService::deletePayment` switched from hard-delete to soft-delete + recompute in the credit-note / refund work. Pre-fix, deleting a payment hard-deleted its ledger row and left stale `balance_after` values on every later row.

## 9. Concurrency

`SELECT ... FOR UPDATE` over the whole chain serializes writes per account. Different accounts don't contend. Read paths use the default `REPEATABLE READ` snapshot — they never see a half-rewritten chain. Every transaction touches exactly one account, so deadlocks are not a concern.

## 10. The verify command

```
php artisan ledger:verify [--account-type=...] [--account-id=...] [--fix]
```

For each account: walk the chain in `(recorded_at, id)` order and compare each row's stored `balance_after` against the running signed-amount total. Reports the **first** drifted row per account. Without `--fix`, exits non-zero. With `--fix`, calls `recomputeChainFrom` for each drifted account, which heals every drifted row in the chain in one pass.

The per-row check matters because end-of-chain comparison alone can mask drift: an offset on intermediate rows that nets out by the last row would falsely pass.

Run on demand to audit. Recommended to schedule nightly as a safety net against future writers that bypass `LedgerService`.

## 11. Worked examples

### Backdated credit note

Before:

```
2026-04-25 invoice_generated  $500   bal +500
2026-04-29 refund              $50   bal +550
```

Admin records a credit note dated 2026-04-27 for $100. `createEntry` detects backdating (new key < latest key), inserts the row with placeholder balance, and runs the walker:

```
2026-04-25 invoice_generated  $500   bal +500
2026-04-27 credit_note        $100   bal +400  ← walked
2026-04-29 refund              $50   bal +450  ← walked
```

### Payment delete cascade

Before:

```
2026-04-25 invoice_generated  $500   bal +500
2026-04-26 payment_received   $100   bal +400
2026-04-29 refund              $50   bal +450
```

Admin deletes the payment. `InvoicePaymentService::deletePayment` soft-deletes the allocation and the ledger entry, then runs the walker from `recorded_at = 2026-04-26`:

```
2026-04-25 invoice_generated  $500   bal +500
[2026-04-26 payment_received  $100   soft-deleted, ignored]
2026-04-29 refund              $50   bal +550  ← walked
```

## 12. Migration / large-table notes

The `recorded_at` column was introduced in three steps so production rollouts don't down-time:

1. `2026_04_29_100000_add_recorded_at_to_ledger_entries_table` — add the column **nullable** plus the `(ledgerable_type, ledgerable_id, recorded_at)` index.
2. `2026_04_29_100003_backfill_recorded_at_on_ledger_entries` — `UPDATE ledger_entries SET recorded_at = created_at WHERE recorded_at IS NULL`.
3. `2026_04_29_100004_make_recorded_at_not_nullable_on_ledger_entries` — change to NOT NULL once the backfill is done.

The backfill is a single `UPDATE` statement. **For tables in the millions of rows, chunk the backfill manually before running step 3** (e.g. `WHERE id BETWEEN ? AND ?` slices in a separate one-off command), otherwise the single statement can grow the InnoDB undo log and lock the table for an extended window. This codebase's row counts are well under that threshold, so the simple form is shipped — re-evaluate before the table grows past ~1M rows.

## 13. Future work (not in scope)

- **Forward-dating** — `recorded_at` is currently constrained to `<= today`. A separate feature could relax this for known scheduled transactions.
- **Automatic-vs-manual badge** — derivable from `reference->gateway` if a future audit view wants it.
- **B2 storage model** — drop stored `balance_after` and compute on read. Decided against in v2 because the recompute walker is small and `balance_after` is a useful historical record.
- **PHPStan rule banning `LedgerEntry::create()` outside `LedgerService`** — would close the only enforcement gap left by convention.
