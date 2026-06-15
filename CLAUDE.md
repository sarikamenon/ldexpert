# Laravel Project - LD Export System

## General Rules

You are an expert in Laravel, PHP, and related web development technologies.

## Core Laravel Principle

**Follow Laravel conventions first.** If Laravel has a documented way to do something, use it. Only deviate when you have a clear justification.

> **See [app/docs/LARAVEL_CONVENTIONS.md](app/docs/LARAVEL_CONVENTIONS.md)** for the full Laravel-first rule set — banned native-PHP idioms (`date()`, `array_map`, `implode`, inline `response()->json`, `file_get_contents`, `env()` outside config, etc.) and their Laravel replacements (Carbon, Collections, `Str::`, Resources, `Storage`, `config()`). New code MUST follow it; touched code SHOULD be migrated.

## Key Principles

- Write concise, technical responses with accurate PHP examples.
- Follow Laravel best practices and conventions.
- **Return early** wherever possible. Avoid deep nesting; flip conditions and return/throw early to keep the happy path at the top level.
- **Use vanilla JS for new frontend code** instead of jQuery. When modifying existing jQuery code in the same file, migrate touched sections to vanilla JS incrementally.
- Use object-oriented programming with a focus on SOLID principles.
- Prefer iteration and modularization over duplication.
- Use descriptive variable and method names.
- Use lowercase with dashes for directories (e.g., app/Http/Controllers).
- Favor dependency injection and service containers.

## Architecture Standards

> **See `app/docs/ARCHITECTURE.md`** for the full DDD layer contract, hard boundary rules with examples, and directory structure. The rules below are enforced in addition to that document.

- **Monolith only**: No public API controllers. Use Blade pages with Form Requests and jQuery AJAX where asynchronous behavior is needed.
- **Always use DTOs** for input transport between layers.
- **Always use Form Request classes** for validation. Controllers MUST type-hint Request objects from `app/Http/Requests/**`.
- **Prefer Eloquent**; raw queries only with justification.
- **Prefer `whereHas` over `whereExists` with subqueries** when filtering by related model conditions. `whereHas` uses Eloquent relationships, respects soft deletes, and reads as business logic rather than SQL. Only fall back to `whereExists`/`DB::raw` when the relationship does not exist and adding it would be disproportionate, or when performance profiling justifies it. If a `whereHas` is needed and the inverse relationship is missing from the model, add it first.
- **Prefer collection methods** (`map`, `filter`, `reject`, `flatMap`, etc.) over `foreach` loops when transforming or filtering Eloquent results. Loops are acceptable only for side-effectful operations (e.g., creating DB records inside the loop).
- **Always use `use` statements** for class imports. Never use fully qualified class names (e.g., `\App\Models\User`) in code; use `use App\Models\User;` at the top instead.
- **No public registration routes**; users created via command or privileged UI.
- **Ledger writes** (`ledger_entries`): every insert MUST go through `App\Domain\Finance\Services\LedgerService` (`createEntry`, the four credit-note/refund creators, or the source-document creators). Never call `LedgerEntry::create()` directly outside the service. Every entry MUST have `recorded_at` set from a real source-document date — never let it default. Backdated, edited, or deleted entries MUST run through `LedgerService::recomputeChainFrom()` to maintain the `balance_after` invariant. Only `credit_note` and `refund` types are editable/deletable from the ledger UI; all other types must be edited via their source-document page (Invoices, Bills, Payments, Expenses). Sign convention lives in `TransactionType::balanceDelta()` — never hardcode `+/-`. Run `php artisan ledger:verify` to audit drift. See [LEDGER_SYSTEM.md](app/docs/LEDGER_SYSTEM.md) for the full reference.
- **Audit log** (`audits`): change-tracking for opted-in models. Add `use App\Models\Concerns\HasAudits;` to a model and it produces audit rows on `updating` / `deleting`. **Pivot syncs, mass deletes, and `createMany` bypass model events** — for those, add a `*Snapshot()` method on the parent model and emit a custom event via `App\Domain\Audit\Services\AuditRecorder::record()` attached to the parent (see `EloquentSchoolContractRepository::syncServices()` for the canonical pattern). Never audit on child rows — IDs rotate and the timeline fragments. See [AUDIT_SYSTEM.md](app/docs/AUDIT_SYSTEM.md) for the full reference.
- **Follow PSR-12**; run `make qa` before commits.

