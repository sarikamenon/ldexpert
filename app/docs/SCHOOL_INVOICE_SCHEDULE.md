# School Invoice — Schedule Configuration Reference

> **Scope:** how a school's `billing_schedules` row (with `schedule_type = SCHOOL_INVOICE`) drives the automatic generation of `invoices`. Covers each form field, the Standard vs Advance billing modes, every Frequency × Generation Timing combination, the silent guard rails (`min_grace_days`, billing entry window), and worked examples.
>
> **Source of truth:** [`BillingScheduleService`](../app/Domain/Billing/Services/BillingScheduleService.php), [`BillingAutomationService`](../app/Domain/Billing/Services/BillingAutomationService.php), [`InvoiceService`](../app/Domain/Billing/Services/InvoiceService.php), [`AdvanceBillingService`](../app/Domain/Billing/Services/AdvanceBillingService.php), [`BillingEntryWindowService`](../app/Domain/Billing/Services/BillingEntryWindowService.php), [`BillingSchedule`](../app/Models/BillingSchedule.php), and the [`BillingFrequency`](../app/Enums/BillingFrequency.php) / [`GenerationDayType`](../app/Enums/GenerationDayType.php) / [`BillingMode`](../app/Enums/BillingMode.php) enums.
>
> **Companion docs:**
> - [`THERAPIST_BILLING_SCHEDULE.md`](THERAPIST_BILLING_SCHEDULE.md) — schedule mechanics are shared; this doc focuses on the school-specific bits.
> - [`BILLING_AUTOMATION_RUNTIME.md`](BILLING_AUTOMATION_RUNTIME.md) — how the daily `billing:generate` command picks schedules, resolves periods for Standard vs Advance, sweeps sessions, advances `next_run_at`, and known bugs in the first-run path.

---

## 1. Mental model

A school invoice schedule answers **three** questions on each run:

1. **What window of work are we billing for?** — driven by `frequency` (the *billing period*).
2. **On what date do we cut the invoice?** — driven by `generation_day_type` + (`generation_day_of_week` *or* `generation_delay_days`), with `min_grace_days` as a floor.
3. **Are we billing for work already delivered or work about to happen?** — driven by `billing_mode` (Standard / Advance). This question doesn't exist for therapist bills.

The invoice's `due_date` is currently **hardcoded to `run_date + 30 days`** — see §7 (Known gaps).

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

