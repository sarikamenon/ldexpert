NOVA · Billing Automation PRD
Version 1.0 · Last Updated: 26 Mar 2026

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
   Table: billing_schedules — `id`, `schedulable_type` (polymorphic: School or TherapistProfile), `schedulable_id`, `type` (BillingScheduleType), `frequency` (BillingFrequency), `mode` (BillingMode), `generation_day_type` (GenerationDayType), `generation_day_value`, `is_active`, `last_run_at`, timestamps, `deleted_at`.
   Table: billing_schedule_runs — `id`, `billing_schedule_id`, `status` (BillingScheduleRunStatus), `invoice_id` or `therapist_bill_id` (nullable), `period_start`, `period_end`, `error_message`, timestamps.
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
   Services: `BillingScheduleService`, `BillingAutomationService`, `BillingSettingsService`, `BillingReminderService`
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
