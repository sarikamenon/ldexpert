# Service Aliases – Implementation Plan

## Overview

Admin UI to manage aliases (mappings) between external service names (from RSM, MARVIN, future sources) and system services. Enables SSA import and other integrations to resolve external service names to internal `services` records.

The table is called `service_aliases` because the concept is not limited to imports — any integration that needs to translate an external service name to an internal one can use this table.

**Scope (this phase):** Admin CRUD UI + seed RSM aliases. Import logic (`SSAImportService`) will be updated to use aliases in a follow-up.

---

## 1. Database

### Migration: `create_service_aliases_table`

| Column | Type | Attributes |
|--------|------|------------|
| id | bigint | PK, auto-increment |
| source | string(50) | NOT NULL, index — identifies the external system (RSM, NOVA, MARVIN) |
| external_name | string(255) | NOT NULL — the name used in the external system |
| service_id | foreignId | NOT NULL, FK → services.id, cascade on delete |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp | nullable (soft deletes) |

**Unique constraint:** `(source, external_name)` – one alias per external name per source.

**Indexes:** `source`, `(source, external_name)` unique.

---

## 2. Model

### `App\Models\ServiceAlias`

- Use `SoftDeletes` trait
- Fillable: `source`, `external_name`, `service_id`
- Casts: `source` → string (or future enum)
- Relationships:
  - `belongsTo(Service::class)` with `@return BelongsTo<Service, $this>`
- Scopes:
  - `scopeForSource(Builder $query, string $source): Builder`
- Constants: `SOURCES = ['RSM', 'NOVA', 'MARVIN']` (or use `SSAImportType` enum values)

---

## 3. Routes

Add to admin routes (same level as `positions`, `services`):

```
POST   /admin/service-aliases/data                        → data (DataTables)
GET    /admin/service-aliases                              → index
GET    /admin/service-aliases/create                       → create
POST   /admin/service-aliases                              → store
GET    /admin/service-aliases/{service_alias}/edit          → edit
PUT    /admin/service-aliases/{service_alias}               → update
DELETE /admin/service-aliases/{service_alias}               → destroy
```

Route names: `admin.service-aliases.*`

Pattern follows `positions` — flat under admin prefix, not nested under `settings/`.

---

## 4. Controller

### `App\Http\Controllers\Admin\ServiceAliasController`

Follow the `PositionController` pattern:

- `index()` – List view with metrics (total, per-source counts) + DataTables
- `data(ServiceAliasDataRequest)` – Server-side DataTables JSON (filter by source, search)
- `create()` – Create form (source dropdown, external_name input, service dropdown)
- `store(StoreServiceAliasRequest)` – Validate and create
- `edit(ServiceAlias)` – Edit form
- `update(UpdateServiceAliasRequest, ServiceAlias)` – Validate and update
- `destroy(ServiceAlias)` – Soft delete with SweetAlert confirmation

**Authorization:** `$this->authorize(...)` on each method using `ServiceAliasPolicy`.

**ORDER_WHITELIST:** `['external_name', 'source', 'created_at']`

---

## 5. Form Requests

### `StoreServiceAliasRequest`

- `source`: required, string, in:RSM,NOVA,MARVIN
- `external_name`: required, string, max:255, unique together with source (Rule::unique with where clause)
- `service_id`: required, exists:services,id (service must be active)

### `UpdateServiceAliasRequest`

- Same rules, `unique` ignores current record

### `ServiceAliasDataRequest`

- `filter_source`: nullable, string, in:RSM,NOVA,MARVIN
- `filter_search`: nullable, string, max:255

---

## 6. Policy

### `ServiceAliasPolicy`

- `viewAny`, `view`, `create`, `update`, `delete` → admin only (`$user->role === Role::ADMIN`)

Register in `AppServiceProvider`.

---

## 7. Views

Follow the positions pattern (`x-ui::card`, help text before inputs, DataTables).

### Index: `resources/views/admin/service-aliases/index.blade.php`

- Page header: "Service Aliases" / "Map external service names from RSM, NOVA, and other sources to system services"
- Metrics row: Total aliases, per-source counts (RSM: X, NOVA: X, MARVIN: X)
- Filter toolbar: Source dropdown (All, RSM, NOVA, MARVIN) + Search input
- DataTable columns: Source, External Name, System Service, Created, Actions (Edit, Delete)
- "Add Alias" button → create
- `data-datatable-url="{{ route('admin.service-aliases.data') }}"`

### Create: `resources/views/admin/service-aliases/create.blade.php`

- Form card with shared `_form.blade.php` partial
- Back link → index

### Edit: `resources/views/admin/service-aliases/edit.blade.php`

- Same form partial, pre-filled
- Back link → index

### Form partial: `resources/views/admin/service-aliases/_form.blade.php`

- **Source** (select, required)
  - Help text: "The external system this service name comes from."
  - Options: RSM, NOVA, MARVIN
