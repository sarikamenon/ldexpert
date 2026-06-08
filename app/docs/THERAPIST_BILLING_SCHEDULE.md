# Therapist Billing — Schedule Configuration Reference

> **Scope:** how a therapist's `billing_schedules` row drives the automatic generation of `therapist_bills`. Covers each form field, every Frequency × Generation Timing combination, the silent guard rails (`min_grace_days`, billing entry window), and worked examples.
>
> **Source of truth:** [`BillingScheduleService`](../app/Domain/Billing/Services/BillingScheduleService.php), [`BillingAutomationService`](../app/Domain/Billing/Services/BillingAutomationService.php), [`BillingEntryWindowService`](../app/Domain/Billing/Services/BillingEntryWindowService.php), [`BillingSchedule`](../app/Models/BillingSchedule.php), and the [`BillingFrequency`](../app/Enums/BillingFrequency.php) / [`GenerationDayType`](../app/Enums/GenerationDayType.php) enums.
>
> **Companion doc:** [`BILLING_AUTOMATION_RUNTIME.md`](BILLING_AUTOMATION_RUNTIME.md) — how the daily `billing:generate` command picks schedules, decides which period to bill, sweeps sessions, advances `next_run_at`, and known bugs in the first-run path. Read this when reasoning about *when* and *what* the system bills, not just *how* fields are configured.

---

## 1. Mental model

A billing schedule answers two questions on each run:

1. **What window of work are we paying for?** — driven by `frequency` (the *billing period*).
2. **On what date do we cut the bill?** — driven by `generation_day_type` + (`generation_day_of_week` *or* `generation_delay_days`), with `min_grace_days` as a floor.

The bill's `due_date` is then stamped as `run_date + payment_terms_days`.

A schedule is picked up by [`BillingGenerate`](../app/Console/Commands/BillingGenerate.php) (`php artisan billing:generate`) which calls `BillingAutomationService::processAllDueSchedules()` for every schedule whose `next_run_at <= today` and `is_active = true` and `auto_generate = true` (see the `scopeDue` scope in `BillingSchedule`).

---

## 2. Fields on the form

All fields are persisted on `billing_schedules` and validated by [`StoreBillingScheduleRequest`](../app/Http/Requests/Admin/StoreBillingScheduleRequest.php) / [`UpdateBillingScheduleRequest`](../app/Http/Requests/Admin/UpdateBillingScheduleRequest.php).

### 2.1 Frequency *
Column: `frequency` — enum [`BillingFrequency`](../app/Enums/BillingFrequency.php).

Defines the **billing period boundaries** — the work window each run pays for. Logic: `BillingScheduleService::determineBillingPeriod()`.

| Value | Period boundaries |
|---|---|
| `weekly` | Monday → Sunday |
| `bi_weekly` | 14-day blocks anchored to Mon 2026-01-05 |
| `semi_monthly` | Day 1–15 and Day 16–end-of-month |
| `monthly` | 1st → last day of month |

Only session logs with `session_date` `<=` the period end (and matching `status = approved`, `is_billable_therapist = true`, no `therapist_bill_id`, positive `therapist_billable_amount`) are swept into the bill by `BillingAutomationService::sweepUnBilledSessions()`. Sessions from earlier periods that were never billed get pulled in too — they're reported as `sessions_from_prior_periods` on the run log.

### 2.2 Generation Timing *
Column: `generation_day_type` — enum [`GenerationDayType`](../app/Enums/GenerationDayType.php).

Picks **how** the run date is computed after the period closes. Two modes today:

- **`day_of_week`** — generate on a specific weekday (e.g. every Monday).
- **`fixed_delay`** — generate N days after the period ends.

The math is in `BillingScheduleService::calculateNextRunDate()`:

```
earliest_run = period_end + min_grace_days   ← floor, always applied

if mode == fixed_delay:
    run = max(period_end + generation_delay_days, earliest_run)

if mode == day_of_week:
    run = first date >= earliest_run whose weekday == generation_day_of_week
```

### 2.3 Day of Week
Column: `generation_day_of_week` — int 0–6 (0 = Sunday, 6 = Saturday).

Only meaningful when **Generation Timing = Day of Week**. Required-if `generation_day_type = day_of_week`. Defaults to Tuesday (2) when missing.

### 2.4 Delay Days
Column: `generation_delay_days` — int 1–30.

Only meaningful when **Generation Timing = Fixed Delay**. Required-if `generation_day_type = fixed_delay`. Defaults to 3 when missing. **Capped from below by `min_grace_days`** — see §3.

### 2.5 Billing Start Date
Column: `billing_start_date` — nullable date.

