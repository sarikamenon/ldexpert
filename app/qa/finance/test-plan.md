# Finance Test Plan

> Source: app/wiki/finance/*.md (+ route inventory for features not yet documented)
> Generated: 2026-06-12 (auto-derived from app/qa/LD-Expert-QA.xlsx - Finance sheet)
> Coverage: 13 features, 54 test cases (35 Valid / 11 Invalid / 8 Edge)

## Scope

Finance is a module under the Admin role (no separate role). Covers invoicing, invoice/bill payments, the ledger, expenses, pay-stub report, and billing automation (schedules/settings/entity config).

---

## Feature Areas

| Area | Priority | Test Cases | Count | Wiki Reference |
|------|----------|-----------|-------|----------------|
| Access Control | P1 | TC-F001, TC-F002 | 2 | - |
| Finance Dashboard | P2 | TC-F003, TC-F004 | 2 | wiki/finance/accounting.md |
| Invoices | P1 | TC-F005, TC-F006, TC-F007, TC-F008, TC-F009, TC-F010, TC-F011, TC-F012, TC-F013, TC-F014, TC-F015 | 11 | wiki/finance/invoicing.md |
| Invoice Payments | P1 | TC-F016, TC-F017, TC-F018, TC-F019, TC-F020 | 5 | wiki/finance/payments.md |
| Therapist Billing | P1 | TC-F021, TC-F022, TC-F023, TC-F024, TC-F025 | 5 | wiki/finance/billing.md |
| Bill Payments | P1 | TC-F026, TC-F027, TC-F028 | 3 | wiki/finance/payments.md |
| Ledger | P2 | TC-F029, TC-F030, TC-F031, TC-F032, TC-F033, TC-F034, TC-F035 | 7 | wiki/finance/ledger.md |
| Expenses | P2 | TC-F036, TC-F037, TC-F038, TC-F039 | 4 | wiki/finance/expenses.md |
| Expense Categories | P3 | TC-F040, TC-F041 | 2 | wiki/finance/expenses.md |
| Pay Stub Report | P2 | TC-F042, TC-F043, TC-F044 | 3 | wiki/finance/pay-stub-report.md |
| Billing Schedules | P1 | TC-F045, TC-F046, TC-F047, TC-F048, TC-F049, TC-F050 | 6 | wiki/finance/billing-automation.md |
| Billing Settings | P2 | TC-F051, TC-F052 | 2 | wiki/finance/billing-automation.md |
| Entity Billing Config | P2 | TC-F053, TC-F054 | 2 | wiki/finance/billing-automation.md |

---

## Test Cases by Feature

### Access Control

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F002 | Valid | Admin can access finance dashboard | Finance dashboard renders |
| TC-F001 | Invalid | Therapist blocked from finance routes | Redirected (not 200) - finance is admin-only |

### Finance Dashboard

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F003 | Valid | View finance dashboard metrics | Dashboard renders AR/AP summary widgets |
| TC-F004 | Edge | Finance dashboard with no data | Renders zero/empty states |

### Invoices

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F005 | Valid | Generate invoice from approved session logs | Draft invoice created, line per log, total from school_invoice_amount |
| TC-F008 | Valid | View invoice detail with line items | Invoice detail shows session-log lines and totals |
| TC-F009 | Valid | Download invoice PDF | PDF invoice downloads with school/company snapshot |
| TC-F010 | Valid | Send draft invoice via email | Status DRAFT -> SENT, sent_at recorded, email with PDF sent |
| TC-F013 | Valid | Resend invoice email | Email re-sent to school contact |
| TC-F014 | Valid | Attach additional sessions to invoice | Sessions attached, invoice total recalculated |
| TC-F006 | Invalid | Generate invoice with logs from different schools | Blocked - all logs must belong to the same school |
| TC-F007 | Invalid | Generate invoice with no approved logs | No selectable logs / warning - only approved logs billable |
| TC-F011 | Invalid | Send invoice that is already sent | Send action unavailable - only DRAFT can be sent |
| TC-F012 | Invalid | Send invoice when school has no invoice email | Friendly error - cannot email without invoice address |
| TC-F015 | Edge | Invoice list empty state | Empty state shown |

### Invoice Payments

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F016 | Valid | Record payment on a sent invoice | Payment recorded, ledger_entries row created |
| TC-F019 | Valid | Delete an invoice payment | Payment removed, ledger chain recomputed |
| TC-F020 | Valid | View invoice payments list | Payments list renders |
| TC-F017 | Invalid | Record payment on a draft invoice | Blocked - record payment only available on SENT |
| TC-F018 | Edge | Record overpayment on invoice | Overpayment handled gracefully (per business rule), ledger consistent |

### Therapist Billing

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F021 | Valid | Create therapist bill from approved logs | Draft therapist bill created with billable amounts |
| TC-F022 | Valid | Send therapist bill | Status DRAFT -> SENT |
| TC-F023 | Valid | Download therapist bill PDF | Bill PDF downloads |
| TC-F024 | Valid | Attach sessions to therapist bill | Sessions attached, total recalculated |
| TC-F025 | Edge | Delete a draft therapist bill | Bill removed |

### Bill Payments

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F026 | Valid | Record payment on a sent therapist bill | Payment recorded, ledger_entries row created, ledger:verify passes |
| TC-F028 | Valid | Delete a bill payment | Payment removed, ledger chain recomputed |
| TC-F027 | Invalid | Record payment on a draft bill | Blocked - only SENT bills accept payment |

### Ledger

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F029 | Valid | View accounts ledger list | Accounts ledger renders with balances |
| TC-F030 | Valid | View account transactions | Transactions listed with balance_after |
| TC-F031 | Valid | Create a ledger adjustment (credit note) | Adjustment entry created via LedgerService, chain recomputed |
| TC-F033 | Valid | Edit a credit-note adjustment | Adjustment updated, balance_after recomputed from that point |
| TC-F035 | Valid | Export ledger transactions | Ledger export downloads |
| TC-F032 | Invalid | Edit a non-editable ledger entry type | Blocked - only credit_note/refund editable from ledger |
| TC-F034 | Edge | Delete a credit-note adjustment | Entry removed, chain recomputed, ledger:verify passes |

### Expenses

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F036 | Valid | Record an expense | Expense recorded and listed |
| TC-F038 | Valid | Edit an expense | Expense updated |
| TC-F037 | Invalid | Record expense with missing amount | Validation error, not saved |
| TC-F039 | Edge | Delete an expense | Expense removed |

### Expense Categories

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F040 | Valid | Create an expense category | Category created and listed |
| TC-F041 | Valid | Toggle expense category status | Category status updated |

### Pay Stub Report

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F042 | Valid | View pay stub report | Report renders therapist pay rows |
| TC-F043 | Valid | Download pay stub report | Report downloads |
| TC-F044 | Edge | Pay stub report with no data | Empty state shown |

### Billing Schedules

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F045 | Valid | Create a billing schedule | Schedule created, next_run_at calculated |
| TC-F047 | Valid | Toggle schedule active status | Active flag flips without deleting |
| TC-F048 | Valid | Run a schedule manually (Run Now) | Run executes, draft invoice/bill generated, run logged |
| TC-F050 | Valid | View schedule run history | Run history lists status, period, generated docs |
| TC-F046 | Invalid | Create schedule with missing frequency | Validation error, not saved |
| TC-F049 | Edge | Run schedule with no sessions found | Run status skipped_no_sessions, no document created |

### Billing Settings

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F051 | Valid | Update global billing settings | Global defaults saved |
| TC-F052 | Invalid | Save billing settings with invalid value | Validation error, not saved |

### Entity Billing Config

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-F053 | Valid | Set per-entity billing override | Override saved, takes precedence over global defaults |
| TC-F054 | Valid | Remove entity billing override | Override removed, falls back to global defaults |
