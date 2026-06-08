# School Invoice — Schedule Configuration Reference

> **Scope:** how a school's `billing_schedules` row (with `schedule_type = SCHOOL_INVOICE`) drives the automatic generation of `invoices`. Covers each form field, the Standard vs Advance billing modes, every Frequency × Generation Timing combination, the billing entry window, and worked examples.
>
> **Source of truth:** [`BillingScheduleService`](../app/Domain/Billing/Services/BillingScheduleService.php), [`BillingAutomationService`](../app/Domain/Billing/Services/BillingAutomationService.php), [`InvoiceService`](../app/Domain/Invoice/Services/InvoiceService.php), [`AdvanceBillingService`](../app/Domain/Billing/Services/AdvanceBillingService.php), [`BillingEntryWindowService`](../app/Domain/Billing/Services/BillingEntryWindowService.php), [`BillingSchedule`](../app/Models/BillingSchedule.php), and the [`BillingFrequency`](../app/Enums/BillingFrequency.php) / [`GenerationDayType`](../app/Enums/GenerationDayType.php) / [`BillingMode`](../app/Enums/BillingMode.php) enums.
>
> **Companion docs:**
> - [`THERAPIST_BILLING_SCHEDULE.md`](THERAPIST_BILLING_SCHEDULE.md) — schedule mechanics are shared; this doc focuses on the school-specific bits.
> - [`BILLING_AUTOMATION_RUNTIME.md`](BILLING_AUTOMATION_RUNTIME.md) — how the daily `billing:generate` command picks schedules, resolves periods for Standard vs Advance, sweeps sessions, advances `next_run_at`, and known bugs in the first-run path.
> - [`INVOICING.md`](INVOICING.md) — the end-to-end invoice reference: manual vs automatic creation, Standard vs Advance line sourcing, the data model, lifecycle (send/pay/reconcile), and known gaps.

---

## 1. Mental model

A school invoice schedule answers **three** questions on each run:

1. **What window of work are we billing for?** — driven by `frequency` (the *billing period*).
2. **On what date do we cut the invoice?** — driven by `generation_day_type` + (`generation_day_of_week` *or* `generation_delay_days`). There is **no grace floor** — the run date is computed directly off `period_end` (see §3).
3. **Are we billing for work already delivered or work about to happen?** — driven by `billing_mode` (Standard / Advance). This question doesn't exist for therapist bills.

The invoice's `due_date` is `invoice_date + payment_terms_days` — honored on every path (manual, standard-auto, advance-auto). See §2.7.

A schedule is picked up by [`BillingGenerate`](../app/Console/Commands/BillingGenerate.php) (`php artisan billing:generate --type=school_invoice`) which calls `BillingAutomationService::processAllDueSchedules()` for every schedule whose `next_run_at <= today` and `is_active = true` and `auto_generate = true`.

---

## 2. Fields on the form

All fields are persisted on `billing_schedules` (with `schedule_type = SCHOOL_INVOICE`) and validated by [`StoreBillingScheduleRequest`](../app/Http/Requests/Admin/StoreBillingScheduleRequest.php) / [`UpdateBillingScheduleRequest`](../app/Http/Requests/Admin/UpdateBillingScheduleRequest.php).

### 2.1 Billing Mode *
Column: `billing_mode` — enum [`BillingMode`](../app/Enums/BillingMode.php).

The school-specific knob. Two modes:

