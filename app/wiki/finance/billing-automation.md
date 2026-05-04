NOVA · Billing Automation PRD
Version 1.1 · Last Updated: 06 Apr 2026

1. OVERVIEW
   The Billing Automation module automates invoice and therapist bill generation via configurable billing schedules. It supports both standard (post-service) and advance (pre-service) billing modes, with scheduled runs, manual triggers, and per-entity configuration overrides.

2. FUNCTIONAL SCOPE

   2.1 Billing Schedules
   Configurable schedules that auto-generate invoices or therapist bills.
   - Type: school_invoice or therapist_bill (BillingScheduleType enum)
   - Frequency: weekly, bi_weekly, semi_monthly, monthly (BillingFrequency enum)
   - Mode: standard or advance (BillingMode enum)
   - Generation day: day_of_week or fixed_delay (GenerationDayType enum)
   - Polymorphic: linked to School (for invoices) or TherapistProfile (for bills)
   - Active/inactive toggle
   - `isDue()` method determines if schedule should run

   2.2 Billing Schedule CRUD
   Full admin CRUD with server-side DataTable:
   - Create: select entity type, entity, frequency, mode, generation day
   - Edit: update schedule parameters
   - Toggle: activate/deactivate without delete
   - Run Now: manually trigger schedule execution
   - Run History: view past runs with status, timestamps, generated document links

   2.3 Automated Runs
   Scheduled command: `billing:generate` runs daily at 02:00
   - Calls `BillingAutomationService::processAllDueSchedules()`
   - Supports flags: --type, --schedule, --dry-run
   - Each run tracked in `billing_schedule_runs` table
   - Run status: success, skipped_no_sessions, failed (BillingScheduleRunStatus enum)

   2.4 Entity Billing Configuration
   Per-school or per-therapist billing config overrides:
   - Route: GET/POST/DELETE /admin/billing/entity-config/{entity_type}/{entity_id}
   - Falls back to global defaults from BillingSettings if no entity-specific config
   - Private student schools default to advance billing mode
   - AJAX-only JSON API used by entity detail page tabs

   2.5 Billing Settings (Global Defaults)
   Route: GET/PUT /admin/billing/settings
   - Global default billing parameters
   - Applies when no entity-specific configuration exists
   Service: `BillingSettingsService`

   2.6 Billing Reminders
   Scheduled command: `billing:send-reminders` runs daily at 08:00
   - Calls `BillingReminderService::processReminders()`
   - Sends `InvoiceReminderMail` for upcoming-due invoices
   - Sends `InvoiceOverdueMail` for overdue invoices
   - Supports --dry-run flag
   - Reminder types: upcoming_due, overdue, overdue_followup (BillingReminderType enum)
   - Tracked in `billing_reminders` polymorphic table

3. DATA MODEL
   Table: billing_schedules — `id`, `schedulable_type` (polymorphic: School or TherapistProfile), `schedulable_id`, `schedule_type` (BillingScheduleType), `frequency` (BillingFrequency), `billing_mode` (BillingMode), `generation_day_type` (GenerationDayType), `generation_day_of_week`, `generation_delay_days`, `min_grace_days` (default 2), `payment_terms_days` (default 30), `auto_generate`, `auto_send`, `is_active`, `last_run_at`, `last_period_end`, `next_run_at`, `billing_start_date`, `notes`, timestamps, `deleted_at`.
   Table: billing_schedule_runs — `id`, `billing_schedule_id`, `status` (BillingScheduleRunStatus), `invoice_id` or `therapist_bill_id` (nullable), `billing_period_start`, `billing_period_end`, `generation_date`, `sessions_found`, `adjustments_count`, `adjustment_total`, `carry_forward_amount`, `total_amount`, `error_message`, timestamps.
   Table: billing_settings — `id`, key-value global configuration.
   Table: billing_reminders — `id`, `remindable_type` (polymorphic: Invoice), `remindable_id`, `type` (BillingReminderType), `sent_at`, timestamps.

4. ROUTES
   Billing Schedules:
   - GET /admin/billing/schedules — list
   - POST /admin/billing/schedules/data — DataTable endpoint
   - GET /admin/billing/schedules/create — create form
   - POST /admin/billing/schedules — store
   - GET /admin/billing/schedules/{schedule}/edit — edit form
   - PUT /admin/billing/schedules/{schedule} — update
   - PATCH /admin/billing/schedules/{schedule}/toggle — activate/deactivate
   - POST /admin/billing/schedules/{schedule}/run — manual run
   - GET /admin/billing/schedules/{schedule}/history — run history
   - POST /admin/billing/schedules/{schedule}/history/data — history DataTable

   Entity Billing Config:
   - GET /admin/billing/entity-config/{entity_type}/{entity_id} — get config
   - POST /admin/billing/entity-config — create/update config
   - DELETE /admin/billing/entity-config/{entity_type}/{entity_id} — remove override

   Billing Settings:
   - GET /admin/billing/settings — edit form
   - PUT /admin/billing/settings — update

