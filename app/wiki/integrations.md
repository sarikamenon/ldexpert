# Platform Integrations

This page documents the third-party services currently wired into the codebase so new developers can configure environments quickly and understand runtime dependencies.

## Sentry (Error Monitoring)

-   Package: `sentry/sentry-laravel`, configured in `config/sentry.php` and Monolog channel `sentry`.
-   Key env vars: `SENTRY_LARAVEL_DSN` (or `SENTRY_DSN`), `SENTRY_ENVIRONMENT`, `SENTRY_RELEASE`, `SENTRY_TRACES_SAMPLE_RATE` (default 0.1), `SENTRY_PROFILES_SAMPLE_RATE` (default 0.0), `SENTRY_SEND_DEFAULT_PII` (default false).
-   Ignored exceptions: auth/authorization failures, validation errors, missing models, 404/405/429 to avoid noise.
-   Usage: enabled automatically via Laravel auto-discovery; use `Log::channel('sentry')` for explicit logging if needed.

## Email Delivery

-   Default mailer: `log` (writes emails to storage logs in non-prod).
-   Supported transports (configurable via env): SMTP (`MAIL_*` + optional `MAIL_URL`/`MAIL_SCHEME`), SES, Postmark, Resend, Sendmail, Failover, Roundrobin.
-   Global From: `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
-   Mailables in use:
    -   `WelcomeTherapistMail` and `WelcomeStudentMail` (plain welcome + credentials).
    -   `ScheduleReminderMail` (48h/2h reminders; queued).
    -   `ScheduleNotificationMail` (create/update notifications; queued).
-   Queueing: reminders and notifications are enqueued via `Mail::queue()`/`Mail::to(...)->queue(...)`, so a queue worker must be running.

## Logging & Alerts

-   Default channel: `stack`, controlled by `LOG_STACK` (comma-separated channels). Typical local: `single`; production can include `sentry` and/or `slack`.
-   Slack: optional webhook via `LOG_SLACK_WEBHOOK_URL` with emoji/username overrides.
-   Papertrail: optional via `PAPERTRAIL_URL`/`PAPERTRAIL_PORT` and `LOG_PAPERTRAIL_HANDLER`.
-   Log level: `LOG_LEVEL` (default `debug`); Sentry handler defaults to `critical`.

## Queues & Async Work

-   Default queue connection: `database` (see `config/queue.php`). Jobs stored in `jobs` table; failed jobs in `failed_jobs`.
-   Other supported drivers (env-driven): `sqs`, `redis`, `beanstalkd`, `sync`, `failover`.
-   Dev helper: `composer dev` runs `php artisan queue:listen --tries=1` alongside the app.

## Front-end Libraries

-   Installed via `package.json`: Tailwind, Alpine, Axios, Select2, SweetAlert2, Vite.
-   CDN usage: Chart.js is loaded from JSDelivr in admin analytics/therapist views.
-   UX conventions: SweetAlert2 is the standard for confirmations/toasts; Select2 is used for rich selects; Tailwind for styling.