- **External Name** (text, required)
  - Help text: "The service name as it appears in the external system (e.g. 'Speech Therapy Online')."
- **System Service** (select, required)
  - Help text: "The system service this external name maps to."
  - Options: active services from DB, showing service name
- Cancel / Submit buttons

---

## 8. Navigation

Add under **Settings** in `config/navigation.php`:

```php
[
    'label' => 'Service Aliases',
    'route' => 'admin.service-aliases.index',
    'active' => 'admin.service-aliases.*',
],
```

Update Settings `active` array to include `'admin.service-aliases.*'`.

---

## 9. DataTables

### `ServiceAliasRowTransformer`

**File:** `app/DataTables/Transformers/ServiceAliasRowTransformer.php`

Static `transform(ServiceAlias $alias): array` returns:
1. Source badge (styled per source type)
2. External name (escaped)
3. System service name (from `$alias->service->name`)
4. Created at (formatted date)
5. Action buttons (Edit, Delete)

### JS: `resources/js/pages/admin-service-aliases-index.js`

- `initServiceAliasesTable()` – Server-side DataTable with source filter + search
- `setupDeleteHandlers()` – SweetAlert confirmation → AJAX DELETE
- Filter form change/submit → reload DataTable
- Register in `vite.config.js`

---

## 10. Source Values

Use `SSAImportType` enum values for consistency: `RSM`, `NOVA`, `MARVIN`.

For the form dropdown, use `SSAImportType::cases()` to dynamically generate options.

---

## 11. Seeder

### `ServiceAliasSeeder`

Seed known RSM aliases based on the CSV analysis:

| Source | External Name | System Service |
|--------|--------------|----------------|
| RSM | Speech Therapy Online | Speech Therapy |
| RSM | Occupational Therapy Online | Occupational Therapy |
| RSM | Speech Therapy Init-Evaluation Online | Evaluations (Speech, Occupational) |
| RSM | Occupational Therapy Init-Evaluation Online | Evaluations (Speech, Occupational) |
| RSM | Speech Therapy Re-Evaluation Online | Evaluations (Speech, Occupational) |
| RSM | Occupational Therapy Re-Evaluation Online | Evaluations (Speech, Occupational) |
| RSM | Speech Therapy Meeting Attendance | IEP Meetings |
| RSM | Occupational Therapy Meeting Attendance | IEP Meetings |
| RSM | Augmentative and Alternative Communication Services Consultation Online | Speech Therapy |

Use `updateOrCreate` keyed on `(source, external_name)` to be idempotent.

---

## 12. File Checklist

| File | Action |
|------|--------|
| `database/migrations/xxxx_create_service_aliases_table.php` | Create |
| `database/seeders/ServiceAliasSeeder.php` | Create |
| `app/Models/ServiceAlias.php` | Create |
| `app/Policies/ServiceAliasPolicy.php` | Create |
| `app/Http/Controllers/Admin/ServiceAliasController.php` | Create |
| `app/Http/Requests/Admin/StoreServiceAliasRequest.php` | Create |
| `app/Http/Requests/Admin/UpdateServiceAliasRequest.php` | Create |
| `app/Http/Requests/Admin/ServiceAliasDataRequest.php` | Create |
| `app/DataTables/Transformers/ServiceAliasRowTransformer.php` | Create |
| `resources/views/admin/service-aliases/index.blade.php` | Create |
| `resources/views/admin/service-aliases/create.blade.php` | Create |
| `resources/views/admin/service-aliases/edit.blade.php` | Create |
| `resources/views/admin/service-aliases/_form.blade.php` | Create |
| `resources/js/pages/admin-service-aliases-index.js` | Create |
| `vite.config.js` | Add entry |
| `routes/admin.php` | Add routes |
| `config/navigation.php` | Add menu item + update active array |
| `app/Providers/AppServiceProvider.php` | Register policy |

---

## 13. Future: Import Logic Integration

When updating `SSAImportService` to use aliases:

1. In `processRow()`, after extracting `primary_service_name`, resolve via alias:
   ```php
   $alias = ServiceAlias::where('source', $import->type->value)
       ->where('external_name', trim($primaryServiceName))
       ->first();
   $primaryService = $alias?->service ?? $this->lookupService($primaryServiceName);
   ```
2. If neither alias nor direct match found → validation error:
   `"Service 'X' not found for RSM. Add a mapping in Settings → Service Aliases."`
3. Same approach for additional services.

---

## 14. Design / UX Notes

- Follow positions page pattern for layout, metrics, and DataTables
- Source badges: use distinct colors per source (e.g. RSM = blue, NOVA = green, MARVIN = purple)
- External name matching is **case-sensitive** — document this in help text
- Trim `external_name` on input (in Form Request)
- Service dropdown: show only active services, sorted alphabetically by name
- Delete confirmation via SweetAlert2 with alias details shown