5. TECHNICAL IMPLEMENTATION
   Controllers: `BillingScheduleController`, `EntityBillingController`, `BillingSettingsController`
   Services: `BillingScheduleService`, `BillingAutomationService`, `AdvanceBillingService`, `BillingSettingsService`, `BillingReminderService`
   Policies: `BillingSchedulePolicy`
   Commands: `billing:generate` (daily 02:00), `billing:send-reminders` (daily 08:00)
   Mail: `InvoiceReminderMail`, `InvoiceOverdueMail`

6. INVOICE LINE TYPES (Advance Billing)
   InvoiceLineType enum supports advance billing adjustments:
   - session_charge — standard session charge
   - advance_scheduled — pre-billed scheduled sessions
   - adjust_no_show — no-show rate adjustment
   - adjust_cancel_billable — billable cancellation adjustment
   - adjust_cancel_non_billable — non-billable cancellation adjustment
   - adjust_extra_session — extra session beyond scheduled
   - adjust_rate_difference — rate difference adjustment
   - carry_forward_credit — credit carried from previous period
   Helpers: `isAdvanceCharge()`, `isAdjustment()`, `isCredit()`

───────────────────────────────────────────────────────────────────
7. HOW BILLING WORKS — CONCEPTS AND EXAMPLES
───────────────────────────────────────────────────────────────────

7.1 BILLING MODES
──────────────────

Standard (Postpaid)
  Invoice is generated AFTER the billing period ends, based on actual approved
  SessionLog records.
  "Pay after services are delivered."

Advance (Prepaid)
  Invoice is generated BEFORE the billing period starts, based on sessions that
  are currently SCHEDULED (not yet delivered). Each subsequent invoice also
  includes adjustments that reconcile what was pre-charged vs. what actually
  happened the week before.
  "Pay in advance for next week. Last week's actuals are settled on this invoice."

7.2 SCHEDULE CONFIGURATION FIELDS
───────────────────────────────────

  Field                  Column                   Notes
  ─────────────────────────────────────────────────────────────────────────────
  Billing Mode           billing_mode             standard or advance
  Frequency              frequency                weekly / bi_weekly / semi_monthly / monthly
  Generation Timing      generation_day_type      day_of_week or fixed_delay
  Day of Week            generation_day_of_week   0=Sun … 6=Sat (used with day_of_week)
  Generation Delay Days  generation_delay_days    Days after period end (used with fixed_delay)
  Min Grace Days         min_grace_days           Minimum buffer after period end. Default: 2.
                                                  NOT shown in the UI — hidden form field.
  Billing Start Date     billing_start_date       No invoices generated for periods before this.
  Payment Terms (Days)   payment_terms_days       Due date = generation date + this. Default: 30.
  Auto-generate          auto_generate            Scheduler auto-creates draft invoices.
  Auto-send              auto_send                Invoices sent automatically without admin review.

7.3 BILLING PERIODS BY FREQUENCY
──────────────────────────────────

  Frequency      Period boundaries
  ─────────────────────────────────────────────────────────────────
  weekly         Monday – Sunday
  bi_weekly      Two-week Mon–Sun blocks aligned to epoch 2026-01-05
  semi_monthly   1st–15th and 16th–last day of month
  monthly        1st–last day of month

7.4 GRACE PERIOD — HOW IT WORKS
─────────────────────────────────

  min_grace_days (default 2) is the minimum number of days that must pass after
  a billing period ends before an invoice can run. It is not configurable from
  the UI — it is submitted as a hidden value of 2.

  Purpose: gives a buffer for session logs to be submitted and approved before
  the invoice reads from them.

  Example (Generation Timing = Day of Week, Tuesday):

    Period ends:  Sunday Apr 13
    Earliest run: Apr 13 + 2 = Apr 15
    Target day:   Tuesday
    Apr 15 is already a Tuesday → next_run_at = Apr 15

  If Apr 15 had been a Wednesday instead:
    Walk forward to next Tuesday → next_run_at = Apr 21

  Example (Generation Timing = Fixed Delay, 3 days):

    Period ends:  Sunday Apr 13
    next_run_at:  Apr 13 + 3 = Apr 16

  If fixed_delay < min_grace_days, the grace period wins and the delay is
  extended to min_grace_days.

