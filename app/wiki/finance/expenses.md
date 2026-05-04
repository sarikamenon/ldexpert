NOVA · Expenses PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Expenses module tracks operating expenses for the organization. Admins can create, edit, view, and delete expenses categorized by expense type, with filtering and total amount summaries.

2. FUNCTIONAL SCOPE

   2.1 Create Expense
   - Date, amount, category (from ExpenseCategory), description, vendor/payee
   - Category dropdown populated from active expense categories

   2.2 List Expenses
   Server-side DataTable with:
   - Columns: Date, Category, Description, Vendor/Payee, Amount, Actions
   - Filters: date range, category
   - Total amount summary displayed for current filter set
   - DataTable endpoint returns `totalAmount` alongside rows

   2.3 View Expense
   Detail page showing all expense fields.

   2.4 Edit Expense
   All create fields editable.

   2.5 Delete Expense
   Soft delete with confirmation.

3. DATA MODEL
   Table: expenses — `id`, `date`, `amount` (decimal), `expense_category_id`, `description`, `vendor`, timestamps, `deleted_at`.
   Table: expense_categories — `id`, `name`, `description`, `is_active`, timestamps, `deleted_at`.

4. ROUTES
   - GET /admin/expenses — list
   - POST /admin/expenses/data — DataTable endpoint (returns totalAmount)
   - GET /admin/expenses/create — create form
   - POST /admin/expenses — store
   - GET /admin/expenses/{expense} — show
   - GET /admin/expenses/{expense}/edit — edit form
   - PUT /admin/expenses/{expense} — update
   - DELETE /admin/expenses/{expense} — soft delete

5. TECHNICAL IMPLEMENTATION
   Controller: `App\Http\Controllers\Admin\ExpenseController`
   Service: `ExpenseService`
   Policy: `ExpensePolicy`
   Related: Expense categories managed under Settings (see admin/settings.md)
