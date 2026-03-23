# Accounting & Financial Management PRD

## Purpose

Provide a complete financial picture of the business by tracking every dollar that comes in, goes out, and is owed. Today the system can create invoices for schools and bills for therapists, but there is no way to record payments, track expenses, or see overall financial health. This module closes that gap with a full double-entry accounting system that gives finance teams real-time visibility into revenue, costs, profit, and cash position.

## Business Goals

- **Know what schools owe and what has been collected.** When a school pays an invoice -- fully or partially -- the team can record the payment and immediately see the remaining balance.
- **Know what is owed to therapists and what has been paid.** Same principle: record payments against therapist bills, see outstanding balances at a glance.
- **Track all other business expenses.** Rent, software, travel, supplies, insurance -- anything that is not a therapist payment can be logged with a category, date, vendor, and amount.
- **See the financial health of the business.** Dashboards and reports answer questions like: How much revenue did we earn this month? What are our total expenses? What is our profit? How much cash do we have? Which invoices are overdue?
- **Maintain an audit trail.** Every financial event creates a journal entry with balanced debits and credits. Nothing can be silently changed -- corrections are made by voiding and re-entering.

## Personas

| Who | What They Care About |
|-----|---------------------|
| **Finance / Accounting** | Recording payments, categorizing expenses, closing periods, running reports, ensuring books balance. |
| **Owner / CEO** | High-level dashboard: revenue, expenses, profit, cash position, overdue receivables. |
| **Admin / Operations** | Recording day-to-day expenses, marking invoices as paid when checks arrive. |
| **Therapist** | Seeing payment history on their bills (read-only via therapist portal). |

## What Exists Today

| Area | Current State | Gap |
|------|--------------|-----|
| School Invoices | Create invoices, send as PDF, manually mark as "Paid" | No payment records, no partial payments, no payment history |
| Therapist Bills | Create bills, send as PDF, manually mark as "Paid" | No payment records, no partial payments, no payment history |
| Expenses | Nothing | No way to track rent, software, travel, or other costs |
| Reports | No financial reports | No P&L, no balance sheet, no aging, no cash flow visibility |
| Accounting | No ledger | No chart of accounts, no journal entries, no audit trail |

---

## Features

### 1. Chart of Accounts

The chart of accounts is the backbone of the accounting system. It is the master list of all financial categories used to classify every transaction.

**What the user sees:**
- A categorized list of accounts organized by type: Assets, Liabilities, Equity, Revenue, and Expenses.
- Each account has a code (e.g., 1000), a name (e.g., "Cash"), and a type.
- The system comes pre-loaded with a sensible default chart of accounts (see below). Admins can add, rename, or deactivate accounts but cannot delete system accounts.

**Default accounts provided:**

- **Assets (money the business has or is owed)**
  - 1000 -- Cash
  - 1010 -- Bank Account
  - 1200 -- Accounts Receivable (money schools owe us)

- **Liabilities (money the business owes)**
  - 2000 -- Accounts Payable (money we owe therapists)
  - 2100 -- Tax Payable