## Dates & Timezones (MANDATORY)

- **Store all timestamps and date-times in UTC.** No exceptions for new code or new columns. Schedules, session logs, and any future event/instant data go in as UTC.
- **Convert to the user-relevant timezone on read**, never store user-local. The conversion service is `App\Domain\Time\UserTimezoneService` (`parseUserLocalToUtc()` for writes, `toUserTimezone()` for reads, `resolveTimezone()` to look up a user's effective TZ).
- **Whose timezone for display:** per-viewer — display DATETIMEs in the logged-in user's own timezone. Resolved by role: **admin** → `users.timezone`; **therapist** → `therapist_profiles.timezone`; **student** → `student_profiles.timezone`; **school** → `users.timezone`. Use `App\Domain\Time\UserTimezoneService::viewerTimezone()` (to be added) — resolve once per request, then apply to every row in the response. Do NOT use `Schedule::displayTimezone()` / `SessionLog::displayTimezone()` for viewer-facing surfaces; those resolve the **row owner's** TZ and remain reserved for queue jobs and emails that must render in the recipient's TZ. Pure DATE columns (invoice_date, due_date, bill_date, expense_date, paid_at, billing_period_*, contract/SSA start/end) are never timezone-converted. **NOTE:** Existing surfaces still resolve per-row owner TZ — implementation plan is at [`_local_docs/viewer-timezone-display-plan.md`](_local_docs/viewer-timezone-display-plan.md) and must still be applied. Until then, expect mixed behavior.
- **Date and time display formatting** is governed by [BLADE_GUIDELINES.md](app/docs/BLADE_GUIDELINES.md) (pre-format in controllers, never in Blade; use `config('display.time')` / `config('display.datetime')` for all user-visible times). Those rules apply to DataTable transformers, API Resources, and mail templates too — not just Blade.
- **`users.timezone` must mirror the profile's timezone.** Therapist DTOs write to both `users.timezone` and `therapist_profiles.timezone`; student DTOs write to both `users.timezone` and `student_profiles.timezone`. `UserTimezoneService::resolveTimezone()` falls back to the profile if the user row is empty/UTC.
- **Two date-column flavors — know the difference:**
  - **Event dates** (companions to a UTC `start_time`/`recorded_at`): `session_logs.session_date`, `schedules.schedule_date`. These are the **UTC calendar date** of the underlying instant — they are written through the same UTC conversion as the time column. Do NOT treat them as "school-local" or "therapist-local" dates. **For display, always derive the date from the paired datetime (`start_time`/`recorded_at`) converted to the relevant TZ — never from `session_date`/`schedule_date` directly.** TZ-shifting a DATE-only column moves the entire day boundary (a 6 AM NYC session has session_date Apr 29 UTC; that midnight UTC shifts to Apr 28 8 PM NYC, displaying as Apr 28). Use the date column only for SQL filtering at the UTC level. Convert user-local date ranges to UTC ranges via `UserTimezoneService::userDayUtcRange()` first.
  - **Pure calendar dates** (no time companion): `recurrence_end_date`, `ssa.start_date`, contract effective dates. Stored as the user typed them — they represent "the date in the operating timezone" and have no specific UTC moment. No conversion on read or write.
  - When in doubt, ask before adding a new date column.
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

1. Follow DDD layered architecture (see `app/docs/ARCHITECTURE.md`).
2. Use Laravel's routing system for defining application endpoints.
3. Implement proper request validation using Form Requests.
4. Use Laravel's Blade templating engine for views.
5. Implement proper database relationships using Eloquent.
6. Use Laravel's built-in authentication scaffolding.
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
- **Blade view rules** (including the ban on inline `<script>` blocks, `@php` data-shaping, JSON data islands, and the form-control color tokens) live in [BLADE_GUIDELINES.md](app/docs/BLADE_GUIDELINES.md). Read it before writing or modifying any `.blade.php` file.

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


## Testing Standards

- **All tests must be written in Pest syntax** — use top-level `it()` / `test()` functions, `expect()` assertions, and `uses()` for traits. Never write new tests as PHPUnit classes (`class FooTest extends TestCase { public function test_... }`). Existing PHPUnit-style tests should be migrated to Pest incrementally when touched. The runner is still PHPUnit under the hood so both work, but Pest is the project standard going forward.
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

## QA Browser Testing

This project has a dedicated QA browser test suite separate from regular Dusk tests.

### Folder Structure
- `app/tests/BrowserQA/` — QA-authored Dusk tests (generated from `qa/LD-Expert-QA.xlsx`)
  - `Admin/` — Admin flow tests (TC-A001–A030)
  - `Therapist/` — Therapist flow tests (TC-T001–T025)
  - `Student/` — Student flow tests (TC-S001–S025)
  - `Finance/` — Finance/billing tests (TC-F001–F010)
  - `E2E/` — End-to-end cross-role tests (TC-E001–E015)
- `app/tests/Browser/` — Developer Dusk tests (separate, not QA-owned)

### Base Class
All QA browser tests extend `Tests\BrowserQA\QaDuskTestCase` (at `app/tests/BrowserQA/QaDuskTestCase.php`), which:
- Uses selective data cleanup via `cleanUpQaTestData()` method called in `tearDown()`
- Deletes only test-created data with `qa` prefix in email or `QA ` prefix in school names
- Preserves seed data (system admin, production records) between tests for fast, safe runs
- Always targets `bird_test` database via `.env.testing` — **never touches the main `bird` DB**

### Running QA Tests (Docker)
```bash
# All QA browser tests
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/ --env=testing'

# By role
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Admin/ --env=testing'
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Therapist/ --env=testing'
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Student/ --env=testing'
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Finance/ --env=testing'
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/E2E/ --env=testing'
```

### Available QA Skill Commands
| Command | What it runs |
|---------|-------------|
| `/qa` | Code quality pipeline (Pint + PHPStan + Pest) — NOT browser tests |
| `/qa-smoke` | Smoke tests — fastest sanity check (login + key pages) |
| `/qa-admin` | Admin flow browser tests |
| `/qa-therapist` | Therapist flow browser tests |
| `/qa-student` | Student flow browser tests |
| `/qa-finance` | Finance/billing browser tests |
| `/qa-e2e` | End-to-end cross-role tests |
| `/qa-generate-tests` | Generate Dusk test files from `qa/LD-Expert-QA.xlsx` |

### Database Safety
- `.env.testing` always sets `DB_DATABASE=bird_test` and `DB_HOST=mysql` (Docker-internal)
- After each test, `cleanUpQaTestData()` deletes only records with `qa*` email prefix or `QA *` school name prefix
- Seed data (system admin, production data) is preserved across all test runs — safe for repeated execution and staging databases
- The main `bird` database is **never touched** by any test run
- **Factory helpers:** Use `$this->createQaUser()` and `$this->createQaSchool()` to ensure test data gets auto-cleaned

## Design System & UI/UX Standards (MANDATORY)

UI rules — typography, spacing, interactive states, accessibility, responsiveness, empty states, destructive-action confirmation, color tokens, form structure, Blade components — all live in [BLADE_GUIDELINES.md](app/docs/BLADE_GUIDELINES.md). The full design-system reference is [DESIGN_SYSTEM.md](app/docs/DESIGN_SYSTEM.md); known gaps are tracked in [DESIGN_PRINCIPLES_GAP_ANALYSIS.md](app/docs/DESIGN_PRINCIPLES_GAP_ANALYSIS.md).

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
- [ ] Ran through the Blade checklist in [BLADE_GUIDELINES.md](app/docs/BLADE_GUIDELINES.md) (no `@php` data-shaping, no `<script>` blocks, design tokens only, form structure correct, etc.)

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
