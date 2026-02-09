# Operations & Background Jobs

## Scheduler (Cron)

-   **Command:** `schedule:send-reminders`
-   **Frequency:** Every 30 minutes (`app/Console/Kernel.php`).
-   **Purpose:** Sends email reminders 48 hours and 2 hours before upcoming schedules.
-   **Implementation:** `App\Console\Commands\SendScheduleReminders`
    -   Uses `ScheduleRepositoryInterface::getSchedulesInWindow()` to fetch schedules in 30-minute windows.
    -   Resolves recipient timezones via `UserTimezoneService` (checks profile timezone, then user timezone, then UTC).
    -   Deduplicates recipients by email and queues `ScheduleReminderMail` for therapists, students, and guardians.
-   **Operational requirements:** queue worker must be running for queued mails; ensure mailer is configured (defaults to `log`).

## Queue Workers

-   **Default connection:** `database` with `jobs` / `failed_jobs` tables.
-   **Dev script:** `composer dev` runs `php artisan queue:listen --tries=1`.
-   **Prod guidance:** run long-lived `queue:listen` or `queue:work` alongside Laravel Scheduler (`php artisan schedule:work` or system cron `php artisan schedule:run` every minute).
-   **Failure handling:** failed jobs logged to `failed_jobs` (driver `database-uuids`).

## On-demand Commands

-   `user:create-welcome {name} {email} [--password=] [--role=]` — creates a user via `UserService` and sends the role-specific welcome email (currently implemented for therapists).
-   Other artisan maintenance commands (cache clear, etc.) are available via authenticated `/cache/clear` route for admins.
