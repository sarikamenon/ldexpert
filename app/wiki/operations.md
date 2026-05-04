# Operations & Background Jobs

Last Updated: 26 Mar 2026

## Scheduler (Cron)

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `schedule:send-reminders` | Every 30 minutes | Sends email reminders 48h and 2h before upcoming schedules to therapists and student contacts. |
| `leads:send-follow-up-reminders` | Daily at 08:00 | Sends `LeadFollowUpReminderMail` to admins for leads with today's follow-up date. |
| `billing:generate` | Daily at 02:00 | Auto-generates invoices and therapist bills from active billing schedules via `BillingAutomationService`. Supports `--type`, `--schedule`, `--dry-run`. |
| `billing:send-reminders` | Daily at 08:00 | Sends upcoming-due and overdue invoice reminders via `BillingReminderService`. Supports `--dry-run`. |

## Queued Jobs

| Job | Trigger | Timeout | Completion Email |
|-----|---------|---------|------------------|
| `ProcessStudentImportJob` | Admin uploads student CSV | Default | `StudentImportCompletedMail` |
| `ProcessSSAImportJob` | Admin uploads SSA CSV | Default | `SSAImportCompletedMail` |
| `ProcessSessionLogImportJob` | Admin uploads session log CSV | 600s | None |

All import jobs follow the same pattern: set status → PROCESSING → call import service → set status → COMPLETED/FAILED. Failure handler sets status → FAILED with error message.

## Queue Workers

-   **Default connection:** `database` with `jobs` / `failed_jobs` tables.
-   **Dev script:** `composer dev` runs `php artisan queue:listen --tries=1`.
-   **Prod guidance:** run long-lived `queue:work` alongside Laravel Scheduler (`php artisan schedule:run` every minute via cron).
-   **Failure handling:** failed jobs logged to `failed_jobs` (driver `database-uuids`).

## Events & Listeners

| Event | Listener | Queued | Action |
|-------|----------|--------|--------|
| `ScheduleCreated` | `SendScheduleNotification` | Yes | Sends `ScheduleNotificationMail` to therapist + student schedule_email |
| `ScheduleUpdated` | `SendScheduleNotification` | Yes | Same as above with type `updated` |

## On-demand Commands

-   `user:create-welcome {name} {email} [--password=] [--role=]` — creates a user and sends role-specific welcome email.
-   Standard artisan maintenance commands available via authenticated admin routes.