7.5 GENERATION TIMING OPTIONS
───────────────────────────────

  day_of_week:
    Finds the next occurrence of a specific weekday on or after
    (period_end + min_grace_days).

  fixed_delay:
    Adds a fixed number of days directly after period end.
    Must be >= min_grace_days (2) or grace days takes over.

7.6 STANDARD (POSTPAID) BILLING — FLOW
────────────────────────────────────────

  1. Wait until billing period ends.
  2. Query approved SessionLog records within that period for the school.
  3. Create one line item per session (session_charge).
  4. Generate draft invoice.
  5. Advance next_run_at to next cycle.

  Standard billing has no adjustments and no carry-forward. It bills only what
  actually happened.

7.7 ADVANCE (PREPAID) BILLING — FLOW
──────────────────────────────────────

  Each advance billing run produces a two-part invoice:

  Part 1 — Adjustments:
    Look at the PREVIOUS invoice's ADVANCE_SCHEDULED lines and compare each one
    against the actual SessionLog for that scheduled session.

  Part 2 — Advance charges:
    Query the schedules table for sessions in the UPCOMING period with
    status = SCHEDULED. Create one ADVANCE_SCHEDULED line per session at the
    calculated billing rate.

  The final invoice = Part 1 (adjustments) + Part 2 (advance charges).

  ADJUSTMENT LINE TYPES:
  ─────────────────────────────────────────────────────────────────────────────
  adjust_cancel_non_billable   Session had no log at all, or cancelled non-billably.
                               Effect: full credit (negative amount).

  adjust_rate_difference       Session occurred (SERVICES_ADMINISTERED) but actual
                               rate differs from what was pre-charged.
                               Effect: positive or negative difference.

  adjust_no_show               Session log outcome = NO_SHOW, and no-show rate
                               differs from advance rate.
                               Effect: positive or negative difference.

  adjust_cancel_billable       Session log outcome = BILLABLE_CANCELLATION, and
                               cancellation rate differs from advance rate.
                               Effect: positive or negative difference.

  adjust_extra_session         An approved session log exists for this school/period
                               that was never in any advance invoice (unscheduled
                               session that happened).
                               Effect: full charge (positive).

  carry_forward_credit         Previous invoice netted negative (school was over-charged).
                               Effect: credit applied at top of next invoice (negative).

