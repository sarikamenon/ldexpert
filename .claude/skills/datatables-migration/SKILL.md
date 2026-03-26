---
name: datatables-migration
description: Migrate DataTables from client-side to server-side processing in the NOVA project. Use when converting an existing table to server-side, adding a new DataTable list page, or troubleshooting DataTable performance. Triggers on "migrate datatable", "server-side table", "datatable is slow", "add a list page with filtering".
---

# DataTables Server-Side Migration

Migrate NOVA DataTables from client-side to server-side processing following the shared pattern in `app/docs/DATATABLES_SERVER_SIDE.md`.

## Architecture

```
Browser                          Server
  │                                │
  │  POST /entity/data             │
  │  (draw, start, length,         │
  │   order, filter_*)             │
  │ ──────────────────────────►    │
  │                                ├─ FormRequest validates filters
  │                                ├─ DataTablesRequest::fromRequest()
  │                                ├─ Repository::listForDataTables()
  │                                ├─ RowTransformer::transform()
  │  JSON response                 │
  │  (draw, recordsTotal,          │
  │   recordsFiltered, data)       │
  │ ◄──────────────────────────    │
```

## Backend Files

### 1. Form Request (`app/Http/Requests/{Role}/{Entity}DataRequest.php`)
```php
/** @return array<string, array<int, mixed>|string> */
public function rules(): array {
    return [
        'filter_status' => ['nullable', 'string'],
        'filter_date_from' => ['nullable', 'date'],
        'filter_date_to' => ['nullable', 'date', 'after_or_equal:filter_date_from'],
    ];
}
```

### 2. Row Transformer (`app/DataTables/Transformers/{Entity}RowTransformer.php`)
```php
/** @return array<int, string> */
public static function transform(Model $model): array {
    return [
        '<td>'.e($model->name).'</td>',
        '<td><span class="badge bg-success">Active</span></td>',
        '<td><a href="'.route('admin.entity.show', $model).'">View</a></td>',
    ];
}
```

### 3. Controller Data Method
```php
private const ORDER_WHITELIST = [0 => 'name', 1 => 'created_at'];

public function data(EntityDataRequest $request): JsonResponse {
    $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
    $filters = array_filter([...], fn ($v) => $v !== null && $v !== '');
    $result = $this->service->listForDataTables($filters, $params);
    return $this->dataTablesResponse($params, $result['recordsTotal'], $result['recordsFiltered'], $result['rows'],
        static fn ($model): array => EntityRowTransformer::transform($model));
}
```

### 4. Route (POST, CSRF protected)
```php
Route::post('{entity}/data', [EntityController::class, 'data'])->name('entity.data');
```

## Frontend Files

### 5. JavaScript (`resources/js/pages/{entity}/index.js`)
```javascript
import { loadDataTablesLibrary, initServerSideDataTable } from "../../common/datatables";

document.addEventListener("DOMContentLoaded", async () => {
    await loadDataTablesLibrary();
    const table = document.querySelector("[data-datatable-url]");
    const dataUrl = table.dataset.datatableUrl;

    const dt = initServerSideDataTable("#entityTable", dataUrl, {
        order: [[0, "asc"]],
        columnDefs: [{ targets: -1, orderable: false }],
        getExtraData(d) {
            d.filter_status = document.getElementById("filter_status")?.value || "";
        },
    });

    document.getElementById("filterForm")?.addEventListener("change", () => dt.ajax.reload());
});
```

### 6. Blade View
```blade
<table id="entityTable" data-datatable-url="{{ $datatableUrl }}">
    <thead>...</thead>
    <tbody></tbody>  {{-- Empty — server fills this --}}
</table>
```

### 7. Register in `vite.config.js` and run `make assets-build`

## Checklist
- [ ] FormRequest validates all filter_* keys
- [ ] ORDER_WHITELIST only allows safe columns
- [ ] POST route with CSRF
- [ ] RowTransformer returns HTML strings (no inline HTML in controller)
- [ ] JS registered in vite.config.js
- [ ] `make assets-build` run after JS changes
- [ ] `make qa` passes