- **`standard`** (Postpaid) — invoice generated **after** the period closes, billing the session logs that were actually delivered. This matches "invoice for 5/1–5/31 after May ends." Sweep logic: [`BillingAutomationService::sweepUnInvoicedSessions()`](../app/Domain/Billing/Services/BillingAutomationService.php#L242) collects every approved, billable, uninvoiced `SessionLog` with `session_date <= period_end` (no lower bound — late entries from prior periods ride along).
- **`advance`** (Prepaid) — invoice generated **before** the period begins, billing the `Schedule` rows (status = `SCHEDULED`) whose `schedule_date` falls inside the upcoming period. On the *next* run, [`AdvanceBillingService`](../app/Domain/Billing/Services/AdvanceBillingService.php) adds an **adjustment line item** that reconciles what was prepaid against what was actually delivered, so over/underbilling settles automatically.

The rest of the form behaves the same regardless of mode — only the sweep query differs.

### 2.2 Frequency *
Column: `frequency` — enum [`BillingFrequency`](../app/Enums/BillingFrequency.php).

Defines the **billing period boundaries** — the work window each run covers. Logic shared with therapist side: [`BillingScheduleService::determineBillingPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L181).

| Value | Period boundaries |
|---|---|
| `weekly` | Monday → Sunday |
| `bi_weekly` | 14-day blocks anchored to Mon 2026-01-05 |
| `semi_monthly` | Day 1–15 and Day 16–end-of-month |
| `monthly` | 1st → last day of month |

### 2.3 Generation Timing *
Column: `generation_day_type` — enum [`GenerationDayType`](../app/Enums/GenerationDayType.php).

Picks **how** the run date is computed after the period closes (Standard) or before it begins (Advance). Two modes:

- **`day_of_week`** — generate on a specific weekday (e.g. every Monday).
- **`fixed_delay`** — generate N days after the period ends.

Math lives in [`BillingScheduleService::calculateNextRunDate()`](../app/Domain/Billing/Services/BillingScheduleService.php#L196):

```
if mode == fixed_delay:
    run = period_end + max(generation_delay_days, 1)   ← delay 0 ⇒ next day

if mode == day_of_week:
    run = first date >= period_end whose weekday == generation_day_of_week
```

> Neither branch applies a grace floor — both walk directly from `period_end`. (The old `min_grace_days` floor was removed; see §3 and §7.)

### 2.4 Day of Week
Column: `generation_day_of_week` — int 0–6 (0 = Sunday, 6 = Saturday). Only meaningful when **Generation Timing = Day of Week**. Defaults to Tuesday (2).

### 2.5 Delay Days
Column: `generation_delay_days` — int 0–30. Only meaningful when **Generation Timing = Fixed Delay**. Defaults to 3. The run date is `period_end + max(generation_delay_days, 1)` — a value of **0 means the next day** (never the same day as the period end). No grace floor is applied — see §3.

### 2.6 Billing Start Date
Column: `billing_start_date` — nullable date.

**Anchors the first billing period.** On the first-ever run (`last_period_end` null), the advance and standard flows anchor the billing period on `billing_start_date` instead of `now()`, so the first invoice covers the intended period — see [`AdvanceBillingService::resolveCompletedPeriod()`](../app/Domain/Billing/Services/AdvanceBillingService.php) / [`BillingAutomationService::resolveCurrentPeriod()`](../app/Domain/Billing/Services/BillingAutomationService.php#L336) (both fall back to `now()` only when `billing_start_date` is null). On auto-create, [`BillingStartDateResolver::forSchool()`](../app/Domain/Billing/Services/BillingStartDateResolver.php) computes it: private/advance → 1st of next month; non-private/standard → 1st of current month. A future start date holds the schedule idle until then. It does **not** gate session sweeping ("don't bill sessions before this date") — it only sets the first-period anchor.

### 2.7 Payment Terms (Days) *
Column: `payment_terms_days` — int 1–90.

Drives the invoice **due date**: `due_date = invoice_date + payment_terms_days`. Honored on **every** school path:
- **Standard auto** — [`BillingAutomationService::processSchoolInvoice()`](../app/Domain/Billing/Services/BillingAutomationService.php#L111) passes `payment_terms_days` into [`InvoiceService::generateInvoice()`](../app/Domain/Invoice/Services/InvoiceService.php#L102).
- **Advance auto** — [`AdvanceBillingService::createAdvanceInvoice()`](../app/Domain/Billing/Services/AdvanceBillingService.php) resolves it from the schedule.
- **Manual** — `InvoiceService::generateInvoice()` uses `payment_terms_days` from the school's `school_invoice` schedule, falling back to the matching billing-settings default.

### 2.8 Dormant column: `min_grace_days`
Column: `min_grace_days` — int 0–14. **No longer read by any generation logic** — the grace floor was removed (see §3 and §7). The column is kept in the DB for backward-compat and receives a value on save (mirroring `generation_delay_days`), but it does **not** affect the run date.

### 2.9 Hidden but important: `auto_generate` / `auto_send` / `is_active`
- `is_active` — schedule is enabled at all.
- `auto_generate` — the daily `billing:generate` command will fire it. If `false`, the schedule still tracks `next_run_at` but waits for a manual trigger.
- `auto_send` — when `true` **and** the generated invoice total is **> 0**, the run auto-emails the invoice (or therapist bill) to the recipient immediately after the generation transaction commits, reusing the manual send path (`InvoiceService::sendInvoice` / `TherapistBillService::sendBill`). The send runs **outside** the generation transaction and is wrapped in try-catch — a mailer failure is logged and swallowed, never rolling back a successfully generated invoice. On success the `billing_schedule_runs.auto_sent` flag is set. Zero-amount invoices are never auto-sent. Toggled per-schedule and seeded from the billing-settings defaults (`*_auto_send`). See `BillingAutomationService::maybeAutoSendInvoice/Bill` and `AdvanceBillingService::maybeAutoSendInvoice`.

---

## 2.10 Where the defaults come from — Admin Billing Settings

When the schedule form first loads for a school, every field is **pre-populated from the global Admin Billing Settings**, not blank. The settings live on the singleton `billing_settings` row, edited by admins at [`resources/views/admin/billing/settings.blade.php`](../resources/views/admin/billing/settings.blade.php) (route: `admin.billing.settings`). The admin can override any field on the per-school form before saving — that override is what gets persisted to the schedule's `billing_schedules` row.

**Which defaults are used is decided automatically**, not by the admin: [`EntityBillingController::show()`](../app/Http/Controllers/Admin/EntityBillingController.php#L69-L102) checks the school's `is_private_student` flag — if `true` → **Advance Invoice Defaults**, otherwise → **Standard Invoice Defaults**. (Therapists always use the **Standard Billing Defaults** `default_*` block.) The admin can still flip the Billing Mode radio on the form after load.

There are **three** default sets on the `billing_settings` singleton, each a parallel column family:

**Standard Invoice Defaults (Postpaid School)** — `standard_default_*` columns, used for non-private schools:

| Form field | Pre-filled from `billing_settings` column |
|---|---|
| Billing Mode | hardcoded `'standard'` |
| Frequency | `standard_default_frequency` |
| Generation Timing | `standard_default_generation_day_type` |
| Day of Week | `standard_default_generation_day_of_week` |
| Delay Days | `standard_default_delay_days` |
| Payment Terms | `standard_default_payment_terms_days` |
| Auto Generate | `standard_default_auto_generate` |
| Auto Send | `standard_default_auto_send` |
| Billing Start Date | `null` (computed on auto-create — see §2.6) |
| Notes | `null` |

**Advance Invoice Defaults (Prepaid School/Family)** — parallel `advance_default_*` columns, used for private-student schools:

| Form field | Pre-filled from `billing_settings` column |
|---|---|
| Billing Mode | hardcoded `'advance'` |
| Frequency | `advance_default_frequency` |
| Generation Timing | `advance_default_generation_day_type` |
| Day of Week | `advance_default_generation_day_of_week` |
| Delay Days | `advance_default_delay_days` |
| Payment Terms | `advance_default_payment_terms_days` |
| Auto Generate | `advance_default_auto_generate` |
| Auto Send | `advance_default_auto_send` |
| Billing Start Date | `null` (computed on auto-create — see §2.6) |
| Notes | `null` |

> The **Standard Billing Defaults** (`default_*`) set is the therapist-bill family — see [`THERAPIST_BILLING_SCHEDULE.md`](THERAPIST_BILLING_SCHEDULE.md). The grace columns were renamed to `*_delay_days` (`default_delay_days`, `advance_default_delay_days`, `standard_default_delay_days`).

**Why this matters:** changing a default in Admin Billing Settings only affects **schedules created after** that change — existing schedules already have their values copied into `billing_schedules` and keep using those. If you need to update all schools at once, the global setting is not enough; you'd have to backfill the per-schedule rows.

> **Note:** the entity billing tab no longer hardcodes a hidden `min_grace_days`; that column is dormant (§2.8) and the form persists a value mirroring the chosen Delay Days. The Delay Days field is sourced from the `*_delay_days` setting for the selected mode.

---

## 3. Generation timing and the late-entry buffer (the old grace floor, removed)

**Historical note:** generation timing used to apply a `min_grace_days` **floor** — the run could never happen sooner than `period_end + min_grace_days`. That floor was **removed** from both Generation Timing modes. `fixed_delay` now runs at `period_end + max(generation_delay_days, 1)` and `day_of_week` walks forward from `period_end` directly. The `min_grace_days` column is dormant (§2.8).

**Why a buffer still matters:** session logs can be entered after the work happens. If invoices generated the instant the period closed, late entries (Sunday session entered Monday morning) would miss the current cycle. With the floor gone, the buffer is now expressed **only** through the chosen Delay Days (or the weekday gap) — an admin who wants a buffer must configure `fixed_delay` with a non-trivial delay (or a later weekday). A `Fixed Delay = 0` now generates the very next day with no buffer.

The matching guard on the data-entry side is [`BillingEntryWindowService`](../app/Domain/Billing/Services/BillingEntryWindowService.php) — therapists are blocked from entering sessions past the configured cutoff (`config('billing.entry_window_days_after_week_start')`). It still operates independently; it is no longer paired with a generation-side floor.

---

## 4. Frequency × Generation Timing — full combination matrix

All examples assume **`payment_terms_days = 30`** (so due date = run date + 30), **Standard (Postpaid)** mode, and **no grace floor** (run dates are computed directly off `period_end`). Reference month: May 2026 (5/1 = Fri, 5/15 = Fri, 5/31 = Sun).

### 4.1 Weekly

**Period:** Monday → Sunday. Example block: Mon 5/4 → Sun 5/10. `period_end = 5/10` (Sun).

| Mode | Setting | Run date | Due date (+30) |
|---|---|---|---|
| Day of Week | Monday | Mon 5/11 | 6/10 |
| Day of Week | Tuesday | Tue 5/12 | 6/11 |
| Day of Week | Friday | Fri 5/15 | 6/14 |
| Fixed Delay | 1 | Mon 5/11 | 6/10 |
| Fixed Delay | 3 | Wed 5/13 | 6/12 |
| Fixed Delay | 7 | Sun 5/17 | 6/16 |

### 4.2 Bi-Weekly

**Period:** 14-day blocks aligned to Mon 2026-01-05 (see [`biWeeklyPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L274)). Example block: Mon 5/11 → Sun 5/24. `period_end = 5/24` (Sun).

