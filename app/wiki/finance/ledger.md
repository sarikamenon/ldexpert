NOVA · Ledger Accounts PRD
Version 1.0 · Last Updated: 26 Mar 2026

1. OVERVIEW
   The Ledger Accounts module provides a double-entry accounting view of financial transactions. It aggregates invoice and therapist bill data into account-level summaries with drill-down to individual transactions.

2. FUNCTIONAL SCOPE

   2.1 Accounts List
   Route: GET /admin/ledger/accounts
   Dual-mode view:
   - School accounts: shows invoiced, paid, outstanding amounts per school
   - Therapist accounts: shows billed, paid, outstanding amounts per therapist
   - Toggle between modes via filter
   - Server-side DataTable with different column whitelists per mode

   2.2 Account Detail
   Route: GET /admin/ledger/accounts/{type}/{id}
   - Shows all transactions for a specific school or therapist
   - Transaction types: invoice_generated, payment_received, bill_generated, payment_made, expense (TransactionType enum)
   - Server-side DataTable for transactions

   2.3 Export
   Route: GET /admin/ledger/accounts/export
   - CSV export of accounts data with current filters

3. DATA MODEL
   Table: ledger_entries — `id`, `ledgerable_type` (polymorphic), `ledgerable_id`, `reference_type` (polymorphic), `reference_id`, `transaction_type` (TransactionType enum), `amount` (decimal), `recorded_by_id` (admin user), `description`, `transaction_date`, timestamps.
   Polymorphic targets: Invoice, TherapistBill, Expense linked to School or User.

4. ROUTES
   - GET /admin/ledger/accounts — accounts list
   - POST /admin/ledger/accounts/data — accounts DataTable endpoint
   - POST /admin/ledger/accounts/transactions/data — transactions DataTable endpoint
   - GET /admin/ledger/accounts/export — CSV export
   - GET /admin/ledger/accounts/{type}/{id} — account detail (type: schools or therapists)

5. TECHNICAL IMPLEMENTATION
   Controller: `App\Http\Controllers\Admin\LedgerAccountController`
   Enums: `TransactionType`

6. NAVIGATION
   Appears under "Finance" top-level admin menu as "Accounts Ledger" entry.
