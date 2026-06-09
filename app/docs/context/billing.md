# Context — Billing & Invoicing

Glossary of the domain language used across the billing/invoice subsystem. Terms are
canonical; if code or a plan uses a word differently, that is a smell to resolve, not a
synonym to accept.

## Billing Schedule

A `billing_schedules` row. Configuration that drives automatic generation of either a
**Therapist Bill** or a **School Invoice**, distinguished by `schedule_type`
(`therapist_bill` | `school_invoice`). Answers, on each run: what work window are we
billing (Frequency), on what date do we cut the document (Generation Timing), and — for
school invoices only — are we billing work already delivered or work about to happen
(Billing Mode).

## Billing Mode

`billing_mode` on a School Invoice schedule. **Standard** (postpaid): invoice cut *after*
the period closes, from delivered session logs. **Advance** (prepaid): invoice cut
*before* the period begins, from `Schedule` rows in the upcoming period, with a later
adjustment line reconciling prepaid-vs-delivered. Does **not** apply to Therapist Bills.

## Therapist Bill vs School Invoice

Two different documents. A **Therapist Bill** (`therapist_bills`) is what we owe a
therapist for delivered work; always postpaid; no Billing Mode. A **School Invoice**
(`invoices`) is what a school/family owes us; can be Standard or Advance. They share the
schedule mechanics but are not the same entity.

## Generation Timing

`generation_day_type`: **day_of_week** (cut on a specific weekday) or **fixed_delay** (cut
N days after the period ends). N comes from `generation_delay_days`.

## Delay Days

The N-days-after-period-end offset, stored in `generation_delay_days`. After the
grace→delay change, this is the *only* offset concept — see [[grace-floor-removed]].
Distinct from the legacy **grace days** floor it replaces.

A `delay_days` value of **0 means "next day"** (period_end + 1), never same-day. The
`fixed_delay` run date is `period_end + max(delay_days, 1)`. Range 0–30.

## Grace Days (legacy)

`min_grace_days` / `*_min_grace_days`. Historically a floor — "earliest the document can be
cut" = period_end + grace — applied in *both* generation modes. Being removed from code;
see [[grace-floor-removed]]. The `billing_schedules.min_grace_days` column is retained but
dormant.

## Billing Start Date

`billing_start_date` on a Billing Schedule. Intended anchor for the *first* billing period.
Currently stored but read by no generation code ("dead data") — being wired up.

## Advance Reconciliation

The settling of a prepaid Advance invoice against what was actually delivered. Done
inline on the next monthly run (1st) and — being added — again on the 10th to catch
late-approved session logs.
