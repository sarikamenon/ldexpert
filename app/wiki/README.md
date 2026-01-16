# NOVA Knowledge Base

NOVA is LD Expert's bird-care platform that coordinates schools, therapists, students, and financial operations. This wiki holds the product requirements for every module, capturing both the **current implementation** in this repository and the **planned capabilities** requested for the NOVA launch.

## How to Use This Wiki

-   PRDs now live inside domain folders under `app/wiki` (`admin`, `finance`, `therapist`, `student`). Each file follows the same structure: purpose, personas, current vs. planned scope, domain model (DB + states), API/routes, workflows, integrations, metrics, and open risks.
-   Cross-cutting concepts (roles, authentication, notifications, auditing) are referenced inside each PRD and summarized in the "Shared Concepts" section below.
-   Update PRDs alongside code changes. When a module ships, move items from "Planned" to "Current" and link to the relevant pull request.

## Module Index

### Admin Operations (`app/wiki/admin`)

-   [Manage Schools](./admin/schools.md) — onboard, edit, activate/deactivate schools plus contracted services/rates.
-   [Manage Therapists](./admin/therapists.md) — lifecycle of therapist providers, credentials, compensation rates, availability sync.
-   [Manage Students](./admin/students.md) — admin-only student master data and guardians; powers SSA creation, scheduling, and billing.
-   [Student Import](./admin/student-import.md) — bulk import students via CSV files with validation, duplicate detection, and error reporting.
-   [Student Documents](./admin/student-documents.md) — upload, manage, and track documents associated with students or session logs.
-   [Manage SSA](./admin/ssa.md) — service agreements per student outlining services, duration, frequency, therapist assignments. **Currently links existing students to services/therapists; future iterations may embed student intake directly into the SSA flow.**
-   [Manage Services](./admin/services.md) — catalog definitions (SLP, OT, PT, Progress Reports, IEP meetings, etc.) with billing metadata.

### Financial Operations (`app/wiki/finance`)

-   [Invoice Schools / Private Families](./finance/invoicing.md) — AR pipeline derived from SSA schedules and service delivery.
-   [Bill Therapists](./finance/billing.md) — AP pipeline to compensate therapists per delivered service.
-   [RSM/CAVA Sync](./finance/sync.md) — import canonical records (students, sessions, rates) to eliminate double entry.

### Therapist Experience (`app/wiki/therapist`)

-   [Therapist Workspace](./therapist/workspace.md) — dashboard plus implemented schedule calendar with recurring schedule support, read-only caseload/SSA views, and student comments.
-   [Session Logs](./therapist/session-logs.md) — log therapy sessions with notes, billing metadata, and document attachments.
-   [Student Comments](./therapist/student-comments.md) — add contextual comments on student records visible to admins and assigned therapists.

### Student Experience (`app/wiki/student`)

-   [Student Portal](./student/portal.md) — schedules, past sessions, progress reporting aligned to SSA goals.

## Shared Concepts

-   **Roles & Authorization** — Implemented via `App\Enums\Role` and policies (e.g., `StudentProfilePolicy`). Future PRDs should reference these enums instead of duplicating logic.
-   **User Status Lifecycle** — `App\Enums\UserStatus` keeps users active/inactive; planned modules should reuse the same state machine.
-   **Soft Deletes & Auditing** — Core tables already include `deleted_at`. New tables must do the same and emit domain events for downstream sync.
-   **Notifications & Emails** — Welcome emails (see `WelcomeUserMail`) exist for student onboarding; extend the same pattern for therapists, SSA milestones, billing events.
-   **Integrations** — All modules that ingest or push data externally should describe API contracts (REST, SFTP, etc.) and retry/logging expectations in their PRDs.

Refer back to this index whenever you add, refine, or discuss a module PRD.

## Current Implementation Snapshot

-   **Routing & Roles** — Admin routes live under `/admin/*` with `role:admin`; therapist-facing routes live under `/therapist/*` with `role:therapist`; student dashboard is under `/student/dashboard`.
-   **Dashboards & Metrics** — Admin dashboards expose school/therapist/student/SSA metrics; school detail tabs lazy-load students, therapists, and SSAs via the respective services and DTO filters.
-   **Scheduling UX** — Therapists manage schedules via `Therapist\ScheduleController` (calendar, CRUD, billing status bulk updates) with recurring schedule support (daily, weekly, bi-weekly, monthly). SSAs can be assigned/unassigned to therapists from admin routes.
-   **Student Import** — Admins can bulk import students via CSV with support for multiple import types (NOVA, RSM, MARVIN), row-level validation, and comprehensive error reporting.
-   **Student Documents** — Polymorphic document storage for students and session logs with S3 storage, type categorization, and authorization-based access control.
-   **Student Comments** — Shared comment system for admins and therapists to add contextual notes on student records with visibility based on SSA assignments.
-   **Contracts** — School and therapist contracts have their own controllers and status transitions under `/admin/contracts/*`.

## Operations & Integrations

-   **Error Monitoring** — Sentry is wired via `sentry/sentry-laravel` and `config/sentry.php`. DSN, sampling, and PII flags are fully environment-driven.
-   **Email Delivery** — Default mailer is `log`; SMTP/Postmark/SES/Resend are available via `config/mail.php` and `config/services.php`. Welcome mails exist for students and therapists; schedule reminders and schedule notifications are queued.
-   **Background Work** — `schedule:send-reminders` runs every 30 minutes (see `app/Console/Kernel.php`) and queues reminder emails 48h and 2h before sessions. Queue default is `database`; workers should be running alongside the scheduler.
-   **Front-end Libraries** — Vite + Tailwind + Alpine power the UI. Select2 and SweetAlert2 are installed; Chart.js is loaded via CDN where charts are rendered in admin views.

See `app/wiki/integrations.md` and `app/wiki/operations.md` for environment variables, runbooks, and cron details.
