# Architecture Reference

## Pattern: Domain-Driven Design (DDD) + Repository Pattern

This project uses a **layered DDD architecture** inside a Laravel monolith. Each domain (e.g. SSA, Student, Finance) lives under `app/Domain/<Domain>/` and owns its Services and Repository interfaces. Infrastructure (Eloquent implementations) lives under `app/Infrastructure/Repositories/`.

### Request Flow

```
HTTP Request
    → Controller          (validates, delegates, responds)
        → Service         (business rules, orchestration)
            → Repository  (data access only)
                → Eloquent Model
```

---

## Layer Responsibilities

| Layer | Lives in | Responsibility | Cannot touch |
|---|---|---|---|
| **Controller** | `app/Http/Controllers/` | Validate input, call one service, return response | Repositories, Eloquent models, DB directly |
| **Service** | `app/Domain/<Domain>/Services/` | Business rules, multi-step orchestration, transactions | Eloquent models directly, `DB::`, HTTP layer |
| **Repository Interface** | `app/Domain/<Domain>/Repositories/` | Contract for data access | — |
| **Repository (Eloquent)** | `app/Infrastructure/Repositories/` | DB queries for its own model(s) only | Other Repositories, Services |
| **Model** | `app/Models/` | Relationships, scopes, casts | Services, Repositories |

---

## Hard Boundary Rules

### Services

- Create service classes in `app/Domain/[Entity]/Services/`
- Encapsulate business logic
- Use dependency injection for repositories
- **MUST NOT** call `Model::query()`, `Model::where()`, or `DB::` directly.
- All DB access goes through a **Repository interface**.
- If data from another domain is needed (e.g. `StudentProfile` inside `SSAService`), inject that domain's Repository — do NOT reach for the model directly.
- Own the `DB::transaction()` boundary for multi-step writes.

```php
// ❌ Wrong — direct model query inside a Service
$schoolIds = StudentProfile::query()->forUserIds($studentIds)->pluck('school_id');

// ✅ Correct — go through an injected repository
$schoolIds = $this->studentProfileRepo->getSchoolIdsByStudentIds($studentIds);
```

### Repositories
- Define interfaces in `app/Domain/[Entity]/Repositories/`
- Implement in `app/Infrastructure/Repositories/`
- **MUST NOT** inject or call other Repositories.
- **MUST NOT** contain business logic — no `if/else` on domain state, no side effects.
- A Repository is a **leaf node**: it only touches its own Eloquent model(s).
- Cross-domain data needs belong in the Service that orchestrates both repos.

```php
// ❌ Wrong — SSARepository calling InvoiceRepository
class EloquentSSARepository {
    public function __construct(private InvoiceRepositoryInterface $invoiceRepo) {}
}

// ✅ Correct — Service injects both and orchestrates
class SSAService {
    public function __construct(
        private SSARepositoryInterface $ssaRepo,
        private InvoiceRepositoryInterface $invoiceRepo,
    ) {}
}
```

### Controllers

- **MUST NOT** contain business logic — no multi-step operations, no domain `if/else`.
- Call **one service method** per action; the service does the rest.
- Use Form Requests for all validation. Never validate manually in a controller.

---

## Directory Structure

```
app/
├── Domain/
│   └── <Domain>/
│       ├── Repositories/       # Interfaces only
│       └── Services/           # Business logic
├── DTOs/                       # Input transport between layers
├── Http/
│   ├── Controllers/            # Thin — delegate to services
│   └── Requests/               # Form Request validation
├── Infrastructure/
│   └── Repositories/           # Eloquent implementations
└── Models/                     # Eloquent models, scopes, relationships
```

---

## DTOs

Always use DTOs to pass data between layers. Never pass raw arrays or `$request` objects into a Service or Repository.

```php
// ❌ Wrong
$service->create($request->all());

// ✅ Correct
$dto = CreateSSADTO::fromRequest($request);
$service->create($dto);
```

---

## Binding

Repository interfaces are bound to their implementations via Laravel's service container in `AppServiceProvider`. When adding a new repository, register it there:

```php
$this->app->bind(SSARepositoryInterface::class, EloquentSSARepository::class);
```

---

## Scopes

Query conditions that are reusable or represent a domain concept belong in a scope class, not inline in a repository. Extend `BaseModelScope` and place in `app/Models/Scopes/`.

Keep models clean by delegating scope logic

```php
// ❌ Wrong — inline where in repository
$query->where('status', 'active')->where('school_id', $schoolId);

// ✅ Correct — named scope on the model
$query->active()->forSchool($schoolId);
```

---

## Models

- **Soft deletes by default.** Every model and migration must use `SoftDeletes` + `$table->softDeletes()`. Hard deletes require explicit justification.
- **Auto-discovery is mandatory** — do not register manually in `AppServiceProvider`:
  - **Policies** → `App\Policies\<Model>Policy` auto-discovered. Never call `Gate::policy(...)`.
  - **Observers** → Use `#[ObservedBy(ModelObserver::class)]` on the model. Never call `Model::observe()`.
  - **Event listeners** → Use `#[AsEventListener]` on the listener class. Never call `Event::listen()`.
  - Manual registration is only allowed when the naming convention doesn't apply. Add a comment explaining why.

---

## API Resources & JSON Response Patterns

For controller endpoints that return a JSON object payload (single record or list), use Laravel's API Resources to shape the response. The controller delegates to a service for data and to a Resource for shape. Reference: https://laravel.com/docs/12.x/eloquent-resources.

| Output | Pattern |
|---|---|
| JSON object (single record) | `JsonResource` under `app/Http/Resources/<Domain>/` |
| JSON list / paginated | `Resource::collection()` or `ResourceCollection` |
| DataTables rows | `App\DataTables\Transformers\*RowTransformer` |
| Trivial ack (`{ ok: true }`) | Inline `response()->json()` is acceptable |

- **Single record** → `JsonResource` subclass under `app/Http/Resources/<Domain>/`.
- **Multiple records / paginated** → `Resource::collection($items)` for the simple case; a `ResourceCollection` subclass when the envelope itself needs metadata.
- Resources MAY contain **presentation-only** helpers (formatted strings, derived labels). Domain logic stays on models/services.
- Pass per-request context via `->additional([...])` — never read globals inside `toArray()`.
- Wrap a single resource by setting `public static $wrap = '<key>';` when the JS contract expects an envelope.
- When a resource grows past ~150 lines or its sub-shape is reused, extract into a nested resource.
- Inline `response()->json([...])` is acceptable only for trivial acks. Anything object-shaped must use a Resource.
- DataTables stays on `RowTransformer` — rows are positional HTML arrays, not named-key data. Do not migrate DataTables endpoints to Resources.

---

## Roles & Authorization

Four roles: `admin`, `therapist`, `student`, `parent`. Protect routes with `role` middleware. Every new model or feature must have a Policy. Use `$this->authorize()` in controllers — never inline `Gate::` checks.

---

## File Size Limits

Hard cap: **300 lines per PHP file**, **400 lines per JS file**. If approaching the limit, extract to smaller classes, dedicated services, or JS modules. Exceeding requires a comment with a follow-up task to split.
