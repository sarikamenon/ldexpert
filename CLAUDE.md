# Laravel Project - LD Export System

## General Rules

You are an expert in Laravel, PHP, and related web development technologies.

## Key Principles

- Write concise, technical responses with accurate PHP examples.
- Follow Laravel best practices and conventions.
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
- **Follow PSR-12**; run `make qa` before commits.

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
- **Use jQuery for DOM/AJAX interactivity**; avoid vanilla JS for features.
- **Keep CSS and JS in separate files**. Use Tailwind for styles.

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

## Testing Standards

- **Tests are mandatory for new logic**:
 - Unit tests for DTOs/Services/Repositories
 - Feature tests for routes/commands
 - Dusk browser tests for any UI (views, forms, interactions)
 - **Do not merge features without corresponding tests**
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

### Design Principles (MANDATORY)

- **Modern & Clean**: Prioritize whitespace, clear typography, and minimal visual clutter. Avoid busy layouts typical of legacy software.
- **User-Centric**: Design for task completion, not data display. Hide complexity; reveal progressively.
- **Consistency**: Establish and reuse UI patterns across all pages. Document any new patterns before implementing.

### Visual Foundation (MANDATORY)

- **Color System**: Use ONLY colors defined in `tailwind.config.js`. NEVER introduce ad-hoc colors (e.g., `bg-[#ff0000]`, `bg-red-50`, `text-red-600`).
  - Use design system colors: `bg-primary`, `bg-secondary`, `bg-success`, `bg-warning`, `bg-danger`
  - Use `text-danger` instead of `text-red-600` for error states
- **Typography**: Follow established scale consistently:
  - H1: `text-2xl font-semibold text-foreground`
  - H2: `text-lg font-semibold text-foreground`
  - H3: `text-sm font-medium text-foreground/70`
  - Body: `text-sm text-foreground`
  - Labels: `text-xs font-medium text-foreground/70`
- **Spacing**: Use standard scale (2, 4, 6, 8) only. Card padding: `p-6`. Section spacing: `mb-6`.

### Component Behavior (MANDATORY)

- **Interactive States**: ALL interactive elements MUST have complete state patterns:
  - Default: Base styling
  - Hover: `hover:bg-{variant}/90`
  - Focus: `focus:outline-none focus:ring-2 focus:ring-ring`
  - Focus-Visible: `focus-visible:ring-2 focus-visible:ring-ring` (for keyboard navigation)
  - Active: `active:bg-{variant}/80`
  - Disabled: `disabled:opacity-50 disabled:pointer-events-none`
- **Feedback**: Every user action requires immediate visual response
- **Loading States**: Use SweetAlert2 loading OR button spinners OR skeleton loaders
- **Empty States**: Use `x-ui::empty-state` component with `py-12` spacing
- **Errors**: Show contextually near source with `text-danger` color

### User Experience Patterns (MANDATORY)

- **Progressive Disclosure**: Hide complexity behind clear affordances. Use collapsible sections for long forms.
- **Destructive Actions**: Require explicit confirmation with clear consequence explanation.
- **Error Recovery**: Provide clear paths to resolve errors; avoid dead ends.
- **Responsiveness**: Design must work on mobile, tablet, and desktop viewports.

### Accessibility Requirements (MANDATORY)

- Keyboard navigation must work for all interactive elements
- Color cannot be the only indicator of state or meaning
- Text must have sufficient contrast against backgrounds
- Use semantic HTML and appropriate ARIA labels
- Form fields MUST have help text: Label → Help Text → Input → Error Messages
- Help text styling: `class="mt-1 text-xs text-foreground/60"`
- Add `aria-describedby` linking to help text IDs

### Design Quality Gates (MANDATORY)

Before considering UI complete, verify:
- [ ] Follows established color palette and typography scale
- [ ] Has clear visual hierarchy with one primary action per view
- [ ] Includes all states: default, loading, empty, error, success
- [ ] Related content is properly grouped and visually separated
- [ ] Works on mobile, tablet, and desktop viewports
- [ ] Passes keyboard-only navigation test
- [ ] Matches established patterns from reference pages

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

- **All form inputs MUST have help text** to guide users
- **Help text MUST be placed BEFORE the input field** (between label and input)
- Use consistent styling: `class="mt-1 text-xs text-foreground/60"`
- Standard structure: Label → Help Text → Input → Error Messages

### Required Pattern (MANDATORY):