7.8 ADVANCE BILLING — WORKED EXAMPLES
───────────────────────────────────────

  Settings used throughout: Weekly · Day of Week = Tuesday · Billing Start = Apr 1 2026
  Payment Terms = 30 days · Grace = 2 days (hidden default)

  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  INVOICE #1 — Tuesday Apr 7, 2026  (first ever run)
  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  No previous invoice exists → no adjustment lines.

  Completed period (week just ended): Apr 1 – Apr 6
  Upcoming period  (pre-charge):      Apr 7 – Apr 13

  Query: schedules WHERE school_id = X
                    AND schedule_date BETWEEN Apr 7 AND Apr 13
                    AND status = 'scheduled'

  Found:
    Speech Therapy (45 min)   Tue Apr 8   $120
    OT (30 min)               Thu Apr 10   $90
    Speech Therapy (45 min)   Fri Apr 11  $120

  ┌──────────────────────────────────────────────────────────────┐
  │ ADVANCE_SCHEDULED  Speech Therapy — Tue Apr 8 (45 min)  $120 │
  │ ADVANCE_SCHEDULED  OT — Thu Apr 10 (30 min)              $90 │
  │ ADVANCE_SCHEDULED  Speech Therapy — Fri Apr 11 (45 min) $120 │
  │ ─────────────────────────────────────────────────────────────│
  │ Total                                                    $330 │
  │ Due: May 7 2026 (Apr 7 + 30 days)                           │
  └──────────────────────────────────────────────────────────────┘

  After run: last_period_end = Apr 6
             next_run_at     = Apr 15 (first Tuesday on/after Apr 13 + 2)

  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  INVOICE #2 — Tuesday Apr 15, 2026
  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  Completed period (reconcile Invoice #1): Apr 7 – Apr 13
  Upcoming period  (pre-charge):           Apr 14 – Apr 20

  STEP 1 — Adjustments (compare Invoice #1 advance lines vs. session logs)

    Advance line: Apr 8 Speech $120
    → SessionLog found, outcome = SERVICES_ADMINISTERED, actual = $120
    → Difference < $0.01 → NO ADJUSTMENT

    Advance line: Apr 10 OT $90
    → SessionLog found, outcome = NO_SHOW, no-show rate = $0
    → adjust_no_show: $0 - $90 = -$90 CREDIT

    Advance line: Apr 11 Speech $120
    → NO SessionLog found at all
    → adjust_cancel_non_billable: -$120 CREDIT (full refund)

    Extra session detected: Apr 9 session log exists ($95, approved, no advance line)
    → adjust_extra_session: +$95

    Adjustment total: -$90 - $120 + $95 = -$115

  STEP 2 — Advance charges for Apr 14–20

    Found 2 sessions: Speech $120 + OT $90 = $210

  ┌──────────────────────────────────────────────────────────────────────┐
  │ ── ADJUSTMENTS (for Apr 7–13) ──────────────────────────────────    │
  │ adjust_no_show             OT — Thu Apr 10 (no-show adj.)    -$90   │
  │ adjust_cancel_non_billable Speech — Fri Apr 11 (no log)     -$120   │
  │ adjust_extra_session       Session — Wed Apr 9 (extra)       +$95   │
  │                                                                      │
  │ ── ADVANCE CHARGES (for Apr 14–20) ─────────────────────────────    │
  │ ADVANCE_SCHEDULED  Speech Therapy — Mon Apr 14 (45 min)     $120   │
  │ ADVANCE_SCHEDULED  OT — Wed Apr 16 (30 min)                  $90   │
  │ ─────────────────────────────────────────────────────────────────── │
  │ Adjustment total:  -$115                                            │
  │ Advance total:     +$210                                            │
  │ Net total:          $95                                             │
  │ Due: May 15 2026                                                    │
  └──────────────────────────────────────────────────────────────────────┘

  After run: last_period_end = Apr 13
             next_run_at     = Apr 22

7.9 CARRY FORWARD CREDITS
───────────────────────────

  If the net total of an invoice goes NEGATIVE (adjustments wipe out or exceed
  the advance charges), the invoice total is capped at $0.00 and the surplus is
  stored as carry_forward_balance on the invoice record.

  On the NEXT invoice, a carry_forward_credit line is automatically prepended:

  Example: Invoice #2 netted -$40 (all sessions cancelled, no advance charges)

  ┌──────────────────────────────────────────────────────────────┐
  │ Invoice #2 total = $0.00                                     │
  │ carry_forward_balance = $40.00                               │
  └──────────────────────────────────────────────────────────────┘

  Invoice #3:
  ┌──────────────────────────────────────────────────────────────┐
  │ carry_forward_credit  Credit from Invoice #2         -$40   │
  │ ADVANCE_SCHEDULED     Speech — Mon Apr 21 (45 min)   $120   │
  │ ADVANCE_SCHEDULED     OT — Wed Apr 23 (30 min)        $90   │
  │ ─────────────────────────────────────────────────────────────│
  │ Net total                                             $170   │
  └──────────────────────────────────────────────────────────────┘

7.10 SCHEDULE LIFECYCLE — next_run_at
───────────────────────────────────────

  After every successful run, advanceSchedule() updates three fields:

    last_run_at     → now()
    last_period_end → the period end that was just processed
    next_run_at     → calculated from the NEXT period end + grace + day-of-week

  The automation query fires when:
    is_active = true AND auto_generate = true AND next_run_at <= today

  "Due" schedules are picked up by the daily billing:generate command at 02:00.

7.11 SETTLEMENT INVOICE
─────────────────────────

  When a school stops advance billing, a settlement invoice is generated that:
  - Contains ONLY adjustment lines (no advance charges for a future period)
  - Reconciles the final completed period against the last advance invoice
  - Applies any remaining carry_forward_credit from the last invoice

7.12 AUTOMATION SETTINGS
──────────────────────────

  auto_generate ON  → Scheduler automatically creates draft invoices on next_run_at.
  auto_generate OFF → Schedule tracks next_run_at but nothing is created until
                      an admin manually triggers "Run Now".

  auto_send ON      → After generation, invoice is immediately sent to the school.
  auto_send OFF     → Invoice stays as draft for admin review before sending.

───────────────────────────────────────────────────────────────────
8. KEY CODE FILES
───────────────────────────────────────────────────────────────────

  app/Domain/Billing/Services/AdvanceBillingService.php
    Core advance billing logic — builds adjustment lines and advance charge lines,
    creates the invoice, handles carry-forward, logs the run.

  app/Domain/Billing/Services/BillingScheduleService.php
    Period boundary calculation (weekly/bi-weekly/semi-monthly/monthly),
    next_run_at calculation, schedule advancement after a run.

  app/Domain/Billing/Services/BillingAutomationService.php
    Routes due schedules to standard or advance processor.

  app/Models/BillingSchedule.php
    Model — schedule config, isDue(), scopeDue(), scopeActive().

  app/Console/Commands/BillingGenerate.php
    CLI: billing:generate {--type=all} {--schedule=} {--dry-run}

  resources/views/admin/billing/_entity-billing-tab.blade.php
    School-level billing configuration UI (min_grace_days sent as hidden field = 2).
