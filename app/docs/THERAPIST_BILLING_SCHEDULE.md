# Therapist Billing — Schedule Configuration Reference

> **Scope:** how a therapist's `billing_schedules` row drives the automatic generation of `therapist_bills`. Covers each form field, every Frequency × Generation Timing combination, the billing entry window, and worked examples.
>
> **Source of truth:** [`BillingScheduleService`](../app/Domain/Billing/Services/BillingScheduleService.php), [`BillingAutomationService`](../app/Domain/Billing/Services/BillingAutomationService.php), [`BillingEntryWindowService`](../app/Domain/Billing/Services/BillingEntryWindowService.php), [`BillingSchedule`](../app/Models/BillingSchedule.php), and the [`BillingFrequency`](../app/Enums/BillingFrequency.php) / [`GenerationDayType`](../app/Enums/GenerationDayType.php) enums.
>
> **Companion doc:** [`BILLING_AUTOMATION_RUNTIME.md`](BILLING_AUTOMATION_RUNTIME.md) — how the daily `billing:generate` command picks schedules, decides which period to bill, sweeps sessions, advances `next_run_at`, and known bugs in the first-run path. Read this when reasoning about *when* and *what* the system bills, not just *how* fields are configured.

---

## 1. Mental model

A billing schedule answers two questions on each run:

1. **What window of work are we paying for?** — driven by `frequency` (the *billing period*).
2. **On what date do we cut the bill?** — driven by `generation_day_type` + (`generation_day_of_week` *or* `generation_delay_days`). There is **no grace floor** — the run date is computed directly off `period_end` (see §3).

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
if mode == fixed_delay:
    run = period_end + max(generation_delay_days, 1)   ← delay 0 ⇒ next day

if mode == day_of_week:
    run = first date >= period_end whose weekday == generation_day_of_week
