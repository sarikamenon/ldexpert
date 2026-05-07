# Laravel Project - LD Export System

## General Rules

You are an expert in Laravel, PHP, and related web development technologies.

## Key Principles

- Write concise, technical responses with accurate PHP examples.
- Follow Laravel best practices and conventions.
- **Prefer Eloquent scopes** over inline `where` conditions in repository queries. If a query condition is reusable or represents a domain concept, extract it into a scope on the model (via `BaseModelScope`). Only use raw `where` clauses when a scope would be a one-off with no reuse potential.
- **Return early** wherever possible. Avoid deep nesting; flip conditions and return/throw early to keep the happy path at the top level.
- **Use vanilla JS for new frontend code** instead of jQuery. When modifying existing jQuery code in the same file, migrate touched sections to vanilla JS incrementally.
- Use object-oriented programming with a focus on SOLID principles.
- Prefer iteration and modularization over duplication.
- Use descriptive variable and method names.
- Use lowercase with dashes for directories (e.g., app/Http/Controllers).
- Favor dependency injection and service containers.

## Architecture Standards

- **Monolith only**: No public API controllers. Use Blade pages with Form Requests and jQuery AJAX where asynchronous behavior is needed.
- **Always use DTOs** for input transport between layers.
- **Always use Form Request classes** for validation. Controllers MUST type-hint Request objects from `app/Http/Requests/**`.
- **Controllers must delegate to Services**; Services use Repositories.
- **Prefer Eloquent**; raw queries only with justification.
- **Prefer `whereHas` over `whereExists` with subqueries** when filtering by related model conditions. `whereHas` uses Eloquent relationships, respects soft deletes, and reads as business logic rather than SQL. Only fall back to `whereExists`/`DB::raw` when the relationship does not exist and adding it would be disproportionate, or when performance profiling justifies it. If a `whereHas` is needed and the inverse relationship is missing from the model, add it first.
- **Prefer collection methods** (`map`, `filter`, `reject`, `flatMap`, etc.) over `foreach` loops when transforming or filtering Eloquent results. Loops are acceptable only for side-effectful operations (e.g., creating DB records inside the loop).
- **Always use `use` statements** for class imports. Never use fully qualified class names (e.g., `\App\Models\User`) in code; use `use App\Models\User;` at the top instead.
- **Always add policies** for new models/features. Use `$this->authorize()` in controllers.
- **Keep files small and focused**: Hard cap of 300 lines per file. If approaching 300, extract to smaller classes, view components, or dedicated services. No exceptions without a comment justifying and a follow-up task to split.
- **Use soft deletes by default** on Eloquent models and tables (add `deleted_at` with `$table->softDeletes()` and `use SoftDeletes` on the model). Only use hard deletes with explicit justification and tests.
- **No public registration routes**; users created via command or privileged UI.
- **Roles system**: `admin`, `therapist`, `student`, `parent`. Protect routes with `role` middleware.
- **Ledger writes** (`ledger_entries`): every insert MUST go through `App\Domain\Finance\Services\LedgerService` (`createEntry`, the four credit-note/refund creators, or the source-document creators). Never call `LedgerEntry::create()` directly outside the service. Every entry MUST have `recorded_at` set from a real source-document date — never let it default. Backdated, edited, or deleted entries MUST run through `LedgerService::recomputeChainFrom()` to maintain the `balance_after` invariant. Only `credit_note` and `refund` types are editable/deletable from the ledger UI; all other types must be edited via their source-document page (Invoices, Bills, Payments, Expenses). Sign convention lives in `TransactionType::balanceDelta()` — never hardcode `+/-`. Run `php artisan ledger:verify` to audit drift. See [LEDGER_SYSTEM.md](app/docs/LEDGER_SYSTEM.md) for the full reference.
- **Follow PSR-12**; run `make qa` before commits.

## Dates & Timezones (MANDATORY)

