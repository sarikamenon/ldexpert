# Admin Menu Specification

Last Updated: 26 Mar 2026

## Purpose

Define the top-level navigation for NOVA administrators. The menu is config-driven via `config/navigation.php` and covers all implemented modules.

## Current Menu Structure (from config/navigation.php)

| Top-Level | Children | Routes |
|-----------|----------|--------|
| Dashboard | — | `/admin/dashboard` |
| Schools | List, Create, Contracts | `/admin/schools`, `/admin/schools/create`, `/admin/contracts/schools` |
| Therapists | List, Create, Contracts | `/admin/therapists`, `/admin/therapists/create`, `/admin/contracts/therapists` |
| Students | Student List, Student Create, Student Import, Documents, Lead List, Create Lead, SSA List, SSA Create, SSA Import | `/admin/students`, `/admin/students/create`, `/admin/students/import`, `/admin/student-documents`, `/admin/leads`, `/admin/leads/create`, `/admin/ssas`, `/admin/ssas/create`, `/admin/ssas/import` |
| Session Logs | Submitted, Sent back, Approved, Cancelled (status filters), Import Session Logs, Schedule Calendar | `/admin/session-logs?filter_status=*`, `/admin/session-logs/import`, `/admin/schedule-calendar` |
| Finance | Dashboard, Accounts Ledger, Invoices, Invoice Payments, Therapist Billing, Bill Payments, Expenses, Pay Stub Report, Billing Schedules, Billing Settings | `/admin/finance/dashboard`, `/admin/ledger/accounts`, `/admin/invoices`, `/admin/payments/invoices`, `/admin/billing/therapist-bills`, `/admin/payments/therapist-bills`, `/admin/expenses`, `/admin/finance/pay-stub-report`, `/admin/billing/schedules`, `/admin/billing/settings` |
| Reports | Utilization & Compliance, Caseload & Assignment, Expirations & Pipeline, Analytics | `/admin/reports/ssa/utilization`, `/admin/reports/ssa/caseload`, `/admin/reports/ssa/expirations`, `/admin/analytics` |
| Settings | Services, Expense Categories, Positions, Service Aliases | `/admin/services`, `/admin/settings/expense-categories`, `/admin/positions`, `/admin/service-aliases` |

## Implementation Notes

1. **Navigation Config** — Menu structure is defined in `config/navigation.php` and rendered by the admin layout Blade component.
2. **Role Protection** — All routes use `auth` + `role:admin` middleware.
3. **Active State** — Navigation highlights the current section based on route name matching.
4. **Notifications** — In-app notification center accessible at `/admin/notifications` with unread badge count.
5. **Testing** — Feature tests verify each menu entry renders correctly and unauthorized roles get 403.