```

> Neither branch applies a grace floor — both walk directly from `period_end`. (The old `min_grace_days` floor was removed; see §3.)

### 2.3 Day of Week
Column: `generation_day_of_week` — int 0–6 (0 = Sunday, 6 = Saturday).

Only meaningful when **Generation Timing = Day of Week**. Required-if `generation_day_type = day_of_week`. Defaults to Tuesday (2) when missing.

### 2.4 Delay Days
Column: `generation_delay_days` — int 0–30.

Only meaningful when **Generation Timing = Fixed Delay**. Required-if `generation_day_type = fixed_delay`. Defaults to 3 when missing. The run date is `period_end + max(generation_delay_days, 1)` — a value of **0 means the next day** (never the same day as the period end). No grace floor is applied — see §3.

### 2.5 Billing Start Date
Column: `billing_start_date` — nullable date.

**Anchors the first billing period.** On auto-create, [`BillingStartDateResolver::forTherapist()`](../app/Domain/Billing/Services/BillingStartDateResolver.php) computes it (created on/before the 15th → 1st of month; on/after the 16th → 16th). On the first-ever run (`last_period_end` null), [`BillingScheduleService::createSchedule()`](../app/Domain/Billing/Services/BillingScheduleService.php#L70) anchors the first period and `next_run_at` on `billing_start_date` instead of `now()`, and [`BillingAutomationService::resolveCurrentPeriod()`](../app/Domain/Billing/Services/BillingAutomationService.php#L336) seeds the first period from it. A future start date holds the schedule idle until then (`next_run_at` is clamped ≥ `billing_start_date`). It does **not** gate session sweeping (no lower bound on `session_date`) — it only sets the first-period anchor. Falls back to `now()` when null (legacy schedules).

### 2.6 Payment Terms (Days) *
Column: `payment_terms_days` — int 1–90.

Drives the **due date** stamped on the generated bill — not the generation date itself:

```
bill.due_date = run_date + payment_terms_days
```

Applied at [`BillingAutomationService::processTherapistBill()`](../app/Domain/Billing/Services/BillingAutomationService.php#L222).

### 2.7 Dormant column: `min_grace_days`
Column: `min_grace_days` — int 0–14. **No longer read by any generation logic** — the grace floor was removed (see §3). The column is kept in the DB for backward-compat and receives a value on save (mirroring `generation_delay_days` via [`EntityBillingController::show()`](../app/Http/Controllers/Admin/EntityBillingController.php#L108-L110)), but it does **not** affect the run date.

### 2.8 Hidden but important: `auto_generate` / `auto_send` / `is_active`
- `is_active` — schedule is enabled at all.
- `auto_generate` — the daily `billing:generate` command will fire it. If `false`, the schedule still tracks `next_run_at` but waits for a manual trigger.
- `auto_send` — when `true` **and** the generated bill's `total_due > 0`, the run auto-emails the bill to the therapist right after the generation transaction commits, via `TherapistBillService::sendBill()`. Runs **outside** the transaction, wrapped in try-catch (a mailer failure is logged and swallowed, never failing generation); on success `billing_schedule_runs.auto_sent` is set and the run DTO carries `autoSent: true`. Zero-amount bills are never auto-sent.

---

## 2.9 Where the defaults come from — Admin Billing Settings

When the schedule form first loads for a therapist, every field is **pre-populated from the global Admin Billing Settings**, not blank. The settings live on the singleton `billing_settings` row, edited by admins at [`resources/views/admin/billing/settings.blade.php`](../resources/views/admin/billing/settings.blade.php) (route: `admin.billing.settings`). The admin can override any field on the per-therapist form before saving — that override is what gets persisted to the schedule's `billing_schedules` row.

Therapists always receive the **Standard Billing Defaults** (the `default_*` column family) — the school-only Advance/Standard *Invoice* defaults branches in [`EntityBillingController::show()`](../app/Http/Controllers/Admin/EntityBillingController.php#L69-L102) only trigger for schools, never for therapists.

Mapping (the `default_*` block):

| Form field | Pre-filled from `billing_settings` column |
|---|---|
| Billing Mode | hardcoded `'standard'` |
| Frequency | `default_frequency` |
| Generation Timing | `default_generation_day_type` |
| Day of Week | `default_generation_day_of_week` |
| Delay Days | `default_delay_days` |
| Payment Terms | `default_payment_terms_days` |
| Auto Generate | `default_auto_generate` |
| Auto Send | `default_auto_send` |
| Billing Start Date | `null` (computed on auto-create — see §2.5) |
| Notes | `null` |

> The `billing_settings` grace column was renamed `default_delay_days` (and seeds the Delay Days field). The schools use parallel `advance_default_*` / `standard_default_*` families — see [`SCHOOL_INVOICE_SCHEDULE.md`](SCHOOL_INVOICE_SCHEDULE.md).

**Why this matters:** changing a default in Admin Billing Settings only affects **schedules created after** that change — existing schedules already have their values copied into `billing_schedules` and keep using those. If you need to update all therapists at once, the global setting is not enough; you'd have to backfill the per-schedule rows.

---

## 3. Generation timing and the late-entry buffer (the old grace floor, removed)

**Historical note:** generation timing used to apply a `min_grace_days` **floor** — the run could never happen sooner than `period_end + min_grace_days`. That floor was **removed** from both Generation Timing modes. `fixed_delay` now runs at `period_end + max(generation_delay_days, 1)` and `day_of_week` walks forward from `period_end` directly. The `min_grace_days` column is dormant (§2.7).

**Why a buffer still matters:** session logs can be entered after the work happens. If bills generated the instant the period closed, late entries (Sunday session entered Monday morning) would miss the current cycle. With the floor gone, the buffer is now expressed **only** through the chosen Delay Days (or the weekday gap) — an admin who wants a buffer must configure `fixed_delay` with a non-trivial delay (or a later weekday). A `Fixed Delay = 0` now generates the very next day with no buffer.

The matching guard on the *data-entry* side is `BillingEntryWindowService` — therapists are blocked from entering sessions past the configured cutoff (`config('billing.entry_window_days_after_week_start')`). It still operates independently; it is no longer paired with a generation-side floor.

---

## 4. Frequency × Generation Timing — full combination matrix

All examples assume **`payment_terms_days = 15`** and **no grace floor** (run dates computed directly off `period_end`). Reference month: May 2026 (5/1 = Fri, 5/15 = Fri, 5/31 = Sun).

### 4.1 Weekly

**Period:** Monday → Sunday. Example block: Mon 5/4 → Sun 5/10. `period_end = 5/10` (Sun).

| Mode | Setting | Run date | Due date (+15) |
|---|---|---|---|
| Day of Week | Monday | Mon 5/11 | 5/26 |
| Day of Week | Tuesday | Tue 5/12 | 5/27 |
| Day of Week | Friday | Fri 5/15 | 5/30 |
| Fixed Delay | 1 | Mon 5/11 | 5/26 |
| Fixed Delay | 3 | Wed 5/13 | 5/28 |
| Fixed Delay | 7 | Sun 5/17 | 6/1 |

### 4.2 Bi-Weekly

**Period:** 14-day blocks aligned to Mon 2026-01-05 (see [`biWeeklyPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L274)). Example block: Mon 5/11 → Sun 5/24. `period_end = 5/24` (Sun).

