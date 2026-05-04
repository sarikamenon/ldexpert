# Platform Integrations

Last Updated: 26 Mar 2026

This page documents the third-party services and internal integrations wired into the codebase.

## Sentry (Error Monitoring)

-   Package: `sentry/sentry-laravel`, configured in `config/sentry.php` and Monolog channel `sentry`.
-   Key env vars: `SENTRY_LARAVEL_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_RELEASE`, `SENTRY_TRACES_SAMPLE_RATE` (default 0.1), `SENTRY_PROFILES_SAMPLE_RATE`, `SENTRY_SEND_DEFAULT_PII`.
-   Ignored exceptions: auth/authorization failures, validation errors, missing models, 404/405/429.
-   Middleware: `SentryContext` sets user scope (id, email, username) on every authenticated request.

## Email Delivery

-   Default mailer: `log` (writes to storage logs in non-prod).
-   Supported transports: SMTP, SES, Postmark, Resend, Sendmail, Failover, Roundrobin.
-   Global From: `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
-   **All Mailables (12 total):**
    -   `WelcomeTherapistMail` — welcome email with credentials to new therapists.
    -   `WelcomeStudentMail` — welcome email with credentials to new students.
    -   `ScheduleReminderMail` — 48h/2h reminders to therapists and student contacts (queued).
    -   `ScheduleNotificationMail` — create/update notifications to therapists and student contacts (queued).
    -   `StudentImportCompletedMail` — import completion notification to admin.
    -   `SSAImportCompletedMail` — SSA import completion notification to admin.
    -   `SessionLogSentBackMail` — notification to therapist when session log is sent back.
    -   `LeadFollowUpReminderMail` — daily follow-up reminder to lead creator (queued).
    -   `InvoiceMail` — invoice delivery to school with PDF attachment.
    -   `InvoiceReminderMail` — upcoming due date reminder to school.
    -   `InvoiceOverdueMail` — overdue invoice notification to school.
    -   `TherapistBillMail` — therapist bill delivery with PDF attachment.
-   Queueing: schedule reminders, schedule notifications, and lead follow-up reminders are queued via `Mail::queue()`.

## Stripe Payment Gateway

-   Package: Stripe PHP SDK.
-   Env vars: `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`.
-   Usage: online invoice payments via payment links. Schools receive a payment URL with their invoice email.
-   Models: `PaymentGatewayTransaction` (tracks checkout sessions), `PaymentGatewayLog` (logs outgoing/incoming requests).
-   Routes: `/payment/{token}` (public, unauthenticated).

## Logging & Alerts

-   Default channel: `stack`, controlled by `LOG_STACK`. Typical local: `single`; production includes `sentry`.
-   Slack: optional webhook via `LOG_SLACK_WEBHOOK_URL`.
-   Papertrail: optional via `PAPERTRAIL_URL`/`PAPERTRAIL_PORT`.

## Queues & Async Work

-   Default queue connection: `database`. Jobs in `jobs` table; failed jobs in `failed_jobs`.
-   Other supported drivers: `sqs`, `redis`, `beanstalkd`, `sync`, `failover`.

## Front-end Libraries

-   Installed via npm: Tailwind, Alpine.js, Axios, jQuery, Select2, SweetAlert2, Vite.
-   CDN: Chart.js 4.4.0 (admin dashboards and analytics).
-   UX conventions: SweetAlert2 for confirmations/toasts; Select2 for rich selects; DataTables for server-side tables.