- **Store all timestamps and date-times in UTC.** No exceptions for new code or new columns. Schedules, session logs, and any future event/instant data go in as UTC.
- **Convert to the user-relevant timezone on read**, never store user-local. The conversion service is `App\Domain\Time\UserTimezoneService` (`parseUserLocalToUtc()` for writes, `toUserTimezone()` for reads, `resolveTimezone()` to look up a user's effective TZ).
- **Whose timezone for display:** schedule owner = the schedule's therapist. Session log owner = the session log's therapist. Invoice = the invoice's school. Per-row, not per-viewer — so admin viewing a therapist's schedule sees the therapist's local time (consistent across viewers).
- **Pre-format dates in controllers/services, not in Blade.** Blade should only print strings. Controllers attach pre-formatted strings (e.g. `$log->sent_at_formatted`) to view-models or transient model properties (annotate with `@property string|null` for PHPStan).
- **`users.timezone` must mirror the profile's timezone.** Therapist DTOs write to both `users.timezone` and `therapist_profiles.timezone`; student DTOs write to both `users.timezone` and `student_profiles.timezone`. `UserTimezoneService::resolveTimezone()` falls back to the profile if the user row is empty/UTC.
- **Pure calendar dates (not instants)** — e.g. `recurrence_end_date`, `ssa.start_date`, contract effective dates — are stored as the user typed them (no UTC conversion). These represent "the date in the operating timezone," not a specific moment in time. When in doubt, ask before adding a new date column.
- **DTOs must include `timezone`** in `toUserArray()` for any user create/update flow that exposes a timezone field, so `users.timezone` stays in sync with the profile.
- **Never assume the database date matches the user's local date.** A late-evening session in PT will store as the next-day UTC date. Date-range queries (`whereBetween('schedule_date', ...)`) must convert the user's local range to a UTC range using `UserTimezoneService::userDayUtcRange()` before querying.
- **MySQL `CONVERT_TZ` is NOT available** in all environments (staging lacks the named-zone tables). Do timezone conversions in PHP/Carbon, never in SQL.
- **Migrations that re-interpret existing data** (e.g. backfilling from local-as-UTC to true UTC) must snapshot original values to a backup table for reversibility. See `2026_04_30_000001_backfill_schedules_utc_from_therapist_timezone.php` for the canonical pattern.

## PHP/Laravel

- Use PHP 8.2+ features when appropriate (e.g., typed properties, match expressions).
- Follow PSR-12 coding standards.
- Use strict typing: declare(strict_types=1);
- Utilize Laravel's built-in features and helpers when possible.
- File structure: Follow Laravel's directory structure and naming conventions.
- Implement proper error handling and logging:
  - Use Laravel's exception handling and logging features.
  - Create custom exceptions when necessary.
  - Use try-catch blocks for expected exceptions.
  - **Every HTTP request handler (controller action) that calls a service or external operation MUST wrap the call in try-catch.** Catch specific exceptions first (`\InvalidArgumentException`, domain exceptions), then catch `\Throwable` as a fallback to return a user-friendly error response instead of a 500. Log unexpected errors with `Log::error()` before responding.
  - **Side-effect operations (email, notifications, file writes) triggered during an HTTP request MUST be wrapped in try-catch and must not propagate exceptions that would fail the primary action.** Log failures with `Log::error()` and swallow. Exception: if sending is the primary intent (e.g. "Send Invoice" button), log and re-throw so the controller can surface a friendly error.
  - Never let a mailer, notification, or third-party call produce a 500 for the user when the core business action has already succeeded.
- Use Laravel's validation features for form and request validation.
- Implement middleware for request filtering and modification.
- Utilize Laravel's Eloquent ORM for database interactions.
- Use Laravel's query builder for complex database queries.
- Implement proper database migrations and seeders.

## Dependencies

- Laravel (latest stable version)
- Composer for dependency management

## Laravel Best Practices

- Use Eloquent ORM instead of raw SQL queries when possible.
- Implement Repository pattern for data access layer.
- Use Laravel's built-in authentication and authorization features.
- Utilize Laravel's caching mechanisms for improved performance.
- Implement job queues for long-running tasks.
- Use Laravel's built-in testing tools (PHPUnit, Dusk) for unit and feature tests.
- Use Laravel's localization features for multi-language support.
- Implement proper CSRF protection and security measures.
- Use Vite for asset compilation and bundling.
- Implement proper database indexing for improved query performance.
- Use Laravel's built-in pagination features.
- Implement proper error logging and monitoring.

## Key Conventions

1. Follow Laravel's MVC architecture.
2. Use Laravel's routing system for defining application endpoints.
3. Implement proper request validation using Form Requests.
4. Use Laravel's Blade templating engine for views.
5. Implement proper database relationships using Eloquent.
6. Use Laravel's built-in authentication scaffolding.
7. Implement proper API resource transformations.
8. Use Laravel's event and listener system for decoupled code.
9. Implement proper database transactions for data integrity.
10. Use Laravel's built-in scheduling features for recurring tasks.

## JavaScript & Frontend

- Use modern ES6+ JavaScript features.
- Utilize Vite for asset compilation and bundling.
- Implement modular JavaScript with proper imports/exports.
- Use Tailwind CSS for styling following utility-first approach.
- Organize JavaScript files by feature/page in `resources/js/pages/`.
- Use shared utilities in `resources/js/common/`.
- **Use vanilla JS for new DOM/AJAX interactivity**. Migrate touched jQuery sections incrementally when modifying existing files.
- **Keep CSS and JS in separate files**. Use Tailwind for styles.
- **Never put `<script>` blocks inside Blade views or partials.** All page-specific JS must live in `resources/js/pages/<name>.js`, be registered in `vite.config.js`, and loaded via `<x-slot name="scripts">@vite(...)</x-slot>` in the parent view. This applies even for small, self-contained interactions — "it's only a few lines" is not an exception.

## User Interactions & Confirmations

- **Always use SweetAlert2 for user confirmations and alerts** instead of native browser prompts (`alert()`, `confirm()`, `prompt()`).
- SweetAlert2 is installed via npm and should be imported from `'sweetalert2'`.
- Use the helper module `resources/js/common/sweetalert.js` for consistent alert patterns:
  - `confirmDialog()` - For confirmation dialogs with optional input field
  - `successToast()` - For success notifications (toast style)
  - `errorAlert()` - For error messages
  - `showLoading()` - For loading indicators
  - `closeAlert()` - To close any open alert
- SweetAlert2 works seamlessly with Tailwind CSS using custom classes.
- All dialog buttons should use Tailwind-compatible rounded styling.

### Example Usage:

```javascript
import { confirmDialog, successToast, errorAlert } from "../common/sweetalert";

// Confirmation with reason input
const result = await confirmDialog({
  title: "Deactivate User?",
  text: "You are about to deactivate this user.",
  icon: "warning",
  confirmButtonText: "Yes, deactivate",
  showInput: true,
  inputPlaceholder: "Provide a reason...",
});

if (result.isConfirmed && result.value) {
  // Perform action
  await successToast("User deactivated successfully!");
}

// Error handling
try {
  // ... code
} catch (error) {
  errorAlert("An error occurred while processing your request");
}
```

## DataTables Integration

- Use the common DataTables module: `resources/js/common/datatables.js`
- Always await `loadDataTablesLibrary()` before initialization
- Use DataTables CSS from `resources/css/common/datatables.css`
- **New list tables MUST use server-side processing** with the shared pattern:
  - **Backend**: Use `DataTablesRequest::fromRequest($request, ORDER_WHITELIST)` to build params; use an entity-specific `*DataRequest` Form Request for filter validation (`filter_*` keys); call a repository/service `listForDataTables(FilterDTO, DataTablesParamsDTO)`; return JSON via `DataTablesResponse::dataTablesResponse()` or a custom response. **Controllers must not build DataTables JSON or row HTML inline** — use an `App\DataTables\Transformers\XxxRowTransformer` with a static `transform($model): array` that returns one HTML string per column.
  - **Frontend**: Use `initServerSideDataTable(selector, dataUrl, { order, pageLength, columnDefs, getExtraData(d) { ... } })` from `resources/js/common/datatables.js`. Read `data-datatable-url` from the table; pass filter form values in `getExtraData(d)`. Wire filter form `change`/`submit` to reload the table.
  - **Views**: Render the table with an empty `<tbody></tbody>` and set `data-datatable-url="{{ $datatableUrl }}"` when using server-side; pass `datatableUrl` from the controller (e.g. `route('admin.students.data')`).
- Use **POST** for the data endpoint (CSRF); enforce a **column whitelist** for ordering. See `app/docs/DATATABLES_SERVER_SIDE.md` for the contract and list of migrated entities.

## Code Organization Patterns

### DTOs (Data Transfer Objects)

- Create DTOs for data transport between layers
- Implement `fromArray()` and `toArray()` methods
- Place in `app/DTOs/` directory

### Repository Pattern

- Define interfaces in `app/Domain/[Entity]/Repositories/`
- Implement in `app/Infrastructure/Repositories/`
- Bind interfaces in `AppServiceProvider`

### Service Layer

- Create service classes in `app/Domain/[Entity]/Services/`
- Encapsulate business logic
- Use dependency injection for repositories

### Scopes

- Create separate scope classes extending `BaseModelScope`
- Place in `app/Models/Scopes/`
- Keep models clean by delegating scope logic

### Policies

- Create policy classes for authorization
- Register in `AppServiceProvider`
- Use consistent method names: `viewAny()`, `view()`, `create()`, `update()`, etc.

### API Resources (HTTP JSON responses)

For controller endpoints that return a JSON object payload (single record or list), use Laravel's API Resources to shape the response. The controller delegates to a service for data and to a Resource for shape. Reference: https://laravel.com/docs/12.x/eloquent-resources.

- **Single record** → `JsonResource` subclass under `app/Http/Resources/<Domain>/` (e.g., `app/Http/Resources/Schedule/ScheduleDetailsResource.php`).
- **Multiple records / paginated** → `Resource::collection($items)` for the simple case; a `ResourceCollection` subclass when the envelope itself needs metadata (filters applied, summary totals, etc.).
- Resources MAY contain **presentation-only** helpers (formatted strings, derived labels, conditional shapes) as private methods. Domain logic stays on models/services.
- Pass per-request context via `->additional([...])` (e.g., timezone, viewer role) — never read globals inside `toArray()`.
- Wrap a single resource by setting `public static $wrap = '<key>';` when the JS contract expects an envelope (e.g., `{ "schedule": { ... } }`).
- When a resource grows past ~150 lines or its sub-shape is reused, extract the sub-shape into a nested resource (e.g., `ScheduleDetailsResource` → `SsaSummaryResource`).
- Inline `response()->json([...])` payload-building is acceptable only for trivial acks (`{ ok: true }`, simple count responses). Anything object-shaped must use a Resource.

#### When to use which response pattern

| Output                                          | Pattern                                                |
|-------------------------------------------------|--------------------------------------------------------|
| JSON object response (single record)            | `JsonResource`                                         |
| JSON list / paginated response                  | `Resource::collection()` / `ResourceCollection`        |
| DataTables row HTML (positional array)          | `App\DataTables\Transformers\*RowTransformer`          |
| Trivial ack (`{ ok: true }`, `{ count: N }`)    | Inline `response()->json()` is fine                    |

DataTables intentionally stays on `RowTransformer`: rows are positional arrays of pre-rendered HTML strings, not named-key data, so a Resource would be off-label use. Do not migrate DataTables endpoints to Resources.

## Testing Standards

- **Tests are mandatory for new logic**:
 - Unit tests for DTOs/Services/Repositories/**Model methods** (any public method added to a model needs a unit test)
 - Feature tests for routes/commands — including validation rules (happy path + each failure case)
 - Dusk browser tests for any UI (views, forms, interactions)
 - **Do not merge features without corresponding tests**
 - **Factories must reflect the model.** Any new column added to a model must also be added to its factory definition. Missing factory fields cause other tests to fail silently with wrong defaults.
- Write unit tests for DTOs, repositories, and services
- Write feature tests for HTTP workflows
- Write Dusk tests for browser interactions
- Use factories for test data generation
- Test both success and failure scenarios
- Test authorization rules

### UI/UX Testing Requirements (MANDATORY)

- **MANDATORY**: Include accessibility testing for all UI changes
- **MANDATORY**: Test keyboard navigation on all new interactive elements
- **MANDATORY**: Verify responsive design across all viewports (mobile, tablet, desktop)
- **MANDATORY**: Run design quality checklist before marking UI complete
- **MANDATORY**: Verify all form fields have help text and proper ARIA labels
- **MANDATORY**: Test all interactive states (hover, focus, active, disabled)

## Design System & UI/UX Standards (MANDATORY)

See `app/docs/DESIGN_SYSTEM.md` for the full design system reference (colors, typography, spacing, component patterns). See `app/docs/DESIGN_PRINCIPLES_GAP_ANALYSIS.md` for known issues.

**Key rules enforced here:**
- **Colors**: ONLY use design system tokens (`bg-primary`, `text-danger`, etc.). NEVER hardcode hex or Tailwind palette colors. The most common violations to watch for: `gray-*` (use `border-border`, `bg-muted`, `text-foreground/40`), `blue-*` (use `bg-primary`), and raw hex like `#e5e7eb` in `style=` attributes. These look "neutral" but violate the rule just like `red-500` would. When a dynamic user-supplied color (e.g. a color picker value) must go into a `style=` attribute, that is the only valid exception — and it must be escaped with `e()`.
- **Typography**: H1=`text-2xl font-semibold text-foreground`, H2=`text-lg`, H3=`text-sm font-medium text-foreground/70`, Body=`text-sm`, Labels=`text-xs font-medium text-foreground/70`
- **Spacing**: Standard scale (2, 4, 6, 8). Card padding: `p-6`. Section spacing: `mb-6`.
- **Interactive states**: All elements MUST have hover, focus, focus-visible, active, disabled states.
- **Accessibility**: Keyboard navigation, sufficient contrast, semantic HTML, ARIA labels.
- **Responsiveness**: Must work on mobile, tablet, desktop.
- **Empty states**: Use `x-ui::empty-state` component.
- **Destructive actions**: Require explicit confirmation via SweetAlert2.
- Document new UI patterns in `app/docs/DESIGN_SYSTEM.md` BEFORE implementing.

## UI Standards

- Use Blade components for reusable UI elements
- **Create Blade UI components in `resources/views/components/ui`** and reuse them.
- **Prefer `x-ui-card` for page sections and forms**. Always add a card when adding new UI.
- Implement responsive designs with Tailwind CSS
- Provide clear user feedback for all actions
- Use consistent button styles and icons
- Implement proper form validation with error messages
- Show loading states for async operations

## Form Help Text Standards (MANDATORY)

- **All form inputs MUST have help text** placed BEFORE the input (Label → Help Text → Input → Error)
- Help text styling: `class="mt-1 text-xs text-foreground/60"` with `aria-describedby` linking
- Pattern: `<x-input-label>` → `<p id="..._help">` → `<x-text-input aria-describedby="..._help">` → `<x-input-error>`
- Keep help text concise (1-2 sentences). Specify units/ranges for numeric fields, timezone for dates.
- **ALWAYS** use design system components (`x-ui::*`), never hardcoded colors or arbitrary values.

## Project-Enforced Conventions

- **Always run commands via Docker**:
  - Use `docker compose exec -T app bash -lc '<command>'` or Makefile targets (e.g., `make migrate`, `make qa`). Never run host PHP/Node directly. The container's WORKDIR is already `/var/www/html/app` — do NOT prepend `cd app &&`; the nested `app/` does not exist inside the container and the command will fail with "Could not open input file: artisan".
  - Run migrations via Docker (e.g., `docker compose exec -T app bash -lc 'php artisan migrate'`).
- **Create new files via `php artisan make:*` when one exists** (run inside Docker). The command creates any missing parent directories, so do NOT `mkdir` first. Only fall back to manual creation (Write tool / `touch`) when no `make:*` command fits the file type. After generation, overwrite the stub with the real implementation.
- **After any frontend asset changes** (`resources/js`, `resources/css`, etc.), run `make assets-build` before QA or deployment so the Vite manifest stays updated.
- **When introducing a new page-specific JS/CSS entry**, immediately register it in `vite.config.js` and rerun `make assets-build` so Vite's manifest includes the chunk before opening the corresponding Blade view.
- **Local-only documentation**: Implementation plans, feature analysis, test failure analysis, implementation summaries, and similar "done but not implemented" or temporary analysis documents must be stored in the **`_local_docs/`** folder at the repository root. This folder is in `.gitignore` and must not be committed or pushed. When creating or saving such artifacts, always place them in `_local_docs/` to keep the repo clean and data structured.

## Quality Gates

- **Every development task must include tests** and follow these rules by default, even if work will be refined later.
- **Before implementing a feature**, add (or outline) the tests; before moving to next steps, run tests locally (Feature/Unit) and Dusk (headless) in Docker and fix failures.
- **PHPStan Level 8 must pass with zero errors** before any commit. Run `docker compose exec -T app php vendor/bin/phpstan analyse --no-progress --memory-limit=512M` or `make qa`.

### Pre-commit Self-Review Checklist (MANDATORY)

Run through this before marking any task done or raising a PR. These are the rules most commonly violated in review:

**PHP / Architecture**
- [ ] Every new public model method has a unit test
- [ ] Every new validation rule has a feature test (happy path + each failure case)
- [ ] Factory updated for every new model column
- [ ] Controller actions that return a JSON object/list use an API Resource (not inline `response()->json([...])`); DataTables endpoints stay on `RowTransformer`

**Frontend / Blade**
- [ ] No `<script>` blocks inside any `.blade.php` file — JS is in `resources/js/pages/`, registered in `vite.config.js`, loaded via `<x-slot name="scripts">`
- [ ] No `gray-*`, `blue-*`, `red-*` or raw hex (`#xxxxxx`) in Blade or JS — only design tokens (`border-border`, `bg-muted`, `text-foreground/*`, `bg-primary`, etc.)
- [ ] Every form input has help text; help text and label describe **the same thing** (re-read them together)

**General**
- [ ] `make qa` passes (Pint + PHPStan + Pest) with zero errors

## PHPStan Level 8 Compliance (MANDATORY)

This project enforces PHPStan Level 8 with Larastan (`checkModelProperties: true`). All new and modified PHP code MUST pass with zero errors.

**Key rules** (see `app/docs/PHPSTAN_RULES.md` for full reference with code examples):
- Every PHP file: `declare(strict_types=1);`
- Every method: native return type + `@return` PHPDoc for generics
- Every parameter: typed (native + PHPDoc for generics)
- Never bare `array` — always specify: `array<string, mixed>`, `array{key: type}`, etc.
- Model relations: full generic annotations (`/** @return HasMany<SessionLog, $this> */`)
- HasFactory: `@use` annotation required
- BelongsTo: always nullsafe (`$model->relation?->property`)
- Cast enums: never nullsafe on non-nullable (`$model->status->value`)
- Collections/Builders/Paginators: always specify generics
- Builder column strings with `@template`: suppress with `// @phpstan-ignore argument.type`
