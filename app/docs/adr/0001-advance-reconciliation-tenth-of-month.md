---
status: accepted
---

# Advance billing reconciles late-approved sessions on the 10th via a stateful `advance_reconciliations` table

Advance (prepaid) school/family invoices charge a month up front and reconcile it on the **next** monthly run by comparing the charged `ADVANCE_SCHEDULED` lines against approved session logs *at that instant*. A session approved **after** that run (e.g. a May session approved on June 3, after the June 1 run) is never revisited, because the following month's run only ever looks at the immediately prior invoice — so the late approval is a permanent miss. We add a dedicated `billing:reconcile-advance` command that runs on the **10th of each month**, reconciles the **immediately prior calendar month only**, and records each reconciled `(billing_schedule, period)` in a new `advance_reconciliations` table so a period is settled at most once.

## Considered options

- **Re-run the existing stateless adjustment logic for the prior month on the 10th.** Rejected: `buildAdjustmentLines()` recomputes the *entire* delta between advance charges and currently-approved logs every time. Re-running it would re-credit/re-charge sessions the 1st-of-month run already adjusted — double-counting.
- **Detect "already reconciled" purely from `invoice_line_items` (no new table).** This carries the *arithmetic* (see Consequences) but cannot serve as the idempotency guard for **credit-only** late adjustments: when the net is a credit (we owe the family), the correction is a ledger credit note with **no invoice** to stamp `invoice_id` onto, so nothing on the session/period records "already credited." A second pass would credit it again.
- **Widen the reconcile window to catch-up multiple prior months.** Rejected as unnecessary and risky: with generation on the 1st and reconcile on the 10th, every month is reconciled exactly twice (1st of next month, then 10th) and then sealed — looking 2+ months back would re-touch sealed periods.

## Consequences

- **Hard scope:** the 10th-of-month command operates on **prior-calendar-month** `session_date` only and must never read current-month logs (the current month is still its open advance period — charged on the 1st, adjusted on the 1st of next month). Current-month logs here would double-adjust.
- **`advance_reconciliations` is an idempotency guard, not an arithmetic input.** The late delta is computed **per session/schedule** as `should_bill(session) − already_billed(session)`, where `already_billed` is the period-keyed sum of that schedule's prior-month `invoice_line_items.total` across the school's invoices (original advance charge + any `ADJUST_*` already posted). The table only answers "has this `(schedule, period)` been settled?" — valid precisely because the hard scope reconciles each month exactly twice.
- **Positive delta → DRAFT invoice** (admin reviews & sends, itemized catch-up lines). **Negative delta → credit note** via `LedgerService` (auto-posts; `recorded_at` = the run date / the 10th, so it is not backdated and needs no chain recompute).
- If the twice-only invariant is ever relaxed, the per-period `advance_reconciliations.net_amount` must also be subtracted from the late delta to stay idempotent.

See `_local_docs/billing-invoice-settings-generation-updates-plan.md` §8 for the full mechanics and worked example, and [LEDGER_SYSTEM.md](../LEDGER_SYSTEM.md) for the ledger-write rules the credit note obeys.