```blade
<div>
 <x-input-label for="field_name" value="Field Label *" />
 <p class="mt-1 text-xs text-foreground/60" id="field_name_help">
 Clear, concise help text explaining what this field is for and any requirements.
 </p>
 <x-text-input
 id="field_name"
 name="field_name"
 class="mt-1 block w-full"
 aria-describedby="field_name_help"
 />
 <x-input-error :messages="$errors->get('field_name')" class="mt-2" />
</div>
```

### Guidelines (MANDATORY):

- Help text should explain the purpose of the field, format requirements, or constraints
- Use `aria-describedby` on inputs linking to help text ID for accessibility
- Keep help text concise (1-2 sentences maximum)
- For optional fields, mention they're optional in the help text if not obvious
- For date/time fields, specify timezone context if relevant
- For numeric fields, specify units, ranges, or increments (e.g., "Duration in minutes (minimum 5, increments of 5)")
- Never place help text after the input field
- **VIOLATION**: Any form field without help text is a design standards violation

### Implementation Rules (MANDATORY)

- **ALWAYS** use design system components (`x-ui::*`)
- **NEVER** use hardcoded colors or arbitrary values
- **ALWAYS** add help text to form inputs
- **ALWAYS** include all required interactive states
- **ALWAYS** test responsive behavior
- **ALWAYS** verify accessibility requirements
- **ALWAYS** follow the design quality checklist

### Pattern Documentation Requirement (MANDATORY)

- **MANDATORY**: Document any new UI patterns in `app/docs/DESIGN_SYSTEM.md` BEFORE implementing
- **MANDATORY**: Update design system documentation when adding new components
- **MANDATORY**: Reference `app/docs/DESIGN_PRINCIPLES_GAP_ANALYSIS.md` for known issues

## Project-Enforced Conventions

- **Always run commands via Docker**:
  - Use `docker compose exec -T app bash -lc 'cd app && <command>'` or Makefile targets (e.g., `make migrate`, `make qa`). Never run host PHP/Node directly.
  - Run migrations via Docker (e.g., `docker compose exec -T app bash -lc 'cd app && php artisan migrate'`).
- **After any frontend asset changes** (`resources/js`, `resources/css`, etc.), run `make assets-build` before QA or deployment so the Vite manifest stays updated.
- **When introducing a new page-specific JS/CSS entry**, immediately register it in `vite.config.js` and rerun `make assets-build` so Vite's manifest includes the chunk before opening the corresponding Blade view.
- **Local-only documentation**: Implementation plans, feature analysis, test failure analysis, implementation summaries, and similar "done but not implemented" or temporary analysis documents must be stored in the **`_local_docs/`** folder at the repository root. This folder is in `.gitignore` and must not be committed or pushed. When creating or saving such artifacts, always place them in `_local_docs/` to keep the repo clean and data structured.

## Quality Gates

- **Every development task must include tests** and follow these rules by default, even if work will be refined later.
- **Before implementing a feature**, add (or outline) the tests; before moving to next steps, run tests locally (Feature/Unit) and Dusk (headless) in Docker and fix failures.
- **PHPStan Level 8 must pass with zero errors** before any commit. Run `docker compose exec -T app php vendor/bin/phpstan analyse --no-progress --memory-limit=512M` or `make qa`.

## PHPStan Level 8 Compliance (MANDATORY)

This project enforces PHPStan Level 8 with Larastan (`checkModelProperties: true`). All new and modified PHP code MUST pass with zero errors. The rules below prevent the most common violations.

### General

- Every PHP file MUST start with `declare(strict_types=1);`.
- Every method MUST have a native return type. If the return type involves generics (Collection, Builder, Paginator, arrays), also add a `@return` PHPDoc tag.
- Every method parameter MUST be typed (native type + PHPDoc for generics).
- Never use bare `array` as a type — always specify value types: `array<string, mixed>`, `array<int, string>`, `array{key: type, ...}`, etc.

### Model Relations

Every Eloquent relation method MUST include full generic annotations:

```php
/** @return HasOne<TherapistProfile, $this> */
public function therapistProfile(): HasOne { ... }

/** @return BelongsTo<User, $this> */
public function student(): BelongsTo { ... }

/** @return HasMany<SessionLog, $this> */
public function sessionLogs(): HasMany { ... }

// BelongsToMany with custom pivot:
/** @return BelongsToMany<Service, $this, SSAService, 'pivot'> */
public function services(): BelongsToMany { ... }

// BelongsToMany with default pivot (no ->using()):
/** @return BelongsToMany<User, $this> */
public function students(): BelongsToMany { ... }

// MorphMany:
/** @return MorphMany<Document, $this> */
public function documents(): MorphMany { ... }
```

