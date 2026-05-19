# NOVA Knowledge Base

Last Updated: 26 Mar 2026

NOVA is LD Expert's therapy services management platform that coordinates schools, therapists, students, and financial operations. This wiki holds the product requirements for every module, capturing both the **current implementation** in this repository and the **planned capabilities**.

## How to Use This Wiki

-   PRDs live inside domain folders under `app/wiki` (`admin`, `finance`, `therapist`, `student`). Each file follows the same structure: purpose, personas, current vs. planned scope, domain model, routes, workflows, and open risks.
-   Cross-cutting concepts (roles, notifications, integrations) are referenced inside each PRD and summarized in the "Shared Concepts" section below.
-   Update PRDs alongside code changes. When a module ships, move items from "Planned" to "Current".

## Module Index

### Admin Operations (`app/wiki/admin`)

-   [Admin Dashboard](./admin/dashboard.md) — landing page with key metrics, critical alerts, charts, operational metrics, and quick actions.
-   [Manage Schools](./admin/schools.md) — onboard, edit, activate/deactivate schools plus contracted services/rates and calendar events.
-   [Manage Therapists](./admin/therapists.md) — lifecycle of therapist providers, credentials, compensation rates, positions.
-   [Manage Students](./admin/students.md) — admin-only student master data, guardians, import, comments, and documents.
-   [Student Import](./admin/student-import.md) — bulk import students via CSV (NOVA, RSM, MARVIN, TUTORBIRD formats).
-   [Student Documents](./admin/student-documents.md) — polymorphic document storage for students and session logs.
-   [Manage SSA](./admin/ssa.md) — service agreements linking students to services/therapists with status lifecycle and import.
-   [Manage Services](./admin/services.md) — service catalog definitions with billing metadata and delivery modes.
-   [Session Logs](./admin/session-logs.md) — review, approve, send back, cancel, and import session logs.
-   [Schedule Calendar](./admin/schedule-calendar.md) — FullCalendar.js admin view of all schedules across therapists.
-   [Contracts](./admin/contracts.md) — school and therapist contracts with service rates and no-show rates.
-   [Leads (CRM)](./admin/leads.md) — prospective student tracking from inquiry through enrollment with follow-up reminders.
-   [Reports](./admin/reports.md) — SSA utilization, caseload, and expiration reports with CSV export.
-   [Analytics](./admin/analytics.md) — data visualization dashboards (overview, school, therapist) with date range filters.
-   [Notifications](./admin/notifications.md) — in-app notification center with mark-read and delete.
-   [Settings & Configuration](./admin/settings.md) — system settings, positions, service aliases, expense categories.
-   [Schedule Reminders](./admin/schedule-reminders.md) — automated 48h/2h email reminders for upcoming sessions.
-   [Admin Menu](./admin/menu.md) — full admin navigation structure from config/navigation.php.

### Financial Operations (`app/wiki/finance`)

-   [Invoice Schools](./finance/invoicing.md) — AR pipeline: create invoices from approved sessions, PDF generation, email delivery, Stripe payment links.
-   [Bill Therapists](./finance/billing.md) — AP pipeline: create bills from approved sessions, PDF generation, email delivery, therapist portal access.
-   [Payments](./finance/payments.md) — invoice and therapist bill payment recording, allocation, and Stripe online payment gateway.
-   [Billing Automation](./finance/billing-automation.md) — automated billing schedules, entity config overrides, billing reminders, and advance billing.
-   [Expenses](./finance/expenses.md) — operating expense tracking with categories and date filtering.
-   [Ledger Accounts](./finance/ledger.md) — double-entry accounting view of school and therapist financial transactions.
-   [Pay Stub Report](./finance/pay-stub-report.md) — year-filtered therapist payment summaries with PDF download.
-   [Accounting System](./finance/accounting.md) — planned comprehensive accounting (chart of accounts, fiscal periods, financial reports).
-   [RSM/CAVA Sync](./finance/sync.md) — planned pluggable sync framework for external data sources.

### Therapist Experience (`app/wiki/therapist`)

-   [Therapist Workspace](./therapist/workspace.md) — dashboard, schedule calendar with recurring support, read-only caseload/SSA views, student comments.
-   [Session Management & Logs](./therapist/session-logs.md) — complete session lifecycle: create, edit, submit, with dual billing calculations and document attachments.
-   [Substitute Coverage](./therapist/sub-coverage.md) — raise sub requests with multi-invitee picker, race-safe accept, sub-SSA snapshot, importer auto-resolution, automatic expiry.
-   [Student Comments](./therapist/student-comments.md) — contextual notes on student records visible to admins and assigned therapists.
-   [Therapist Menu](./therapist/menu.md) — full therapist navigation structure from config/navigation.php.

### Student Experience (`app/wiki/student`)

-   [Student Portal](./student/portal.md) — minimal dashboard showing today's schedules. Full portal (schedule calendar, progress tracking) is planned.
-   [Student Menu](./student/menu.md) — planned student navigation structure.

## Shared Concepts

-   **Roles & Authorization** — `App\Enums\Role` (admin, therapist, student, parent). 22 Policy classes enforce access. `RoleMiddleware` protects routes.
-   **User Status Lifecycle** — `App\Enums\UserStatus` (active, inactive). Status toggling without hard deletes.
-   **Soft Deletes & Auditing** — All models use soft deletes (`deleted_at`). Activity logging for audit trail.
-   **Notifications & Emails** — 12 Mailable classes covering welcome emails, schedule reminders, billing, imports, and follow-ups. See [Email Notifications PRD](./email-notifications.md).
-   **Integrations** — Sentry (errors), Stripe (payments), email delivery (SMTP/SES/Postmark). See [Integrations](./integrations.md).

## Current Implementation Snapshot

-   **38 Admin Controllers** covering schools, therapists, students, SSAs, services, session logs, contracts, leads, billing, invoicing, payments, expenses, ledger, reports, analytics, settings, notifications, and schedule calendar.
-   **6 Therapist Controllers** covering dashboard, schedules, session logs, SSAs, students, and billing portal.
-   **1 Student Controller** for the dashboard with today's schedule display.
-   **43 Enums** defining all status types, billing modes, rate types, import types, etc.
-   **12 Mailable classes** for email notifications across all modules.
-   **4 Scheduled Commands** for reminders (30min), follow-ups (daily), billing generation (daily 02:00), and billing reminders (daily 08:00).
-   **3 Queued Jobs** for async CSV import processing (students, SSAs, session logs).
-   **2 Events + 1 Listener** for schedule notification dispatch.
-   **Stripe Payment Gateway** for online invoice payments with checkout sessions and webhook logging.

## Operations & Integrations

See [Operations](./operations.md) for scheduler, queue workers, jobs, and commands.
See [Integrations](./integrations.md) for Sentry, email delivery, Stripe, logging, and frontend libraries.
See [Email Notifications](./email-notifications.md) for all 12 mailable classes with trigger details.