| Mode | Setting | Run date | Due date |
|---|---|---|---|
| Day of Week | Monday | Mon 5/25 | 6/24 |
| Day of Week | Tuesday | Tue 5/26 | 6/25 |
| Fixed Delay | 2 | Tue 5/26 | 6/25 |
| Fixed Delay | 7 | Sun 5/31 | 6/30 |
| Fixed Delay | 14 | Sun 6/7 | 7/7 |

### 4.3 Semi-Monthly

**Period:** day 1–15, then day 16–EOM (see [`semiMonthlyPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L234)). Two periods per month → two invoices per month.

For **first-half** period 5/1–5/15: `period_end = Fri 5/15`.
For **second-half** period 5/16–5/31: `period_end = Sun 5/31`.

| Mode | Setting | First-half run | Second-half run |
|---|---|---|---|
| Day of Week | Monday | Mon 5/18 | Mon 6/1 |
| Day of Week | Tuesday | Tue 5/19 | Tue 6/2 |
| Day of Week | Friday | Fri 5/15 | Fri 6/5 |
| Fixed Delay | 2 | Sun 5/17 | Tue 6/2 |
| Fixed Delay | 5 | Wed 5/20 | Fri 6/5 |
| Fixed Delay | 15 | Sun 5/30 | Mon 6/15 |

> Runs drift by weekday because Day of Week walks forward from whatever weekday 5/15 / 5/31 land on. (When `period_end` itself is the target weekday — e.g. first-half Friday on 5/15 — the run lands on the period-end day, since there's no grace floor pushing it out.) There is no current combination that pins runs to "always 15th + last day."

### 4.4 Monthly

**Period:** 1st → last of month. Example: May 2026. `period_end = Sun 5/31`.

| Mode | Setting | Run date | Due date |
|---|---|---|---|
| Day of Week | Monday | Mon 6/1 | 7/1 |
| Day of Week | Tuesday | Tue 6/2 | 7/2 |
| Fixed Delay | 1 | Mon 6/1 | 7/1 |
| Fixed Delay | 3 | Wed 6/3 | 7/3 |
| Fixed Delay | 5 | Fri 6/5 | 7/5 |
| Fixed Delay | 15 | Mon 6/15 | 7/15 |
| Fixed Delay | 30 | Tue 6/30 *(month-length drift)* | 7/30 |

> **Closest match to "invoice for 5/1–5/31, generate on the 1st":** Monthly + Fixed Delay = 1 → generates on Mon 6/1 (now that the grace floor is gone, `period_end + 1` lands exactly on the 1st).

---

## 5. End-to-end: what happens on each run (Standard / Postpaid)

When `billing:generate` finds a due school-invoice schedule, [`BillingAutomationService::processSchoolInvoice()`](../app/Domain/Billing/Services/BillingAutomationService.php#L105) executes:

1. **Resolve the period** via `resolveCurrentPeriod()` — uses `last_period_end + 1 day` if the schedule has run before, else `now()`.
2. **Sweep sessions** via `sweepUnInvoicedSessions(school_id, periodEnd)` — pulls every approved, uninvoiced, billable session log up to `periodEnd` (so prior-period leftovers ride along).
3. **Empty sweep → skip + advance.** Logs a `SKIPPED_NO_SESSIONS` run and advances `next_run_at` for the next period.
4. **Generate the invoice** inside a `DB::transaction`:
    - `InvoiceService::generateInvoice()` creates the `invoices` row with `due_date = invoice_date + payment_terms_days` (the schedule's terms are passed through — see §2.7).
    - Each session becomes a line item carrying `billing_period_start` / `billing_period_end`.
    - Run is logged as `SUCCESS` with totals on `billing_schedule_runs`.
    - `scheduleService->advanceSchedule()` updates `last_run_at`, `last_period_end`, and recomputes `next_run_at` for the next period end.
5. **Failure path** — exceptions are caught, logged via `Log::error`, and recorded as a `FAILED` run on the schedule (schedule does *not* advance on failure, so the next cron run retries).

---

## 6. End-to-end: Advance (Prepaid) mode

When `billing_mode = advance`, [`AdvanceBillingService::processAdvanceSchedule()`](../app/Domain/Billing/Services/AdvanceBillingService.php) runs instead:

1. **Resolve the upcoming period** via [`resolveUpcomingPeriod()`](../app/Domain/Billing/Services/AdvanceBillingService.php#L708) — this calls the same [`BillingScheduleService::determineBillingPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L181) used by postpaid, so the period is **calendar-aligned by Frequency**, not a rolling N-day window from the run date.
2. **Charge lines** — scan `schedules` for `status = SCHEDULED` where `schedule_date` falls within that upcoming period; one charge line per scheduled session.
3. **Adjustment lines** — for the *prior* period, compare what was advance-billed against what actually got delivered (approved session logs); add positive or negative adjustment lines to reconcile.

### 6.1 What "upcoming period" actually means by Frequency

> ⚠️ **Common misconception:** Advance billing does **not** look at "next 7 / 14 / 15 / 30 days from the run date." It looks at the next **calendar-aligned billing period** — the same period boundaries documented in §2.2 and §4.

Concrete examples — schedule runs on **Sun 5/31/2026**, last_period_end = 5/24 (or unset):

| Frequency | Upcoming period that gets billed | Notes |
|---|---|---|
| **Weekly** | Mon 6/1 → Sun 6/7 | Always full Mon–Sun, never a rolling 7 days |
| **Bi-Weekly** | Mon 5/25 → Sun 6/7 | 14-day block aligned to the 2026-01-05 epoch |
| **Semi-Monthly** | 6/1 → 6/15 | The next first-half period (would be 6/16 → 6/30 if last_period_end was already 6/15) |
| **Monthly** | 6/1 → 6/30 | Full June, even though the run is on 5/31 |

So if you run a **monthly** advance schedule on May 30th, it bills **all scheduled sessions in June 1 – June 30**, not "30 rolling days from May 30." The same holds for semi-monthly: a late-May run bills 6/1–6/15, not 5/30–6/14.

### 6.2 Adjustment example

Suppose a school is on Monthly + Advance:

- **May 1 run** bills 20 scheduled sessions for 5/1–5/31 → invoice for $2,000.
- During May, 2 sessions get cancelled and 1 extra makeup session is added & approved.
- **June 1 run** bills June's scheduled sessions ($X) **plus** an adjustment line for May: `-2 cancelled × rate` and `+1 makeup × rate`, netting against the May overbill.

The reconciliation logic lives in [`AdvanceBillingService`](../app/Domain/Billing/Services/AdvanceBillingService.php) (`buildAdjustmentLines()` / `detectExtraSessions()`). No manual credit notes needed. The `due_date = invoice_date + payment_terms_days` rule (§2.7) applies here too.

This means a school in Advance mode that paid for 20 sessions in May but only used 18 will see a -2 session adjustment on the June invoice — no manual credit notes needed.

### 6.3 The 10th-of-month catch-up reconcile (`billing:reconcile-advance`)

The 1st-of-month run (§6.2) only reconciles sessions that were **approved by run time**. Sessions approved *after* it — a therapist logs late, an admin approves on the 5th — are missed by that run. A second scheduled command, [`billing:reconcile-advance`](../app/Console/Commands/ReconcileAdvanceInvoices.php) (`->monthlyOn(10, '02:00')`), catches them. It reconciles **strictly the prior calendar month** (`now()->subMonth()`); current-month sessions are still in their open advance period and are never touched. Idempotent via the `advance_reconciliations` table — a `(schedule, period)` is reconciled at most once.

[`AdvanceReconciliationService::reconcileSchedule()`](../app/Domain/Billing/Services/AdvanceReconciliationService.php) computes a **per-session late delta** = `should_bill − already_billed`, where `already_billed` is the sum of that session's prior-month `invoice_line_items.total` across the school's invoices (the original advance charge **plus** every prior adjustment/settlement line). A session already fully reconciled by the 1st-run nets to 0 and is skipped — it is **never re-billed**. Each non-zero delta becomes a **status-based** line (no-show / cancelled / rate-difference / additional-session, via the shared `AdvanceAdjustmentClassifier`), so a settlement invoice reads like the regular advance invoice.

**Net into ONE document (Q8b).** The run nets all deltas and emits a single document by the **net sign** — never a settlement invoice *and* a credit note in the same run:

| Net of all deltas | Output |
|---|---|
| **> 0** (school owes us) | one **draft settlement invoice** carrying every status-based line, `total = net` |
| **< 0** (we owe the school) | one **ledger `credit_note`** for `\|net\|` (`recorded_at` = run date; no invoice). Single net entry — the ledger has no line items; per-session detail stays in the session logs |
| **= 0** | neither document; only the `advance_reconciliations` row marking the period done |

---

## 7. Behaviour notes / things to flag

- **`due_date` = `invoice_date + payment_terms_days`** on every path (§2.7) — manual, standard-auto, and advance-auto. There is no `+30` hardcode.
- **Generation timing has no grace floor.** `fixed_delay` = `period_end + max(delay, 1)` (delay 0 ⇒ next day); `day_of_week` walks from `period_end` directly (§3). The `min_grace_days` column is dormant (§2.8); the `billing_settings` grace columns are renamed `*_delay_days`.
- **`billing_start_date` anchors the first billing period** (§2.6) for both Standard and Advance. It does **not** gate session sweeping.
- **`auto_send`** auto-emails non-zero invoices when the schedule opts in (§2.9).
- **System user fallback.** Generated invoices are attributed to the oldest admin by id — there's no dedicated `system` user. Worth knowing if you audit `created_by` columns downstream.