**⚠️ Currently informational only.** The column is persisted from the form ([`EntityBillingController:125`](../app/Http/Controllers/Admin/EntityBillingController.php#L125)) but **no production code reads it** — `grep` returns no hits across `app/Domain`, `app/Jobs`, `app/Console`. The scheduler does not use it to gate "don't bill sessions before this date" or "don't run before this date." Either treat as a planned feature or wire it up before relying on it.

### 2.6 Payment Terms (Days) *
Column: `payment_terms_days` — int 1–90.

Drives the **due date** stamped on the generated bill — not the generation date itself:

```
bill.due_date = run_date + payment_terms_days
```

Applied at [`BillingAutomationService:211`](../app/Domain/Billing/Services/BillingAutomationService.php#L211).

### 2.7 Hidden but important: `min_grace_days`
Column: `min_grace_days` — int 0–14, default 2 (sourced from `billing_settings.default_min_grace_days` via [`EntityBillingController:93`](../app/Http/Controllers/Admin/EntityBillingController.php#L93)).

Acts as a **floor on every Generation Timing mode**. The run can never happen sooner than `period_end + min_grace_days`, no matter what's in `generation_delay_days` or which weekday is chosen.

### 2.8 Hidden but important: `auto_generate` / `auto_send` / `is_active`
- `is_active` — schedule is enabled at all.
- `auto_generate` — the daily `billing:generate` command will fire it. If `false`, the schedule still tracks `next_run_at` but waits for a manual trigger.
- `auto_send` — when `true` **and** the generated bill's `total_due > 0`, the run auto-emails the bill to the therapist right after the generation transaction commits, via `TherapistBillService::sendBill()`. Runs **outside** the transaction, wrapped in try-catch (a mailer failure is logged and swallowed, never failing generation); on success `billing_schedule_runs.auto_sent` is set and the run DTO carries `autoSent: true`. Zero-amount bills are never auto-sent.

---

## 2.9 Where the defaults come from — Admin Billing Settings

When the schedule form first loads for a therapist, every field is **pre-populated from the global Admin Billing Settings**, not blank. The settings live on the singleton `billing_settings` row, edited by admins at [`resources/views/admin/billing/settings.blade.php`](../resources/views/admin/billing/settings.blade.php) (route: `admin.billing.settings`). The admin can override any field on the per-therapist form before saving — that override is what gets persisted to the schedule's `billing_schedules` row.

Therapists always receive the **Standard (Postpaid) defaults** — the Advance defaults branch in [`EntityBillingController:60-64`](../app/Http/Controllers/Admin/EntityBillingController.php#L60-L64) only triggers for schools with `is_private_student = true`, never for therapists.

Mapping (sourced in [`EntityBillingController:88-99`](../app/Http/Controllers/Admin/EntityBillingController.php#L88-L99)):

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

**Why this matters:** changing a default in Admin Billing Settings only affects **schedules created after** that change — existing schedules already have their values copied into `billing_schedules` and keep using those. If you need to update all therapists at once, the global setting is not enough; you'd have to backfill the per-schedule rows.

> ⚠️ As called out in §2.7 / §8, the entity billing tab currently hardcodes `min_grace_days = 2` in a hidden input, so the `default_min_grace_days` from settings is **not** actually applied on that screen today. Treat as a bug.

---

## 3. Why `min_grace_days` exists and why it's a floor

**Why a grace period:** session logs can be entered after the work happens. If bills generated the instant the period closed, late entries (Sunday session entered Monday morning) would miss the current cycle and get pushed to next period.

**Why a floor, not a default:** an admin could otherwise set `Fixed Delay = 1` or pick a `Day of Week` that lands sooner than the entry buffer allows, silently losing late entries every cycle. The floor guarantees the buffer always exists regardless of how the user configures the rest.

The matching guard on the *data-entry* side is `BillingEntryWindowService` — therapists are blocked from entering sessions past the configured cutoff (`config('billing.entry_window_days_after_week_start')`). The two guards work as a pair: entry window keeps late edits from going stale, `min_grace_days` keeps generation from racing the entry window.

---

## 4. Frequency × Generation Timing — full combination matrix

All examples assume **`min_grace_days = 2`** and **`payment_terms_days = 15`**. Reference month: May 2026 (5/1 = Fri, 5/15 = Fri, 5/31 = Sun).

### 4.1 Weekly

**Period:** Monday → Sunday. Example block: Mon 5/4 → Sun 5/10. `period_end = 5/10`, `earliest_run = 5/12` (Tue).

| Mode | Setting | Run date | Due date (+15) |
|---|---|---|---|
| Day of Week | Monday | Mon 5/18 | 6/2 |
| Day of Week | Tuesday | Tue 5/12 | 5/27 |
| Day of Week | Friday | Fri 5/15 | 5/30 |
| Fixed Delay | 1 | Tue 5/12 *(floor wins)* | 5/27 |
| Fixed Delay | 3 | Wed 5/13 | 5/28 |
| Fixed Delay | 7 | Sun 5/17 | 6/1 |

### 4.2 Bi-Weekly

**Period:** 14-day blocks aligned to Mon 2026-01-05 (see [`biWeeklyPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L274)). Example block: Mon 5/11 → Sun 5/24. `period_end = 5/24`, `earliest_run = 5/26` (Tue).

| Mode | Setting | Run date | Due date |
|---|---|---|---|
| Day of Week | Monday | Mon 6/1 | 6/16 |
| Day of Week | Tuesday | Tue 5/26 | 6/10 |
| Day of Week | Friday | Fri 5/29 | 6/13 |
| Fixed Delay | 2 | Tue 5/26 | 6/10 |
| Fixed Delay | 7 | Sun 5/31 | 6/15 |
| Fixed Delay | 14 | Sun 6/7 | 6/22 |

### 4.3 Semi-Monthly

**Period:** day 1–15, then day 16–EOM (see [`semiMonthlyPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L234)). Two periods per month.

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
| Fixed Delay | 30 | Mon 6/15 *(drifts)* | Tue 6/30 *(drifts)* |

> Runs drift by weekday because Day of Week walks forward from whatever weekday 5/15 / 5/31 land on. Fixed Delay = 30 *approximately* lands on the 15th and EOM, but breaks in February and 31-day months. There is no current combination that pins runs to "always 15th + last day."

### 4.4 Monthly

**Period:** 1st → last of month. Example: May 2026. `period_end = Sun 5/31`, `earliest_run = Tue 6/2`.

| Mode | Setting | Run date | Due date |
|---|---|---|---|
| Day of Week | Monday | Mon 6/8 | 6/23 |
| Day of Week | Tuesday | Tue 6/2 | 6/17 |
| Day of Week | Friday | Fri 6/5 | 6/20 |
| Fixed Delay | 5 | Fri 6/5 | 6/20 |
| Fixed Delay | 10 | Wed 6/10 | 6/25 |
| Fixed Delay | 15 | Mon 6/15 | 6/30 |
| Fixed Delay | 30 | Tue 6/30 *(month-length drift)* | 7/15 |

---

## 5. End-to-end: what happens on each run

When `billing:generate` finds a due schedule, [`BillingAutomationService::processSingleSchedule()`](../app/Domain/Billing/Services/BillingAutomationService.php#L73) executes:

1. **Resolve the period** via `resolveCurrentPeriod()` — uses `last_period_end + 1 day` if the schedule has run before, else `now()`.
2. **Sweep sessions** via `sweepUnBilledSessions(therapist_id, periodEnd)` — pulls every approved, unbilled, billable session up to `periodEnd` (so prior-period leftovers ride along).
3. **Empty sweep → skip + advance.** Logs a `SKIPPED_NO_SESSIONS` run and advances `next_run_at` for the next period.
4. **Generate the bill** inside a `DB::transaction`:
    - `TherapistBillService::generateBill()` creates the `therapist_bills` row with `due_date = now() + payment_terms_days`.
    - Run is logged as `SUCCESS` with totals on `billing_schedule_runs`.
    - `scheduleService->advanceSchedule()` updates `last_run_at = now()`, `last_period_end = periodEnd`, and recomputes `next_run_at` via `calculateNextRunDate()` for the **next** period end.
5. **Failure path** — exceptions in `processAllDueSchedules()` are caught, logged via `Log::error`, and recorded as a `FAILED` run on the schedule (the schedule does *not* advance on failure, so the next cron run will retry).

---

## 6. Known gaps / things to flag

- **`billing_start_date` — now wired** as the first-period anchor (parallels the school side); no longer dead.
- **No "Fixed Date" Generation Timing.** Clients who pay on the 15th + last-day cannot express that today. Closest workarounds are Monthly + Fixed Delay = 30 (last-of-next-month) or Semi-Monthly + Fixed Delay = 30 (15th + EOM, drifts in Feb / 31-day months). A proper anchor-date mode would be additive — new enum case, new column for `delay_months`.
- **`auto_send` — now implemented** (§2.8). Non-zero bills auto-email the therapist when the schedule opts in; the run DTO's `autoSent` reflects the real outcome.
- **System user fallback.** `BillingAutomationService::getSystemUser()` picks the oldest admin by id to attribute generated bills — there's no dedicated `system` user. Worth knowing if you audit `created_by` columns downstream.