| Mode | Setting | Run date | Due date |
|---|---|---|---|
| Day of Week | Monday | Mon 5/25 | 6/9 |
| Day of Week | Tuesday | Tue 5/26 | 6/10 |
| Day of Week | Friday | Fri 5/29 | 6/13 |
| Fixed Delay | 2 | Tue 5/26 | 6/10 |
| Fixed Delay | 7 | Sun 5/31 | 6/15 |
| Fixed Delay | 14 | Sun 6/7 | 6/22 |

### 4.3 Semi-Monthly

**Period:** day 1–15, then day 16–EOM (see [`semiMonthlyPeriod()`](../app/Domain/Billing/Services/BillingScheduleService.php#L234)). Two periods per month.

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
| Fixed Delay | 30 | Sun 6/14 *(drifts)* | Tue 6/30 *(drifts)* |

> Runs drift by weekday because Day of Week walks forward from whatever weekday 5/15 / 5/31 land on. (When `period_end` is itself the target weekday — e.g. first-half Friday on 5/15 — the run lands on the period-end day, since there's no grace floor pushing it out.) Fixed Delay = 30 *approximately* lands a fortnight-and-a-half out, but breaks in February and 31-day months. There is no current combination that pins runs to "always 15th + last day."

### 4.4 Monthly

**Period:** 1st → last of month. Example: May 2026. `period_end = Sun 5/31`.

| Mode | Setting | Run date | Due date |
|---|---|---|---|
| Day of Week | Monday | Mon 6/1 | 6/16 |
| Day of Week | Tuesday | Tue 6/2 | 6/17 |
| Day of Week | Friday | Fri 6/5 | 6/20 |
| Fixed Delay | 5 | Fri 6/5 | 6/20 |
| Fixed Delay | 10 | Wed 6/10 | 6/25 |
| Fixed Delay | 15 | Mon 6/15 | 6/30 |
| Fixed Delay | 30 | Tue 6/30 *(month-length drift)* | 7/15 |

---

## 5. End-to-end: what happens on each run

When `billing:generate` finds a due schedule, [`BillingAutomationService::processSingleSchedule()`](../app/Domain/Billing/Services/BillingAutomationService.php#L73) executes:

1. **Resolve the period** via `resolveCurrentPeriod()` — uses `last_period_end + 1 day` if the schedule has run before; on the first-ever run it seeds the period from `billing_start_date` (§2.5), falling back to `now()` only when that is null.
2. **Sweep sessions** via `sweepUnBilledSessions(therapist_id, periodEnd)` — pulls every approved, unbilled, billable session up to `periodEnd` (so prior-period leftovers ride along).
3. **Empty sweep → skip + advance.** Logs a `SKIPPED_NO_SESSIONS` run and advances `next_run_at` for the next period.
4. **Generate the bill** inside a `DB::transaction`:
    - `TherapistBillService::generateBill()` creates the `therapist_bills` row with `due_date = now() + payment_terms_days`.
    - Run is logged as `SUCCESS` with totals on `billing_schedule_runs`.
    - `scheduleService->advanceSchedule()` updates `last_run_at = now()`, `last_period_end = periodEnd`, and recomputes `next_run_at` via `calculateNextRunDate()` for the **next** period end.
5. **Failure path** — exceptions in `processAllDueSchedules()` are caught, logged via `Log::error`, and recorded as a `FAILED` run on the schedule (the schedule does *not* advance on failure, so the next cron run will retry).

---

## 6. Behaviour notes / things to flag

- **`billing_start_date` anchors the first billing period** (§2.5), parallel to the school side. It does not gate session sweeping.
- **Generation timing has no grace floor.** `fixed_delay` = `period_end + max(delay, 1)` (delay 0 ⇒ next day); `day_of_week` walks from `period_end` directly (§3). The `min_grace_days` column is dormant (§2.7).
- **No "Fixed Date" Generation Timing.** Clients who pay on the 15th + last-day cannot express that today. Closest workarounds are Monthly + Fixed Delay = 30 (last-of-next-month) or Semi-Monthly + Fixed Delay = 30 (drifts in Feb / 31-day months). A proper anchor-date mode would be additive — new enum case, new column for `delay_months`.
- **`auto_send`** auto-emails non-zero bills to the therapist when the schedule opts in (§2.8); the run DTO's `autoSent` reflects the real outcome.
- **System user fallback.** `BillingAutomationService::getSystemUser()` picks the oldest admin by id to attribute generated bills — there's no dedicated `system` user. Worth knowing if you audit `created_by` columns downstream.