Math lives in [`BillingScheduleService::calculateNextRunDate()`](../app/Domain/Billing/Services/BillingScheduleService.php#L151):

```
earliest_run = period_end + min_grace_days   ← floor, always applied

if mode == fixed_delay:
    run = max(period_end + generation_delay_days, earliest_run)

if mode == day_of_week:
    run = first date >= earliest_run whose weekday == generation_day_of_week
```

### 2.4 Day of Week
Column: `generation_day_of_week` — int 0–6 (0 = Sunday, 6 = Saturday). Only meaningful when **Generation Timing = Day of Week**. Defaults to Tuesday (2).

### 2.5 Delay Days
Column: `generation_delay_days` — int 1–30. Only meaningful when **Generation Timing = Fixed Delay**. Defaults to 3. **Capped from below by `min_grace_days`** — see §3.

### 2.6 Billing Start Date
Column: `billing_start_date` — nullable date.

**⚠️ Currently informational only on both sides.** The column is stored from the form but **no production code reads it** in `BillingAutomationService`, `InvoiceService`, or `AdvanceBillingService`. The scheduler does not use it to gate "don't sweep sessions before this date" or "don't run before this date." Treat as a planned feature or wire it up before relying on it.

### 2.7 Payment Terms (Days) *
Column: `payment_terms_days` — int 1–90.

**⚠️ Currently ignored on the school side.** [`InvoiceService::generateInvoice()`](../app/Domain/Billing/Services/InvoiceService.php#L81) hardcodes `due_date = today + 30 days`. The form lets the admin set this value, but it is not honored at invoice creation time. (Therapist bills *do* honor it — see [`BillingAutomationService:211`](../app/Domain/Billing/Services/BillingAutomationService.php#L211).) See §7 (Known gaps).

### 2.8 Hidden but important: `min_grace_days`
Column: `min_grace_days` — int 0–14, default 2.

Acts as a **floor on every Generation Timing mode**. The run can never happen sooner than `period_end + min_grace_days`, no matter what's in `generation_delay_days` or which weekday is chosen.

### 2.9 Hidden but important: `auto_generate` / `auto_send` / `is_active`
- `is_active` — schedule is enabled at all.
- `auto_generate` — the daily `billing:generate` command will fire it. If `false`, the schedule still tracks `next_run_at` but waits for a manual trigger.
- `auto_send` — reserved; not yet used to auto-email invoices.

---

## 2.10 Where the defaults come from — Admin Billing Settings

When the schedule form first loads for a school, every field is **pre-populated from the global Admin Billing Settings**, not blank. The settings live on the singleton `billing_settings` row, edited by admins at [`resources/views/admin/billing/settings.blade.php`](../resources/views/admin/billing/settings.blade.php) (route: `admin.billing.settings`). The admin can override any field on the per-school form before saving — that override is what gets persisted to the schedule's `billing_schedules` row.

**Which defaults are used is decided automatically**, not by the admin: [`EntityBillingController:60-64`](../app/Http/Controllers/Admin/EntityBillingController.php#L60-L64) checks the school's `is_private_student` flag — if `true` → Advance (Prepaid) defaults, otherwise → Standard (Postpaid) defaults. The admin can still flip the Billing Mode radio on the form after load.

**Standard (Postpaid)** — sourced in [`EntityBillingController:88-99`](../app/Http/Controllers/Admin/EntityBillingController.php#L88-L99):

| Form field | Pre-filled from `billing_settings` column |
|---|---|
| Billing Mode | hardcoded `'standard'` |
| Frequency | `default_frequency` |
| Generation Timing | `default_generation_day_type` |
| Day of Week | `default_generation_day_of_week` |
| Delay Days | **`null`** — no `default_generation_delay_days` column exists; user fills in manually if switching to Fixed Delay |
| Grace Days (hidden) | `default_min_grace_days` |
| Payment Terms | `default_payment_terms_days` |
| Auto Generate | `default_auto_generate` |
| Auto Send | `default_auto_send` |
| Billing Start Date | `null` |
| Notes | `null` |

**Advance (Prepaid)** — sourced in [`EntityBillingController:70-82`](../app/Http/Controllers/Admin/EntityBillingController.php#L70-L82) — uses the parallel `advance_default_*` columns:

| Form field | Pre-filled from `billing_settings` column |
|---|---|
| Billing Mode | hardcoded `'advance'` |
| Frequency | `advance_default_frequency` |
| Generation Timing | `advance_default_generation_day_type` |
| Day of Week | `advance_default_generation_day_of_week` |
| Delay Days | **`null`** — no `advance_default_generation_delay_days` column exists |
| Grace Days (hidden) | `advance_default_min_grace_days` |
| Payment Terms | `advance_default_payment_terms_days` |
| Auto Generate | `advance_default_auto_generate` |
| Auto Send | `advance_default_auto_send` |
| Billing Start Date | `null` |
| Notes | `null` |

**Why this matters:** changing a default in Admin Billing Settings only affects **schedules created after** that change — existing schedules already have their values copied into `billing_schedules` and keep using those. If you need to update all schools at once, the global setting is not enough; you'd have to backfill the per-schedule rows.

> ⚠️ As called out in §7, the entity billing tab currently hardcodes `min_grace_days = 2` in a hidden input, so the `default_min_grace_days` / `advance_default_min_grace_days` value from settings is **not** actually applied on that screen today. Treat as a bug.

---

## 3. Why `min_grace_days` exists and why it's a floor

**Why a grace period:** session logs can be entered after the work happens. If invoices generated the instant the period closed, late entries (Sunday session entered Monday morning) would miss the current cycle and get pushed to next period.

**Why a floor, not a default:** an admin could otherwise set `Fixed Delay = 1` or pick a `Day of Week` that lands sooner than the entry buffer allows, silently losing late entries every cycle. The floor guarantees the buffer always exists regardless of how the user configures the rest.

The matching guard on the data-entry side is [`BillingEntryWindowService`](../app/Domain/Billing/Services/BillingEntryWindowService.php) — therapists are blocked from entering sessions past the configured cutoff (`config('billing.entry_window_days_after_week_start')`). The two guards work as a pair.

---

## 4. Frequency × Generation Timing — full combination matrix

All examples assume **`min_grace_days = 2`**, **Standard (Postpaid)** mode, and a hardcoded **due date = run date + 30**. Reference month: May 2026 (5/1 = Fri, 5/15 = Fri, 5/31 = Sun).

### 4.1 Weekly

**Period:** Monday → Sunday. Example block: Mon 5/4 → Sun 5/10. `period_end = 5/10`, `earliest_run = 5/12` (Tue).

| Mode | Setting | Run date | Due date (+30) |
|---|---|---|---|
| Day of Week | Monday | Mon 5/18 | 6/17 |
| Day of Week | Tuesday | Tue 5/12 | 6/11 |
| Day of Week | Friday | Fri 5/15 | 6/14 |
| Fixed Delay | 1 | Tue 5/12 *(floor wins)* | 6/11 |
| Fixed Delay | 3 | Wed 5/13 | 6/12 |
| Fixed Delay | 7 | Sun 5/17 | 6/16 |

### 4.2 Bi-Weekly

**Period:** 14-day blocks aligned to Mon 2026-01-05 (see [`biWeeklyPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L274)). Example block: Mon 5/11 → Sun 5/24. `period_end = 5/24`, `earliest_run = 5/26` (Tue).

| Mode | Setting | Run date | Due date |
|---|---|---|---|
| Day of Week | Monday | Mon 6/1 | 7/1 |
| Day of Week | Tuesday | Tue 5/26 | 6/25 |
| Fixed Delay | 2 | Tue 5/26 | 6/25 |
| Fixed Delay | 7 | Sun 5/31 | 6/30 |
| Fixed Delay | 14 | Sun 6/7 | 7/7 |

### 4.3 Semi-Monthly

**Period:** day 1–15, then day 16–EOM (see [`semiMonthlyPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L234)). Two periods per month → two invoices per month.

For **first-half** period 5/1–5/15: `period_end = Fri 5/15`, `earliest_run = Sun 5/17`.
For **second-half** period 5/16–5/31: `period_end = Sun 5/31`, `earliest_run = Tue 6/2`.

| Mode | Setting | First-half run | Second-half run |
|---|---|---|---|
| Day of Week | Monday | Mon 5/18 | Mon 6/8 |
| Day of Week | Tuesday | Tue 5/19 | Tue 6/2 |
| Day of Week | Friday | Fri 5/22 | Fri 6/5 |
| Fixed Delay | 2 | Sun 5/17 | Tue 6/2 |
| Fixed Delay | 5 | Wed 5/20 | Fri 6/5 |
| Fixed Delay | 15 | Sun 5/30 | Mon 6/15 |

> Runs drift by weekday because Day of Week walks forward from whatever weekday 5/15 / 5/31 land on. There is no current combination that pins runs to "always 15th + last day."

### 4.4 Monthly

**Period:** 1st → last of month. Example: May 2026. `period_end = Sun 5/31`, `earliest_run = Tue 6/2`.

| Mode | Setting | Run date | Due date |
|---|---|---|---|
| Day of Week | Monday | Mon 6/8 | 7/8 |
| Day of Week | Tuesday | Tue 6/2 | 7/2 |
| **Fixed Delay** | **1** *(floor wins)* | **Tue 6/2** | **7/2** |
| Fixed Delay | 3 | Wed 6/3 | 7/3 |
| Fixed Delay | 5 | Fri 6/5 | 7/5 |
| Fixed Delay | 15 | Mon 6/15 | 7/15 |
| Fixed Delay | 30 | Tue 6/30 *(month-length drift)* | 7/30 |

> **Closest match to "invoice for 5/1–5/31, generate on the 1st":** Monthly + Fixed Delay = 1 → generates on Tue 6/2 (the 2-day grace floor pushes it from 6/1 to 6/2).

---

## 5. End-to-end: what happens on each run (Standard / Postpaid)

When `billing:generate` finds a due school-invoice schedule, [`BillingAutomationService::processSchoolInvoice()`](../app/Domain/Billing/Services/BillingAutomationService.php#L105) executes:

1. **Resolve the period** via `resolveCurrentPeriod()` — uses `last_period_end + 1 day` if the schedule has run before, else `now()`.
2. **Sweep sessions** via `sweepUnInvoicedSessions(school_id, periodEnd)` — pulls every approved, uninvoiced, billable session log up to `periodEnd` (so prior-period leftovers ride along).
3. **Empty sweep → skip + advance.** Logs a `SKIPPED_NO_SESSIONS` run and advances `next_run_at` for the next period.
4. **Generate the invoice** inside a `DB::transaction`:
    - `InvoiceService::generateInvoice()` creates the `invoices` row with `due_date = today + 30 days` (hardcoded — see §7).
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

The reconciliation logic lives in [`AdvanceBillingService`](../app/Domain/Billing/Services/AdvanceBillingService.php) (lines 286-350). No manual credit notes needed.
3. Same `invoices` row format and same `due_date = today + 30 days` rule applies.

This means a school in Advance mode that paid for 20 sessions in May but only used 18 will see a -2 session adjustment on the June invoice — no manual credit notes needed.

---

## 7. Known gaps / things to flag

- **`payment_terms_days` is ignored.** [`InvoiceService::generateInvoice()`](../app/Domain/Billing/Services/InvoiceService.php#L81) hardcodes `+30 days`. Should be `today + schedule.payment_terms_days`, matching the therapist side.
- **`billing_start_date` is half-wired.** Stored from the form, never read by the scheduler or invoice service. Either implement the gating semantics or remove the field.
- **No "Fixed Date" Generation Timing.** Schools that pay strictly on the 1st cannot express that today — the 2-day grace floor pushes the run to the 2nd or 3rd. The closest workaround is Monthly + Fixed Delay = 1, accepting the 1-2 day drift.
- **`auto_send` is dormant.** Schedule stores it, but invoices are not auto-emailed today. Sending is a manual step.
- **System user fallback.** Generated invoices are attributed to the oldest admin by id — there's no dedicated `system` user. Worth knowing if you audit `created_by` columns downstream.
