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

The settling of a prepaid Advance invoice against what was *actually delivered*. The
advance invoice is cut on the 1st from upcoming `Schedule` rows, before sessions happen;
some of the prior month's session logs are still unsubmitted/unapproved at that moment, so
they couldn't be billed accurately. Reconciliation re-runs later (inline on the 1st, and —
being added — again on the **10th**) to catch those late-approved logs.

Source of truth (`AdvanceReconciliationService::buildReconcileLines`): it iterates
**approved `SessionLog` rows** in the prior period (`AdvanceReconciliationService.php:188`),
and for each compares `should_bill` (the log's school amount, or 0 if non-billable)
against `already_billed` — the Σ of that session's prior-month `invoice_line_items.total`
for the period, keyed `schedule:{id}` / `session:{id}`
(`billedTotalsForPeriod`, line 263). Only a non-zero delta produces an adjustment line
(`ADJUST_*`, or `ADJUST_EXTRA_SESSION` when never previously billed). A net-positive
result becomes a **draft settlement invoice**; net-negative becomes a **credit note** via
`LedgerService`. Idempotent per `(billing_schedule_id, period_start, period_end)` via the
`advance_reconciliations` table; see [[advance-reconciliation-tenth-of-month]] (ADR 0001).

**Disjoint from [[Re-open]]:** reconciliation only ever iterates sessions that *have a
session log*. A schedule cancelled before it happens has no session log, so reconciliation
never considers it — there is no double-count between re-open and reconciliation. They act
in different lifecycle windows (re-open = before delivery; reconciliation = after) and key
off different tables (re-open edits `invoice_line_items` / `schedules.invoice_id`;
reconciliation reads `session_logs`).

## Re-open

Moving a *sent* invoice back to `draft` so its line items can be corrected (e.g.
removing a cancelled schedule), then re-sending it. The reverse of "send".

**Re-open is the internal/domain verb; the user-facing UI label is "Edit Invoice."**
The button, route, service method, and audit event all say `reopen` (the mechanism —
SENT → DRAFT), but the admin sees "Edit Invoice" because their intent is to *correct* a
sent invoice, not to think about draft states. Keep the two distinct: a UI copy change
must not rename the `reopen` verb in code, and vice versa.

On re-open
the original `invoice_generated` ledger entry is soft-deleted and the chain recomputed,
restoring the invariant that a draft invoice has no ledger entry; re-sending creates a
fresh `invoice_generated` for the corrected total. Primarily needed for **Advance**
invoices, whose billed sessions haven't happened yet and may be cancelled before they do
— a Standard invoice bills delivered work, so it has nothing to correct. Distinct from a
*void* (terminal cancellation, no re-send expected): a re-opened invoice is expected to
be re-sent.

**Guard:** an advance invoice may be re-opened while it is sent + unpaid, even mid-month
after some of its sessions have been delivered. On re-open the admin sees the **full
schedule set** and freely chooses what stays — there is no system-enforced "delivered =
locked" rule. A delivered schedule (one with a `SessionLog`) is *expected* to be kept (it
was on the original invoice and it happened), but that is the admin's decision, not a
constraint.

Worked case: advance invoice sent on the 1st for 5 sessions (1st, 5th, 8th, …). On the
5th the parent cancels the 8th. The 1st has already happened (approved session log). Admin
re-opens, removes the *8th* (no log), keeps the *1st* (has a log) and the rest, re-sends.
The kept-but-delivered 1st is later seen by [[Advance Reconciliation]] with delta ≈ 0
(billed correctly); the removed 8th never produces a log, so reconciliation ignores it.
Disjointness with reconciliation holds at the **schedule** level.

**Load-bearing dependency:** because re-open reuses the advance re-select/rebuild path
(`attachSchedulesToAdvanceDraft` → `AdvanceChargeLineBuilder`, which filters
`scheduled()`), a kept delivered schedule only round-trips back onto the invoice while its
`Schedule.status` is still `SCHEDULED`. Nothing currently transitions a schedule to
`COMPLETED`, so this works — but it is "safe by accident." See ADR 0003
[[advance-schedules-never-complete]].

**Identity & trail:** re-open keeps the **same invoice number** — it is a correction of the
same document, not a new one. It requires a **reason** (the dispute answer to "why did this
sent invoice's total change?"). The who/when/why is captured via `HasAudits` on `Invoice`
(no dedicated `reopened_*` columns — re-open is low-frequency, so per-row columns would be
NULL on most invoices): the status/`sent_at`/`payment_token` field diff is audited
automatically, and the reason is recorded as a custom `reopened` audit event. It clears
`sent_at` so the invoice genuinely reads as un-sent again. Re-send is **manual** (the admin
clicks Send after editing) — never automatic — giving a review step before the school is
re-emailed. Each send is already recorded in `invoice_email_logs`.

**Payment guard:** only a **fully `PAID`** invoice is blocked from re-open (it is already
settled — nothing to correct). A **partially-paid** invoice MAY be re-opened: the existing
payment allocations stay attached, the recompute nets the corrected total against what was
paid, and the admin handles any resulting delta (e.g. an overpaid balance) manually with a
credit note or refund — out of scope for re-open itself. On re-open the **online payment
token is killed** (`payment_token` → null) so a stale `payment.show/{token}` link can no
longer collect the *old* amount; re-send mints a fresh token via `ensurePaymentToken()`.

**Ledger dating:** re-open is an *edit* of the same period's bill, so the re-issued
`invoice_generated` entry keeps the **original `recorded_at`** (not the re-send date) —
the full timestamp, including time-of-day, not merely the date. Re-open only *soft*-deletes
the original entry, so `createInvoiceGeneratedEntry` reads its `recorded_at` back off the
trashed row (`withTrashed`) and reuses it; the re-issued charge therefore lands in the exact
same `(recorded_at, id)` chain position it had before. (Re-deriving from `invoice_date`
would re-stamp the time-of-day from `now()` and could reorder the row against other same-day
entries — see the `recorded_at` time-of-day note in LEDGER_SYSTEM.md §4.) This backdated
insert is one the chain walker already handles (LEDGER_SYSTEM.md §11), and the school's
statement keeps one advance charge dated the period start, corrected — not a confusing
void+recharge pair.

## Cancelled Schedule (on an advance invoice)

A `Schedule` row, billed up-front on a sent Advance invoice, that the family later
cancels. The school will not pay for it, so on re-open it is removed from the invoice and
released back to the un-invoiced pool (`schedules.invoice_id` → null), making it
re-selectable when the corrected invoice is built.
