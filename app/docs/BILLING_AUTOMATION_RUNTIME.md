# Billing Automation Runtime

> **Scope:** how the `billing:generate` command picks schedules, decides which billing period to bill, sweeps sessions/schedules, and walks itself forward. Covers both Standard (Postpaid) and Advance (Prepaid) modes, plus known bugs in the first-run path.
>
> **Source of truth:** [`BillingGenerate`](../app/Console/Commands/BillingGenerate.php), [`BillingAutomationService`](../app/Domain/Billing/Services/BillingAutomationService.php), [`AdvanceBillingService`](../app/Domain/Billing/Services/AdvanceBillingService.php), [`BillingScheduleService`](../app/Domain/Billing/Services/BillingScheduleService.php), [`BillingSchedule`](../app/Models/BillingSchedule.php), and [`routes/console.php`](../routes/console.php).
>
> **Companion docs:**
> - [`THERAPIST_BILLING_SCHEDULE.md`](THERAPIST_BILLING_SCHEDULE.md) — therapist-side field reference and matrices.
> - [`SCHOOL_INVOICE_SCHEDULE.md`](SCHOOL_INVOICE_SCHEDULE.md) — school-side field reference, Standard vs Advance modes, and matrices.

---

## 1. The cron heartbeat

[`routes/console.php:13`](../routes/console.php#L13):

```php
Schedule::command('billing:generate')->dailyAt('02:00');
```

The Laravel scheduler runs `billing:generate` **once a day at 2:00 AM (app timezone)**. There is no separate cron expression per frequency — semi-monthly, monthly, weekly all share this daily heartbeat. Each schedule decides *for itself* whether it's due on a given tick.

### Manual invocation

```bash
php artisan billing:generate                            # all due schedules
php artisan billing:generate --type=therapist_bill      # only therapist bills
php artisan billing:generate --type=school_invoice      # only school invoices
php artisan billing:generate --schedule=42              # one specific schedule by ID
php artisan billing:generate --dry-run                  # preview, no DB writes
```

Manual runs use the same code path; they're useful for backfilling after the cron has been paused, or for verifying configuration before relying on automation.

---

## 2. How "due" is decided

`BillingGenerate::handle()` → `BillingAutomationService::processAllDueSchedules()` → [`EloquentBillingScheduleRepository::getDueSchedules()`](../app/Infrastructure/Repositories/EloquentBillingScheduleRepository.php#L25-L39):

```php
BillingSchedule::query()->due()->with(['schedulable'])->get();
```

The `due()` scope is defined in [`BillingSchedule.php:120-123`](../app/Models/BillingSchedule.php#L120-L123):

```php
$query
    ->where('is_active', true)
    ->where('auto_generate', true)
    ->where('next_run_at', '<=', now()->toDateString());
```

So three conditions must hold:

1. `is_active = true` — schedule isn't paused.
2. `auto_generate = true` — admin opted in to automation (otherwise the schedule still tracks `next_run_at` but the cron skips it).
3. **`next_run_at <= today`** — the date stamped on the schedule has arrived.

`next_run_at` is the single source of truth for *when* a schedule fires. The command does not check "is today the 15th?" or look at the frequency — it compares the pre-computed `next_run_at` against today's date and that's it.

---

## 3. Where `next_run_at` comes from

It's computed by `BillingScheduleService::calculateNextRunDate()` — the math documented in the two companion docs (`period_end + min_grace_days`, then walk-to-weekday or add-delay). It gets written to the DB at exactly three moments:

| When | What gets stamped | Code |
|---|---|---|
| Schedule created | `next_run_at` for the period containing `now()` | [`createSchedule()`](../app/Domain/Billing/Services/BillingScheduleService.php#L70-L88) |
| Schedule updated | `next_run_at` for the *next* period after `last_period_end` (or current period if never run) | [`updateSchedule()`](../app/Domain/Billing/Services/BillingScheduleService.php#L90-L115) |
| After every successful run | `next_run_at` for the period after the one just billed | [`advanceSchedule()`](../app/Domain/Billing/Services/BillingScheduleService.php#L212-L229) |

So once configured, the schedule walks itself forward without external intervention. The daily cron is just the heartbeat that asks "has any schedule's `next_run_at` arrived?"

---

## 4. Which period gets billed — Standard (Postpaid) mode

When a due schedule is picked, [`BillingAutomationService::processStandardSchedule()`](../app/Domain/Billing/Services/BillingAutomationService.php#L85) calls [`resolveCurrentPeriod()`](../app/Domain/Billing/Services/BillingAutomationService.php#L322-L331) to decide *which* billing period to process:

```php
private function resolveCurrentPeriod(BillingSchedule $schedule): array
{
    if ($schedule->last_period_end !== null) {
        $nextDay = $schedule->last_period_end->copy()->addDay();
        return $this->scheduleService->determineBillingPeriod($schedule->frequency, $nextDay);
    }
    return $this->scheduleService->determineBillingPeriod($schedule->frequency, now());
}
```

Two branches:

- **Subsequent run** (`last_period_end` is set) — period = the billing period starting the day after `last_period_end`. This walks forward sequentially, period by period, regardless of how late the cron actually fires.
- **First-ever run** (`last_period_end` is null) — period = the billing period **containing `now()`** at the moment the cron fires. Not the period anchored at schedule creation — this is the source of the bug in §6.1 below.

The sweep then runs against this period:

```php
SessionLog::query()
    ->where('therapist_id', $therapistId)         // or school_id
    ->where('status', SessionLogStatus::APPROVED)
    ->where('is_billable_therapist', true)        // or is_billable_school
    ->whereNull('therapist_bill_id')              // or invoice_id
    ->where('session_date', '<=', $periodEnd)     // ← upper bound only
    ->where('therapist_billable_amount', '>', 0)
    ->get();
```

**Key observation: there is no lower bound on `session_date`.** The sweep collects every approved, billable, un-billed session up to `periodEnd`, no matter how old. This is by design for late entries (a Tuesday session entered Friday rides along), but combined with the first-run bug it causes session leakage — see §6.

---

## 5. Which period gets billed — Advance (Prepaid) mode

Advance mode is school-only and runs through [`AdvanceBillingService::processAdvanceSchedule()`](../app/Domain/Billing/Services/AdvanceBillingService.php#L47). It resolves **two** periods on each run:

- **`completedPeriod`** — the period just-finished, used to build **adjustment lines** that reconcile what was prepaid vs what was actually delivered.
- **`upcomingPeriod`** — the next period, used to build **charge lines** for what's about to be billed in advance.

```php
$completedPeriod = $this->resolveCompletedPeriod($schedule);
$upcomingPeriod = $this->resolveUpcomingPeriod($schedule);
```

[`resolveCompletedPeriod()`](../app/Domain/Billing/Services/AdvanceBillingService.php#L692-L701) uses the same logic as the Standard `resolveCurrentPeriod()` — `last_period_end + 1` for subsequent runs, period containing `now()` for first runs. [`resolveUpcomingPeriod()`](../app/Domain/Billing/Services/AdvanceBillingService.php#L708-L714) is then **one period forward** from completed.

### What each side of the invoice contains

- **Charge lines** (from `upcomingPeriod`) — built from `Schedule` rows (planned sessions) with `status = SCHEDULED` whose `schedule_date` falls inside the upcoming period. Each scheduled session becomes a charge line at its planned rate.
- **Adjustment lines** (from `completedPeriod`) — compare what the previous Advance invoice charged for that period against what actually got delivered (approved session logs). Differences become positive or negative adjustment lines.
- **Carry-forward credit** — if the previous Advance invoice had a credit balance, it's pulled forward as a `CARRY_FORWARD_CREDIT` line.

Net result: a single invoice each cycle that bills the upcoming period *and* trues up the previous one. Over/underbilling settles automatically; no manual credit notes needed.

> **Late-approved sessions** that the 1st-of-month run missed are caught by a second command, **`billing:reconcile-advance`** (scheduled `monthlyOn(10)`). It reconciles the prior calendar month only and emits **one document by the net sign** — a settlement invoice (net > 0) or a single ledger credit note (net < 0), never both. See [`SCHOOL_INVOICE_SCHEDULE.md` §6.3](SCHOOL_INVOICE_SCHEDULE.md) for the full reference.
>
> **Auto-send:** when a schedule has `auto_send = true` and the generated invoice/bill is non-zero, the run emails it immediately after the generation transaction commits (outside the transaction, failures logged + swallowed). `billing_schedule_runs.auto_sent` records the outcome.

### Why Advance bills "future" sessions

This is the design — `Schedule` rows represent *committed* sessions, not delivered ones. Billing them in advance lets schools pay before the work happens. Reconciliation against actual `session_logs` happens on the next cycle. This is intentional and only applies to Advance schools (typically `is_private_student = true`).

---

## 6. Known issues in the first-run path

### 6.1 Skipped first period on Standard schedules created mid-period

**Symptom.** When a Standard schedule is created in the middle of a billing period, the first period boundary is silently skipped — no `billing_schedule_runs` row is ever recorded for it.

**Trace.** Settings: `frequency = semi_monthly`, `generation_day_type = fixed_delay`, `generation_delay_days = 3`, `min_grace_days = 2`. Schedule created on **5/4/2026** (mid first-half period).

| Step | Date | What happens |
|---|---|---|
| Create | 5/4 | `determineCurrentPeriodEnd(now=5/4)` → 5/15. `calculateNextRunDate(5/15)` → **`next_run_at = 5/18`**. |
| Cron 5/5–5/17 | — | `next_run_at = 5/18`, not yet due → skipped silently. |
| **Cron 5/18** | 5/18 | `due()` matches. `resolveCurrentPeriod()`: `last_period_end` is null → falls into else branch → `determineBillingPeriod(semi_monthly, now=5/18)` → **period = 5/16–5/31**, *not* 5/1–5/15. |
| Sweep | 5/18 | Query: `session_date <= 5/31`, no lower bound → pulls in any approved un-billed sessions from 5/1–5/15 *and* 5/16–5/18. They all land in the **5/16–5/31 bill**. |
| Advance | 5/18 | `last_period_end = 5/31`, `next_run_at = 6/3`. |

**Consequence.** No `billing_schedule_runs` row ever has `billing_period_start = 5/1` / `billing_period_end = 5/15`. The 5/1–5/15 period is invisible in run history. The work *does* get billed (via the open-ended sweep) but it's attributed to the wrong period and shows up in the wrong bill — which may confuse the school or therapist reading the bill ("Why is a 5/3 session on my 5/16–5/31 invoice?").

**Suggested fix.** Anchor the first period off the schedule's creation date, not `now()` at run time. Options:
- In `createSchedule()`, set `last_period_end` to the period end *before* the creation date so the first run computes its period via the `last_period_end + 1` branch (e.g. for a 5/4 creation, set `last_period_end = 4/30`, so first run bills 5/1–5/15).
- Or in `resolveCurrentPeriod()`, fall back to the schedule's `created_at` instead of `now()` when `last_period_end` is null.

### 6.2 Open-ended sweep can pull pre-creation sessions

**Symptom.** A brand-new schedule's first run can include sessions delivered *before the schedule existed*, because the sweep has no lower bound on `session_date`.

**Trace.** Therapist had approved sessions on 4/20, 4/25, 5/3 that were never billed (e.g. previously manual billing). On 5/4, a Semi-Monthly auto-schedule is created. First run on 5/18 sweeps `session_date <= 5/31` → all three sessions are pulled into the bill, even though April was not meant to be in scope.

**Whether this is wanted depends on intent:**
- **Onboarding/catch-up:** correct behaviour — backfill old un-billed work.
- **Clean cutover** ("start billing from May 1 forward, ignore April"): no way to express this today.

The intended gate is `billing_start_date`, but it's not wired — see §6.3.

**Suggested fix.** Once `billing_start_date` is honored, add `->where('session_date', '>=', $schedule->billing_start_date)` to both sweep queries.

### 6.3 `billing_start_date` is half-wired

The column exists on `billing_schedules`, is captured by the form, validated, and persisted — but **no production code reads it**. Verified via `grep` across `app/Domain`, `app/Jobs`, `app/Console`. The scheduler does not consult it when computing `next_run_at`, and the sweep does not use it to gate sessions.

**Consequence.** Admins setting "Billing Start Date" on the form get no behavioural change. This is the missing piece for §6.2; once it's read, the open-ended sweep stops being an issue for clean cutovers.

### 6.4 Schedule reactivated after a gap silently forgets missed periods

**Symptom.** If a schedule is deactivated mid-cycle and reactivated *after* the period it was due to bill has already closed, that period is silently skipped.

**Trace.** Schedule last ran for 4/1–4/15, `next_run_at = 4/18`. On 4/17 the admin deactivates it. On 5/20 they reactivate. First run after reactivation: `last_period_end = 4/15` → next period = 4/16–4/30. The 5/1–5/15 period (which has also closed by now) is never billed. The catch-up loop only advances **one period at a time**.

**Consequence.** Pausing a schedule across multiple period boundaries leaves silent gaps. The open-ended sweep does *eventually* pull the 5/1–5/15 sessions into some later bill (because of §6.2's no-lower-bound behavior), but again attributed to the wrong period.

**Suggested fix.** When `next_run_at + frequency_length < now()`, batch-catch-up by looping `resolveCurrentPeriod()` + sweep + advance until current. Or surface the gap as a warning in the run output so the admin knows to backfill manually.

### 6.5 Failed runs do not advance — retried next tick

`advanceSchedule()` is only called on success or skip paths (see [`BillingAutomationService:163-164`](../app/Domain/Billing/Services/BillingAutomationService.php#L163-L164) and [`231`](../app/Domain/Billing/Services/BillingAutomationService.php#L231)). A `FAILED` run leaves `next_run_at` and `last_period_end` unchanged, so the next cron tick re-attempts the same period. This is **correct behaviour** — listed here for completeness, not as a bug.

---

## 7. End-to-end example — Semi-Monthly, Fixed Delay = 3, created 5/4

Therapist `Jane`. `frequency = semi_monthly`, `generation_day_type = fixed_delay`, `generation_delay_days = 3`, `min_grace_days = 2`, `payment_terms_days = 30`. Schedule created on **5/4/2026**.

| Date | Cron behavior | DB state after run |
|---|---|---|
| 5/4 (create) | — | `next_run_at = 5/18`, `last_period_end = null` |
| 5/5 – 5/17 | `next_run_at = 5/18`, today < 5/18 → skipped | unchanged |
| **5/18** | due → run. `resolveCurrentPeriod()` → period **5/16–5/31** (bug §6.1: should be 5/1–5/15). Sweep `session_date <= 5/31` pulls in any un-billed sessions from both halves of May. Bill generated, `due_date = 5/18 + 30 = 6/17`. | `last_period_end = 5/31`, `next_run_at = 6/3` (5/31 + grace 2 → 6/2, Fixed Delay 3 → 6/3, max wins) |
| 5/19 – 6/2 | skipped | unchanged |
| **6/3** | due → run. `resolveCurrentPeriod()` → `last_period_end + 1 = 6/1` → period **6/1–6/15**. Sweep `session_date <= 6/15`. Bill `due_date = 6/3 + 30 = 7/3`. | `last_period_end = 6/15`, `next_run_at = 6/18` (6/15 + 3) |
| 6/4 – 6/17 | skipped | unchanged |
| **6/18** | due → run. Period **6/16–6/30**. Bill `due_date = 7/18`. | `last_period_end = 6/30`, `next_run_at = 7/3` |

After the first cycle, the loop self-stabilizes into a clean every-15-days cadence. Only the **first** run is anomalous (§6.1).

---

## 8. Run history & observability

Every run — success, skip, or failure — writes a row to `billing_schedule_runs` via [`logRun()`](../app/Domain/Billing/Services/BillingAutomationService.php#L333-L354). Fields captured per run:

- `billing_period_start` / `billing_period_end` — the period billed
- `generation_date` — when the run executed
- `status` — `SUCCESS`, `SKIPPED_NO_SESSIONS`, or `FAILED`
- `sessions_found` / `sessions_from_prior_periods` — sweep counts
- `adjustments_count` / `adjustment_total` — Advance mode only
- `carry_forward_amount` — Advance mode credit carryover
- `invoice_id` / `therapist_bill_id` — the document created
- `total_amount`
- `error_message` — failure reason
- `started_at` / `completed_at`

This is the audit trail. If a period appears to have been skipped, querying `billing_schedule_runs WHERE billing_schedule_id = ? ORDER BY billing_period_start` is the first place to look.

---

## 9. Tying back to the "pay therapist on calendar anchors" question

A common ask: "We pay therapists on the 15th + last day of the month, one period later. How do we configure the schedule?"

Two paths:

1. **Accounting pays on calendar anchors regardless of `due_date`.** Configure `frequency = semi_monthly`, any Generation Timing with a small delay (e.g. Fixed Delay = 3), and the bill will be ready in time. Accounting cuts checks on its own cadence. **No code change needed.**
2. **Accounting reads the bill's `due_date`.** Then no current combination works cleanly because `due_date = run_date + payment_terms_days` and the run date drifts by weekday or by month length. A new `FIXED_DATE` Generation Timing mode (anchor to 15th + EOM with a months-offset) is the clean fix. See [`THERAPIST_BILLING_SCHEDULE.md`](THERAPIST_BILLING_SCHEDULE.md#8-known-gaps--things-to-flag) for the implementation sketch.

The runtime mechanics above don't change between these two paths — only the configuration (path 1) or a new enum case (path 2).