### HasFactory Trait

Every model using `HasFactory` MUST have a `@use` annotation:

```php
// With a dedicated factory:
/** @use HasFactory<\Database\Factories\UserFactory> */
use HasFactory;

// Without a dedicated factory:
/** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<static>> */
use HasFactory;
```

### Eloquent Nullability Rules

- **BelongsTo relations** return `Model|null`. When accessing properties through a BelongsTo, use nullsafe: `$sessionLog->student?->name`.
- **Cast enum properties** are always the enum type (never null, never string) when the column is NOT nullable. Do NOT use `?->` on them: `$model->status->value` (correct), NOT `$model->status?->value`.
- **Nullable Carbon columns** (`end_date`, etc.) may be null. Use nullsafe: `$ssa->end_date?->format('Y-m-d') ?? ''`.
- **Non-nullable Carbon columns** (`start_date`, `created_at`) are always Carbon. Do NOT use `?->`: `$ssa->start_date->format('Y-m-d')`.

### Collections, Builders, and Paginators

Always specify generics on Collection, Builder, and Paginator types:

```php
/** @return Collection<int, SessionLog> */
/** @return Builder<User> */
/** @return LengthAwarePaginator<int, SessionLog> */
/** @param Collection<int, ServiceSupportAgreement> $ssas */
```

**Important**: `LengthAwarePaginator` from `Illuminate\Contracts\Pagination` is NOT iterable. Use `->items()` to get the array for `foreach`:

```php
foreach ($paginator->items() as $item) { ... }
```

### FormRequest Methods

```php
// rules() — MUST have this exact annotation:
/** @return array<string, array<int, mixed>|string> */
public function rules(): array { ... }

// messages() — MUST have this annotation:
/** @return array<string, string> */
public function messages(): array { ... }

// withValidator() — MUST type the parameter:
public function withValidator(\Illuminate\Validation\Validator $validator): void { ... }

// baseRules() in abstract FormRequests:
/** @return array<string, array<int, mixed>|string> */
protected function baseRules(): array { ... }
```

### Model Scopes

Scope methods MUST type both parameter and return:

```php
/**
 * @param Builder<User> $query
 * @return Builder<User>
 */
public function scopeActive(Builder $query): Builder
{
    return $query->where('is_active', true);
}
```

### Repository & Service Methods

- Interface and implementation MUST have matching `@param` and `@return` PHPDoc.
- Never return bare `array` — always specify shape or value types:

```php
/** @param array<string, mixed> $data */
public function create(array $data): Model;

/** @return array{total: int, active: int, inactive: int} */
public function metrics(): array;

/** @return array<string, mixed> */
public function formPayload(): array;
```

### DTO Methods

```php
/** @param array<string, mixed> $data */
public static function fromArray(array $data): self { ... }

/** @return array<string, mixed> */
public function toArray(): array { ... }
```

### Common Pitfalls and Fixes

| Pitfall | Wrong | Correct |
|---------|-------|---------|
| `file_get_contents()` returns `string\|false` | `$content = file_get_contents($path);` | `$content = (string) file_get_contents($path);` |
| `fgetcsv()` returns nullable values | `array_map('trim', $row)` | `array_map(static fn ($v): string => trim((string) $v), $row)` |
| `Model::find()` returns `Model\|null` | `$user = User::find($id);` | `/** @var User $user */ $user = User::findOrFail($id);` |
| `Model::findOrFail()` union type | `$m = Model::findOrFail($id);` | `/** @var User $m */ $m = User::findOrFail($id);` |
| Pivot attribute access | `$model->pivot->amount` | `$model->getRelation('pivot')->amount` |
| Dynamic/computed attribute | `$model->computed_attr` | `$model->getAttribute('computed_attr')` |
| `Model::delete()` returns `bool\|null` | `return $model->delete();` | `return (bool) $model->delete();` |
| `groupBy()` key type | `Collection<int, ...>` | `Collection<int\|string, ...>` |
| Enum `instanceof` on cast prop | `$user->role instanceof Role` | Always true — just use `$user->role === Role::ADMIN` |

### Builder::where() Column Strings

Larastan validates column names in `Builder::where('column', ...)` calls against model properties. When using `@template TModel of Model` on generic query methods, Larastan resolves columns against the base `Model` class (which has no columns). This is a known Larastan limitation. Suppress with:

```php
$query->where('column_name', $value); // @phpstan-ignore argument.type
```

Only use this ignore for Builder column string errors, never for other argument.type issues.
