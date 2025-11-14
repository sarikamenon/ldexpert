# Bill Therapists (Provider Payables) PRD

## Purpose
Calculate and disburse payments owed to therapists based on delivered sessions, contract rates, and adjustments. Provide visibility into payable balances, automate approvals, and integrate with payroll/AP systems.

## Personas
- **Accounts Payable (AP)** — reviews payables, approves payouts, exports to payroll.
- **Therapists** — view accrued pay, submitted sessions, and payment history.
- **Program Admin** — verifies that therapist payouts align with SSA assignments and attendance.

## Current Implementation
- No billing tables or workflows. Therapists cannot see payout information; AP operates outside NOVA.

## Planned Scope
- Mirror AR data pipeline for provider side: sessions feed into `therapist_bills` using provider rates from `therapist_service_rates` (or defaults).
- Support per-therapist adjustments (bonuses, deductions) and status workflow (draft, ready-for-approval, approved, exported, paid).
- Provide statements accessible from therapist portal, and allow therapists to acknowledge payments.

## Domain Model
### Tables
| Table | Fields |
| --- | --- |
| `sessions` | shared with invoicing; add `is_billable_provider` flag and `provider_rate_applied`.
| `therapist_bills` | `id`, `therapist_id`, `period_start`, `period_end`, `status`, `subtotal`, `adjustments_total`, `total_due`, `due_on`, `export_batch_id`, timestamps.
| `therapist_bill_lines` | `id`, `therapist_bill_id`, `session_id`, `service_id`, `quantity`, `unit`, `rate`, `amount`, `ssa_id`, `notes`.
| `therapist_adjustments` | manual debit/credit with reason (travel, penalty, bonus).
| `therapist_payments` | `id`, `therapist_id`, `bill_id`, `paid_on`, `amount`, `method`, `reference`, `status` (initiated, sent, failed, completed).

### Rules
- Sessions must be approved and linked to an active therapist assignment before billing.
- Provider rate priority: SSA assignment override → `therapist_service_rates` → `services.default_provider_rate`.
- Negative adjustments require approver comment; system logs user + timestamp.

## API & Routes
### Admin Web
- `GET /admin/billing/therapists` — pipeline view grouped by period.
- `GET /admin/billing/therapists/{therapist}` — detail of pending bills.
- `POST /admin/billing/generate` — select period + therapists to create draft bills.
- `POST /admin/billing/{bill}/approve` — locks bill, notifies AP.
- `POST /admin/billing/{bill}/export` — marks as sent to payroll (stores export artifact).
- `POST /admin/billing/{bill}/record-payment` — capture payment info.

### Therapist Portal
- `GET /therapist/billing` — list of bills with status + amounts.
- `GET /therapist/billing/{bill}` — line-level detail, ability to dispute.
- `POST /therapist/billing/{bill}/disputes` — start dispute referencing session(s).

### API v1
- `GET /api/v1/therapist-bills` — for payroll integrations.
- `POST /api/v1/therapist-bills/{id}/payment` — inbound webhook from payroll provider updating status.

## Workflows
1. **Cycle Generation**
   1. Scheduler aggregates approved sessions per therapist for the period (weekly/biweekly).
   2. System applies rates, creates draft bills, and notifies AP + therapists (read-only until approved).
   3. AP reviews adjustments, approves, and exports.
2. **Dispute Handling**
   1. Therapist files dispute citing session(s).
   2. Admin reviews, optionally adjusts session/bill; dispute status tracked per line.
3. **Payment Recording**
   1. After payroll run, import payment file to mark bills as paid.
   2. Provide download of pay stub (PDF) for therapists.

## Integrations & Dependencies
- Shares `sessions` data with invoicing; ensure no double counting when session spans multiple services.
- Requires `therapist_service_rates` from therapist management module.
- Payment integration (ACH provider, payroll) needs secure credential storage and webhook endpoints.

## Metrics
- Outstanding AP balance by therapist.
- Average days from service delivery to payment.
- Volume of disputes per period.

## Risks & Open Questions
- Need clarity on contractor vs. employee classification (affects tax forms, deductions).
- Determine if therapists can split rates by school or SSA (if so, extend rate table).
- Understand regulatory requirements for pay stub content per state.
