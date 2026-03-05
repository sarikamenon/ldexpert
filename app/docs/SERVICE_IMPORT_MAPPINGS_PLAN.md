# Service Import Mappings – Implementation Plan

## Overview

Admin UI to manage mappings between external service names (from RSM, MARVIN, future import types) and system services. Enables SSA import and other imports to resolve CSV service names to internal `services` records.

**Scope (this phase):** Admin UI only. Import logic will use this later.

---

## 1. Database

### Migration: `create_service_import_mappings_table`

| Column | Type | Attributes |
|--------|------|------------|
| id | bigint | PK, auto-increment |
| import_type | string(50) | NOT NULL, index |
| external_name | string(255) | NOT NULL |
| service_id | foreignId | NOT NULL, FK → services.id, cascade on delete |
| created_at | timestamp | |
| updated_at | timestamp | |

**Unique constraint:** `(import_type, external_name)` – one mapping per external name per import type.

**Indexes:** `import_type`, `(import_type, external_name)` unique.

---

## 2. Model

### `App\Models\ServiceImportMapping`

- Fillable: `import_type`, `external_name`, `service_id`
- Casts: `created_at`, `updated_at` → datetime
- Relationship: `belongsTo(Service::class)`
- Scopes: `scopeForImportType(string $type)`
- Validation: `import_type` in [NOVA, RSM, MARVIN]; `external_name` trimmed; `service_id` exists and active

---

## 3. Routes

Under `admin` middleware, add to Settings group:

```
GET    /admin/settings/service-import-mappings              → index
GET    /admin/settings/service-import-mappings/create        → create
POST   /admin/settings/service-import-mappings               → store
GET    /admin/settings/service-import-mappings/{mapping}/edit → edit
PUT    /admin/settings/service-import-mappings/{mapping}     → update
DELETE /admin/settings/service-import-mappings/{mapping}     → destroy (optional for phase 1)
POST   /admin/settings/service-import-mappings/data          → data (for DataTables)
```

Route names: `admin.settings.service-import-mappings.*`

---

## 4. Controller

### `App\Http\Controllers\Admin\ServiceImportMappingController`

- `index()` – List view with DataTables
- `data()` – AJAX DataTables payload (filter by import_type, search)
- `create()` – Create form (import_type dropdown, external_name input, service dropdown)
- `store()` – Validate and create
- `edit()` – Edit form
- `update()` – Validate and update
- `destroy()` – Optional for phase 1

**Authorization:** `$this->authorize('viewAny', ServiceImportMapping::class)` (or equivalent admin policy).

---

## 5. Form Requests

### `StoreServiceImportMappingRequest`

- `import_type`: required, string, in:NOVA,RSM,MARVIN
- `external_name`: required, string, max:255, unique per (import_type, external_name) when creating
- `service_id`: required, exists:services,id, service must be active

### `UpdateServiceImportMappingRequest`

- Same rules, `unique` ignores current record

---

## 6. Policy

### `ServiceImportMappingPolicy`

- `viewAny`, `view`, `create`, `update`, `delete` → admin only

---

## 7. Views

### Index: `resources/views/admin/settings/service-import-mappings/index.blade.php`

- Page header: "Service Import Mappings" / "Map external service names to system services for imports"
- Filters: Import Type (NOVA, RSM, MARVIN), Search (external name or system service name)
- DataTable columns: Import Type, External Name, System Service, Created, Actions (Edit, Delete)
- "Add Mapping" button → create
- Use `x-ui::card`, `x-ui::filter-toolbar`, DataTables pattern (like expense-categories)

### Create: `resources/views/admin/settings/service-import-mappings/create.blade.php`

- Form fields:
  - Import Type (select, required) – NOVA, RSM, MARVIN
  - External Name (text, required) – help text: "Name as it appears in the import CSV (e.g. Speech Therapy Online)"
  - System Service (select, required) – active services from DB
- Back link → index
- Submit → store

### Edit: `resources/views/admin/settings/service-import-mappings/edit.blade.php`

- Same form as create, pre-filled
- Submit → update

---

## 8. Navigation

Add under **Settings** in `config/navigation.php`:

```php
[
    'label' => 'Service Import Mappings',
    'route' => 'admin.settings.service-import-mappings.index',
    'active' => 'admin.settings.service-import-mappings.*',
],
```

Update Settings `active` array to include `admin.settings.service-import-mappings.*`.

---

## 9. DataTables

### `ServiceImportMappingRowTransformer` (or inline in controller)

- Transform each mapping for DataTables: import_type, external_name, service.name, created_at, edit URL, delete URL

### JS: `admin-settings-service-import-mappings-index.js`

- Init DataTable with columns, server-side processing, filters (reuse expense-categories pattern)
- Register in `vite.config.js` if new entry

---

## 10. Import Type Source

Use `SSAImportType::cases()` (or shared enum) for import_type options. Values: NOVA, RSM, MARVIN.

---

## 11. File Checklist

| File | Action |
|------|--------|
| `database/migrations/xxxx_create_service_import_mappings_table.php` | Create |
| `app/Models/ServiceImportMapping.php` | Create |
| `app/Policies/ServiceImportMappingPolicy.php` | Create |
| `app/Http/Controllers/Admin/ServiceImportMappingController.php` | Create |
| `app/Http/Requests/Admin/Settings/StoreServiceImportMappingRequest.php` | Create |
| `app/Http/Requests/Admin/Settings/UpdateServiceImportMappingRequest.php` | Create |
| `app/DataTables/Transformers/ServiceImportMappingRowTransformer.php` | Create (optional) |
| `resources/views/admin/settings/service-import-mappings/index.blade.php` | Create |
| `resources/views/admin/settings/service-import-mappings/create.blade.php` | Create |
| `resources/views/admin/settings/service-import-mappings/edit.blade.php` | Create |
| `resources/js/pages/admin-settings-service-import-mappings-index.js` | Create |
| `routes/admin.php` | Add routes |
| `config/navigation.php` | Add menu item |
| `app/Providers/AppServiceProvider.php` | Register policy |

---

## 12. Future Use (Import Logic)

When implementing RSM SSA import:

1. Lookup: `ServiceImportMapping::where('import_type', 'RSM')->where('external_name', $trimmedValue)->first()`
2. If found → use `mapping->service_id`
3. If not found → validation error: "Service 'X' not found for RSM. Add a mapping in Settings → Service Import Mappings."

---

## 13. Design / UX Notes

- Follow design system: `x-ui::card`, `x-ui::button`, `x-input-label`, help text before inputs
- External name: case-insensitive uniqueness (optional) or document that matching is case-sensitive
- Trim `external_name` on input
- Service dropdown: show `name` (and optionally `id` or description) for active services only
