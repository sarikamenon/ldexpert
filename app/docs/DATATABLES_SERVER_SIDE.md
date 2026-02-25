# DataTables Server-Side Processing

List tables in this project use **server-side processing**: paging, search, ordering, and per-page length are handled on the server. The frontend sends request parameters and receives a single page of data as JSON.

**Required for all new list tables**: Use `DataTablesRequest` + `DataTablesResponse` + an entity `XxxRowTransformer`; controllers must not build DataTables JSON or row HTML inline. New list pages must use `initServerSideDataTable()` with `getExtraData` for filters.

## Contract

### Request (POST)

- **DataTables params** (sent by the DataTables plugin):
  - `draw` – counter, must be returned in the response
  - `start` – offset (0-based)
  - `length` – page size (1–100)
  - `search[value]` – global search term
  - `order[0][column]` – column index to sort by
  - `order[0][dir]` – `asc` or `desc`
- **Filter params** (from the page filter form, names prefixed per resource, e.g. `filter_search`, `filter_status`, `filter_school_id`)
- **CSRF**: `_token` or `X-CSRF-TOKEN` (from layout meta tag)

### Response (JSON)

```json
{
  "draw": 1,
  "recordsTotal": 100,
  "recordsFiltered": 42,
  "data": [
    ["<cell1>", "<cell2>", ...],
    ...
  ]
}
```

- `draw` – same value as in the request
- `recordsTotal` – total count without the DataTables search (with filter form applied)
- `recordsFiltered` – total count with DataTables search (and filters) applied
- `data` – array of rows; each row is an array of HTML strings, one per column, in table order

### Security

- Use **POST** for the data endpoint (Laravel CSRF).
- Use a **column whitelist**: map DataTables column indices to allowed DB/query column names. See `App\Http\Support\DataTablesRequest`.

## Migrated entities (server-side DataTables)

| Entity | Route (POST data) | Controller | Row transformer |
|--------|--------------------|------------|-----------------|
| Students | `admin.students.data` | `StudentController::data()` | `StudentRowTransformer` |
| Schools | `admin.schools.data` | `SchoolController::data()` | `SchoolRowTransformer` |
| Therapists | `admin.therapists.data` | `TherapistController::data()` | `TherapistRowTransformer` |
| Services | `admin.services.data` | `ServiceController::data()` | `ServiceRowTransformer` |
| SSAs | `admin.ssas.data` | `SSAController::data()` | `SSARowTransformer` |
| Positions | `admin.positions.data` | `PositionController::data()` | `PositionRowTransformer` |
| Invoices | `admin.invoices.data` | `InvoiceController::data()` | `InvoiceRowTransformer` |
| Invoice payments | `admin.payments.invoices.data` | `InvoicePaymentsListController::data()` | `InvoicePaymentRowTransformer` |
| Expenses | `admin.expenses.data` | `ExpenseController::data()` | `ExpenseRowTransformer` |
| Therapist bill payments | `admin.payments.therapist-bills.data` | `TherapistBillPaymentsListController::data()` | `TherapistBillPaymentRowTransformer` |
| Therapist bills | `admin.billing.therapist-bills.data` | `TherapistBillController::data()` | `TherapistBillRowTransformer` |
| Expense categories | `admin.settings.expense-categories.data` | `ExpenseCategoryController::data()` | `ExpenseCategoryRowTransformer` |
| School contracts | `admin.contracts.schools.data` | `SchoolContractController::data()` | `SchoolContractRowTransformer` |
| Therapist contracts | `admin.contracts.therapists.data` | `TherapistContractController::data()` | `TherapistContractRowTransformer` |
| Student imports | `admin.students.imports.data` | `StudentController::importHistoryData()` | `StudentImportRowTransformer` |
| SSA imports | `admin.ssas.imports.data` | `SSAController::importHistoryData()` | `SSAImportRowTransformer` |
| Activity logs | `admin.activity-logs.data` | `ActivityLogController::data()` | `ActivityLogRowTransformer` |
| Session logs (admin) | `admin.session-logs.data` | `SessionLogController::data()` | `SessionLogRowTransformer` |
| Session logs (therapist) | `therapist.session-logs.data` | `SessionLogController::data()` (Therapist) | `TherapistSessionLogRowTransformer` |
| SSAs (therapist) | `therapist.ssas.data` | `SSAController::data()` (Therapist) | `TherapistSSARowTransformer` |
| Students (therapist) | `therapist.students.data` | `StudentController::data()` (Therapist) | `TherapistStudentRowTransformer` |
| Ledger accounts | `admin.ledger.accounts.data` | `LedgerAccountController::data()` | `LedgerAccountRowTransformer` |
| Ledger account transactions | `admin.ledger.accounts.transactions.data` | `LedgerAccountController::transactionsData()` | `LedgerEntryRowTransformer` |
| IRS report | `admin.finance.irs-report.data` | `IrsReportController::data()` | `IrsReportRowTransformer` |
| Student schedules (admin student detail) | `admin.students.schedules.data` | `StudentController::scheduleData()` | `ScheduleRowTransformer` |

## Reference implementation: Admin Students list

- **Route**: `admin.students.data` (`POST /admin/students/data`)
- **Controller**: `App\Http\Controllers\Admin\StudentController::data()`
- **Request**: `App\Http\Requests\Admin\Student\StudentDataRequest`
- **Helper**: `App\Http\Support\DataTablesRequest::fromRequest(Request $request, array $orderColumnWhitelist)`
- **Views**: `resources/views/admin/students/index.blade.php`, `resources/views/components/admin/students-list.blade.php` (empty tbody when `datatableUrl` is set)
- **JS**: `resources/js/pages/admin-students-index.js` uses `initServerSideDataTable()` from `resources/js/common/datatables.js`

When adding a new list table, replicate this pattern: add a `data` (or `indexData`) action, a `*DataRequest` Form Request for filter validation, a repository/service method that returns `recordsTotal`, `recordsFiltered`, and `rows`, and an `XxxRowTransformer` for row HTML. Build the response via `DataTablesResponse::dataTablesResponse()` or a custom JSON response. On the frontend, use `initServerSideDataTable(selector, url, { getExtraData(d) { ... } })` and pass filter form values in `getExtraData`.
