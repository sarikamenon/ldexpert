# Accounting System Design

## Purpose

Extend the current Finance module to support **full accounting visibility**:

1. **Payments received from schools** (AR) — when and how much was paid against each invoice.
2. **Payments paid to therapists** (AP) — when and how much was paid against each therapist bill.
3. **Other expenses** — rent, software, travel, supplies, etc., with categories and dates.

This gives the business a single place to see cash in, cash out, and operating expenses without replacing your external accounting tool (e.g. QuickBooks). The system acts as **operational accounting** (what happened in the product) and can later sync or export to the general ledger.

---

## Current State

| Area | What Exists | What’s Missing |
|------|-------------|----------------|
| **Invoices (AR)** | Create/send invoices to schools; status draft/sent/paid | No payment records; “paid” is manual status only |
| **Therapist Bills (AP)** | Create/send bills to therapists; status draft/sent/paid | No payment records; “paid” is manual status only |
| **Expenses** | — | No expense tracking |

---

## Recommended Approach: Payment Records + Expenses

### 1. Payments (AR and AP)

- Add **payment** tables that link to invoices and therapist bills.
- Each payment stores: date, amount, method, reference (check/ACH/txn id), optional notes.
- **Invoice “paid”** = sum of payments ≥ invoice total (or explicit “mark paid” that creates a single payment record).
- **Therapist bill “paid”** = same idea: sum of payments ≥ bill total (or one “mark paid” payment).
- Keep existing `Invoice` and `TherapistBill` status; derive or update `paid` when payments are recorded so UI and reports stay consistent.

This gives you:

- History of what was received from each school and when.
- History of what was paid to each therapist and when.
- Support for partial payments and overpayments (if needed later).
- Clear audit trail (who recorded the payment, when).

### 2. Other Expenses

- Add an **expenses** feature: one table (e.g. `expenses`) with category, date, amount, vendor/payee, description, optional attachment/reference.
- Optionally an **expense_categories** table (or enum) for reporting: e.g. Rent, Software, Travel, Supplies, Payroll (non-therapist), Other.
- Admin-only: list, create, edit, soft-delete expenses; filter by category and date range.

### 3. No Double-Entry in App (Initially)

- Do **not** implement full double-entry ledger (debits/credits, chart of accounts) inside the app unless you explicitly need it.
- Keep the model simple: **invoices + invoice_payments**, **therapist_bills + therapist_bill_payments**, **expenses**.
- You can later add exports (CSV/Excel) or API for syncing to QuickBooks/Xero; they can do the formal GL posting.

---

## Data Model (Proposed)

### New Tables

#### `invoice_payments` (AR — money in from schools)

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint PK | |
| `invoice_id` | FK → invoices | |
| `paid_at` | date | When payment was received |
| `amount` | decimal(10,2) | Amount received |
| `method` | string/enum | e.g. check, ach, wire, other |
| `reference` | string nullable | Check number, ACH id, etc. |
| `notes` | text nullable | |
| `recorded_by_id` | FK → users nullable | Who entered it |
| `created_at`, `updated_at` | | |
| `deleted_at` | | soft deletes |

- **Rules:** `amount` > 0; optional: sum of payments per invoice ≤ invoice total (or allow overpayment and track credit).

#### `therapist_bill_payments` (AP — money out to therapists)

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint PK | |
| `therapist_bill_id` | FK → therapist_bills | |
| `paid_at` | date | When payment was made |
| `amount` | decimal(10,2) | Amount paid |
| `method` | string/enum | e.g. check, ach, wire, other |
| `reference` | string nullable | Check number, ACH id, etc. |
| `notes` | text nullable | |
| `recorded_by_id` | FK → users nullable | Who entered it |
| `created_at`, `updated_at` | |
| `deleted_at` | | soft deletes |

- **Rules:** `amount` > 0; same idea as invoice_payments for totals.

#### `expense_categories` (optional but recommended)

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint PK | |
| `name` | string | e.g. Rent, Software, Travel |
| `slug` | string unique | For filters/API |
| `is_active` | boolean | |
| `created_at`, `updated_at` | | |

- Seed a few default categories; admin can manage list.

#### `expenses` (other expenses)

| Column | Type | Notes |
|--------|------|--------|
| `id` | bigint PK | |
| `expense_category_id` | FK → expense_categories | |
| `expense_date` | date | When expense occurred |
| `amount` | decimal(10,2) | Always positive (expense = outflow) |
| `vendor_payee` | string nullable | Who was paid |
| `description` | text nullable | What it was for |
| `reference` | string nullable | Receipt number, etc. |
| `created_by_id` | FK → users nullable | |
| `created_at`, `updated_at` | |
| `deleted_at` | | soft deletes |