- **Equity (owner's stake)**
  - 3000 -- Owner's Equity
  - 3100 -- Retained Earnings

- **Revenue (money earned)**
  - 4000 -- Service Revenue (from school invoices)
  - 4010 -- Other Revenue

- **Expenses (money spent)**
  - 5000 -- Therapist Service Costs (therapist bill payments)
  - 5100 -- Office Supplies
  - 5200 -- Rent
  - 5300 -- Insurance
  - 5400 -- Travel
  - 5500 -- Software & Technology
  - 5900 -- Miscellaneous Expense

**Business rules:**
- Account codes must be unique.
- System accounts (pre-loaded) cannot be deleted, only renamed or deactivated.
- Deactivated accounts cannot be used in new transactions but remain visible in historical reports.
- Sub-accounts are supported (e.g., multiple bank accounts under "Bank Account").

---

### 2. Record Payments Received from Schools (Accounts Receivable)

When a school sends a check or bank transfer for an invoice, the admin records the payment.

**What the user sees:**
- On the invoice detail page, a "Payments" section shows all payments recorded against that invoice with date, amount, method, and reference number.
- A "Record Payment" button opens a form to log a new payment.
- The invoice shows: Total amount, Amount paid so far, and Balance remaining.
- Invoice status updates automatically:
  - **Sent** -- no payments recorded yet.
  - **Partially Paid** -- some payments recorded but balance remains.
  - **Paid** -- payments equal or exceed the invoice total.
- A separate "Payments Received" list page shows all payments across all invoices with filters by school, date range, and payment method.

**Payment form fields:**
- Payment date (when the money was received)
- Amount
- Payment method (Check, Bank Transfer / ACH, Wire, Cash, Credit Card, Other)
- Reference number (check number, transaction ID, etc.) -- optional
- Notes -- optional

**Business rules:**
- Payment amount must be greater than zero.
- A single invoice can have multiple payments (partial payment support).
- Payment date cannot be in a closed fiscal period.
- Each payment creates an automatic journal entry:
  - Debit: Cash/Bank (money in)
  - Credit: Accounts Receivable (school owes less)
- Who recorded the payment and when is always tracked (audit trail).

---

### 3. Record Payments Made to Therapists (Accounts Payable)

When the business pays a therapist for a bill, the admin records the payment.

**What the user sees:**
- On the therapist bill detail page, a "Payments" section shows all payments made against that bill.
- A "Record Payment" button opens a form to log the payment.
- The bill shows: Total due, Amount paid so far, and Balance remaining.
- Bill status updates automatically:
  - **Sent** -- no payments recorded yet.
  - **Partially Paid** -- some payments made but balance remains.
  - **Paid** -- payments equal or exceed the bill total.
- A separate "Payments Made" list page shows all outgoing payments with filters by therapist, date range, and method.
- **Therapist portal:** Therapists can see their payment history on each bill (read-only). They see when they were paid, how much, and via what method.

**Payment form fields:**
- Payment date (when the payment was sent)
- Amount
- Payment method (Check, Bank Transfer / ACH, Wire, Direct Deposit, Other)
- Reference number -- optional
- Notes -- optional

**Business rules:**
- Same rules as AR payments above, but the journal entry is:
  - Debit: Accounts Payable (we owe less)
  - Credit: Cash/Bank (money out)

---

### 4. Expense Tracking

Track all business expenses that are not therapist payments (those are handled via bills).

**What the user sees:**
- An "Expenses" page under Finance with a list of all recorded expenses.
- Each expense shows: date, category, vendor, amount, description.
- Filters: by category, date range, and vendor.
- A "Record Expense" button to add new expenses.

**Expense form fields:**
- Expense date
- Category (selected from expense-type accounts in the Chart of Accounts, e.g., Rent, Software, Travel)
- Vendor / Payee name -- optional
- Amount
- Payment method (Check, Bank Transfer, Cash, Credit Card, Other)
- Reference number -- optional
- Description / notes

**Business rules:**
- Each expense creates an automatic journal entry:
  - Debit: Expense account (e.g., Rent)
  - Credit: Cash/Bank (money out)
- Expenses can be edited or soft-deleted (never hard-deleted, for audit purposes).
- Admin-only: only users with admin role can create, edit, or delete expenses.

---

### 5. Journal Entries (for Accountants)

Most journal entries are created automatically by the system (when invoices are sent, payments are recorded, or expenses are logged). However, accountants sometimes need to make manual adjustments.

**What the user sees:**
- A "Journal Entries" page listing all entries with: entry number, date, description, source (Invoice, Payment, Expense, or Manual), status (Draft, Posted, Void).
- Each entry shows its lines: which accounts were debited and credited, and for how much.
- A "New Journal Entry" form for manual adjustments (e.g., year-end adjustments, corrections, write-offs).
- Ability to void a posted entry (which creates a reversing entry).

**Automatic journal entries are created when:**

| Event | Debit | Credit |
|-------|-------|--------|
| Invoice sent to school | Accounts Receivable | Service Revenue |
| Payment received from school | Cash / Bank | Accounts Receivable |
| Therapist bill sent | Therapist Service Costs | Accounts Payable |
| Payment made to therapist | Accounts Payable | Cash / Bank |
| Expense recorded | Expense Category | Cash / Bank |

**Business rules:**
- Every journal entry must balance: total debits = total credits. The system enforces this.
- Posted entries cannot be edited -- they can only be voided (which creates a reversing entry with an audit note).
- Draft entries have no financial effect until posted.
- Entries cannot be posted into a closed fiscal period.
- Every entry has a unique sequential number (e.g., JE-20260209-001).

---

### 6. Fiscal Periods

Fiscal periods let the finance team organize the books by month (or custom period) and "close" completed periods to prevent backdated changes.

**What the user sees:**
- A "Fiscal Periods" settings page showing periods (e.g., "January 2026", "February 2026") with status: Open or Closed.
- An admin can close a period, which locks all transactions in that date range.
- Periods can be reopened if needed (with appropriate permissions).

**Business rules:**
- No new transactions (payments, expenses, journal entries) can be posted into a closed period.
- Closing a period is reversible but logged for audit purposes.
- The system suggests periods based on calendar months but custom periods are allowed.

---

### 7. Financial Reports

These reports give the finance team and leadership the numbers they need to understand business performance.

**Report 1: General Ledger**
- Shows every transaction for a selected account (or all accounts) within a date range.
- Each line shows: date, description, source document, debit, credit, and running balance.
- Use case: "Show me every transaction in our bank account this month."

**Report 2: Trial Balance**
- A summary of all accounts showing total debits and total credits.
- The totals must always be equal (that is how you know the books are in balance).
- Use case: "Are our books balanced?" -- a quick health check.

**Report 3: Income Statement (Profit & Loss)**
- Shows all revenue earned and all expenses incurred for a selected period.
- Bottom line: Net Income = Revenue minus Expenses.
- Use case: "Did we make money this month? This quarter? This year?"

**Report 4: Balance Sheet**
- Shows the company's financial position at a point in time.
- Assets = Liabilities + Equity (this must always balance).
- Use case: "What do we own, what do we owe, and what is left for the owners?"

**Report 5: Accounts Receivable (AR) Aging**
- Lists all unpaid invoices grouped by how overdue they are: Current, 1-30 days, 31-60 days, 61-90 days, 90+ days.
- Shows totals per school and overall.
- Use case: "Which schools owe us money and how late are they?"

**Report 6: Accounts Payable (AP) Aging**
- Lists all unpaid therapist bills grouped by how overdue they are.
- Shows totals per therapist and overall.
- Use case: "Which therapists are we behind on paying?"

---

### 8. Financial Dashboard

A high-level overview on the admin dashboard giving leadership a snapshot of financial health.

**Widgets:**

- **Revenue This Month:** Total invoiced amount and total collected amount.
- **Expenses This Month:** Total therapist payments + total other expenses.
- **Net Income This Month:** Revenue collected minus all expenses.
- **Outstanding Receivables:** Total amount schools still owe (with count of overdue invoices).
- **Outstanding Payables:** Total amount owed to therapists (with count of overdue bills).
- **Cash Position:** Current cash/bank balance.

---

## Workflows

### Workflow 1: School Pays an Invoice

1. Admin receives a check or bank notification from a school.
2. Admin opens the invoice in the system.
3. Admin clicks "Record Payment" and enters the date, amount, method, and check number.
4. System saves the payment and automatically:
   - Updates the invoice balance (e.g., $5,000 total, $3,000 paid, $2,000 remaining).
   - If fully paid, changes invoice status to "Paid."
   - If partially paid, changes status to "Partially Paid."
   - Creates a journal entry (debit Cash, credit Accounts Receivable).
5. The payment appears in the invoice's payment history and in the "Payments Received" list.

### Workflow 2: Paying a Therapist

1. Admin prepares payment for a therapist (writes check, initiates bank transfer, etc.).
2. Admin opens the therapist bill in the system.
3. Admin clicks "Record Payment" and enters the date, amount, and method.
4. System saves the payment and automatically:
   - Updates the bill balance.
   - Changes bill status to "Paid" or "Partially Paid."
   - Creates a journal entry (debit Accounts Payable, credit Cash).
5. Therapist can see the payment on their portal under "My Bills."

### Workflow 3: Recording a Business Expense

1. Admin receives a vendor invoice or makes a business purchase (e.g., monthly software subscription).
2. Admin navigates to Finance > Expenses > Record Expense.
3. Admin fills in: date, category (Software & Technology), vendor (Zoom), amount ($50.00), method (Credit Card).
4. System saves the expense and creates a journal entry (debit Software & Technology, credit Cash).
5. The expense appears in the expenses list and is reflected in the P&L report.

### Workflow 4: Monthly Close Process

1. At the end of each month, finance reviews:
   - All invoices sent during the period.
   - All payments received and made.
   - All expenses recorded.
2. Finance runs the Trial Balance report to confirm debits equal credits.
3. Finance makes any adjusting journal entries (e.g., accruals, corrections).
4. Finance reviews the Income Statement and Balance Sheet.
5. Finance closes the fiscal period, which locks all transactions for that month.
6. The period status changes to "Closed" and no further changes can be made to that month's data.

### Workflow 5: Checking Overdue Payments

1. Admin or finance opens the AR Aging report.
2. Report shows: School A owes $12,000 (60 days overdue), School B owes $3,000 (current), etc.
3. Admin follows up with overdue schools.
4. Same process for AP Aging -- see which therapist payments are behind schedule.

---

## Navigation Structure

The Finance section in the admin sidebar will be organized as:

```
Finance
├── Dashboard (financial overview with widgets)
├── Invoices (existing -- now with payment recording)
├── Therapist Billing (existing -- now with payment recording)
├── Payments
│   ├── Payments Received (all AR payments across invoices)
│   └── Payments Made (all AP payments across therapist bills)
├── Expenses (new -- record and manage business expenses)
├── Accounting
│   ├── Chart of Accounts (view and manage account categories)
│   ├── Journal Entries (view all entries, create manual adjustments)
│   └── Fiscal Periods (open/close monthly periods)
└── Reports
    ├── General Ledger
    ├── Trial Balance
    ├── Income Statement (P&L)
    ├── Balance Sheet
    ├── AR Aging
    └── AP Aging
```

---

## Therapist Portal Impact

Therapists will see a new "Payment History" section on each of their bills showing:
- Payment date
- Amount paid
- Payment method
- Reference number

This is read-only. Therapists cannot record or modify payments.

---

## Delivery Phases

### Phase 1: Accounting Foundation (Estimated: 2-3 weeks)
**Business value:** Sets up the accounting backbone. No user-facing financial features yet, but required for everything that follows.
- Chart of Accounts with default accounts and admin management UI.
- Journal Entries system (create, post, void manual entries).
- Fiscal Periods (create, open, close).

### Phase 2: Payment Tracking (Estimated: 2-3 weeks)
**Business value:** Eliminates the biggest gap -- the team can finally track exactly what has been paid and what is outstanding.
- Record payments against school invoices (AR).
- Record payments against therapist bills (AP).
- Partial payment support with automatic status updates.
- Payment history visible on invoice and bill detail pages.
- "Payments Received" and "Payments Made" list pages.
- Therapist portal: payment visibility on their bills.
- All payments automatically create journal entries.

### Phase 3: Expense Tracking (Estimated: 1-2 weeks)
**Business value:** The team can track all business costs in one place instead of spreadsheets.
- Record expenses with category, vendor, amount, date.
- Expense list with filtering.
- Automatic journal entries for each expense.

### Phase 4: Connect Existing Invoices and Bills to Accounting (Estimated: 1 week)
**Business value:** Invoices and bills automatically flow into the accounting ledger. Existing historical data is brought in.
- When an invoice is sent, automatically create a revenue journal entry.
- When a therapist bill is sent, automatically create an expense journal entry.
- Backfill journal entries for all existing sent/paid invoices and bills.

### Phase 5: Financial Reports (Estimated: 2-3 weeks)
**Business value:** Finance team and leadership can answer critical business questions with real reports instead of manual calculations.
- General Ledger report.
- Trial Balance report.
- Income Statement (P&L) report.
- Balance Sheet report.
- AR Aging report.
- AP Aging report.
- All reports support date range filtering and export to CSV/PDF.

### Phase 6: Financial Dashboard (Estimated: 1 week)
**Business value:** At-a-glance financial health visible on the admin home page.
- Revenue, expenses, and net income widgets.
- Outstanding receivables and payables widgets.
- Cash position widget.

---

## Access Control

| Feature | Admin | Finance | Therapist | Parent / Student |
|---------|-------|---------|-----------|-----------------|
| Chart of Accounts | Full access | View | No access | No access |
| Record Payments (AR/AP) | Full access | Full access | No access | No access |
| View Payment History | Full access | Full access | Own bills only | No access |
| Record Expenses | Full access | Full access | No access | No access |
| Journal Entries | Full access | Full access | No access | No access |
| Fiscal Periods | Full access | Full access | No access | No access |
| Financial Reports | Full access | Full access | No access | No access |
| Financial Dashboard | Full access | Full access | No access | No access |

*Note: If a "Finance" role does not exist yet, these permissions apply to Admin users. A dedicated Finance role can be introduced later.*

---

## Success Metrics

- **Time to record a payment:** Under 30 seconds (open invoice, click Record Payment, fill 3-4 fields, save).
- **Outstanding balance accuracy:** Invoice and bill balances always match the sum of recorded payments.
- **Books in balance:** Trial Balance always shows equal debits and credits (enforced by system).
- **Report generation time:** All reports load within 5 seconds for up to 12 months of data.
- **Adoption:** 100% of payments recorded in the system within 30 days of Phase 2 launch.

---

## Risks & Open Questions

| Risk / Question | Impact | Recommendation |
|----------------|--------|---------------|
| Tax handling: Are therapy services tax-exempt? | Affects whether tax accounts and calculations are needed. | Clarify with finance team. The system supports tax accounts but does not auto-calculate tax rates. |
| Multi-currency: Will the business ever operate in multiple currencies? | Significant complexity if yes. | Assume single currency (USD) for now. Can be extended later. |
| External accounting software: Does the team also use QuickBooks, Xero, or similar? | Determines whether export/sync features are needed. | Build CSV/PDF export on reports first. API integration can be a future phase. |
| Bank reconciliation: Does the team need to reconcile bank statements? | Adds a significant feature (bank feeds, matching). | Out of scope for initial release. Can be added as a future phase. |
| Partial therapist payments: Can a therapist be paid for part of a bill? | Affects AP workflow. | Yes, supported. The system tracks partial payments and remaining balance. |
| Fiscal year: Does the business use calendar year (Jan-Dec) or a different fiscal year? | Affects default fiscal period setup. | Default to calendar year. Configurable in Fiscal Periods settings. |