---

## Status and Totals

- **Invoice**
  - Keep `invoices.status` (draft, sent, paid).
  - When recording an **invoice_payment**: recalc `total_paid = sum(invoice_payments.amount)` for that invoice. If `total_paid >= invoice.total`, set `invoices.status = paid` (or offer a “Mark as paid” action that creates one payment and sets status).
  - Optionally store `invoices.paid_at` (date of first full payment or last payment that closed the balance).

- **TherapistBill**
  - Same pattern: `therapist_bills.status` (draft, sent, paid).
  - When recording a **therapist_bill_payment**: if sum of payments ≥ `total_due`, set status to paid and optionally set `therapist_bills.paid_at`.

- **Expenses**
  - No status needed; list/filter by date and category.

---

## UI and Navigation (Admin Finance)

Under the existing **Finance** menu, add:

1. **Invoices** (existing)  
   - On invoice show: list of **Payments received** (from `invoice_payments`) and **Record payment** button → form (date, amount, method, reference, notes). After save, recalc and optionally set invoice to paid.

2. **Therapist Billing** (existing)  
   - On therapist bill show: list of **Payments paid** (from `therapist_bill_payments`) and **Record payment** button → same pattern.

3. **Expenses** (new)  
   - **List:** table of expenses (date, category, vendor, amount, description) with filters (category, date range).  
   - **Create/Edit:** form with category, date, amount, vendor, description, reference.  
   - Optional: **Expense categories** settings page (name, active).

4. **Accounting / Finance dashboard** (optional)  
   - Simple summary: AR total (invoices sent not paid, or overdue), AP total (bills sent not paid), total expenses in period, maybe cash-style summary (received this month, paid to therapists this month, expenses this month). No need for full P&L in v1.

---

## Implementation Order

1. **Phase 1 – AR payments**
   - Migration `invoice_payments`.
   - Model `InvoicePayment`, relationship `Invoice::invoicePayments()`.
   - Enum `PaymentMethod` (check, ach, wire, other).
   - DTO, Form Request, Repository, Service for “record payment”.
   - On invoice show: payments list + “Record payment” form; after save, update `invoices.status` (and optional `paid_at`) when fully paid.

2. **Phase 2 – AP payments**
   - Migration `therapist_bill_payments`.
   - Model `TherapistBillPayment`, relationship `TherapistBill::therapistBillPayments()`.
   - Reuse or share `PaymentMethod` enum.
   - Same pattern: DTO, Form Request, Repository, Service; on bill show: payments list + “Record payment”; update bill status when fully paid.

3. **Phase 3 – Expenses**
   - Migrations: `expense_categories`, `expenses`.
   - Models: `ExpenseCategory`, `Expense`.
   - Seed default categories.
   - CRUD: list (with filters), create, edit, soft delete; policy (admin only).

4. **Phase 4 – Optional**
   - Finance dashboard (summary widgets).
   - Export: AR/AP/expenses to CSV/Excel for accounting import.
   - If needed later: “Mark invoice/bill as paid” without entering a payment (creates a single payment record for the full amount and sets paid_at to today).

---

## Conventions (Match Existing Project)

- **Laravel monolith:** Blade + Form Requests + jQuery AJAX where needed; no public API unless you add it later for ERP sync.
- **Layers:** Controllers → Services → Repositories; DTOs for input; Form Requests for validation.
- **Policies:** e.g. `InvoicePaymentPolicy`, `TherapistBillPaymentPolicy`, `ExpensePolicy` (admin-only for payments and expenses).
- **Soft deletes** on `invoice_payments`, `therapist_bill_payments`, `expenses`.
- **Help text and design system:** All new forms use existing UI components and form help text standards.
- **Tests:** Feature tests for record-payment and expense CRUD; unit tests for DTOs/Services.

---

## Summary

| Need | Solution |
|------|----------|
| Payments received from school | `invoice_payments` + “Record payment” on invoice show; drive invoice “paid” from payments |
| Payments paid to therapist | `therapist_bill_payments` + “Record payment” on bill show; drive bill “paid” from payments |
| Other expenses | `expense_categories` + `expenses`; admin CRUD and filters |
| “Entire accounting system” | One Finance area: Invoices + AR payments, Therapist bills + AP payments, Expenses; optional dashboard and exports |

This gives a clear, auditable picture of cash in, cash out, and expenses without building a full general ledger inside the app. You can later add exports or API for your accounting software.
