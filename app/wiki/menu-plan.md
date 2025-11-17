# NOVA Role Menus & Implementation Plan

## Overview

This document consolidates the agreed menu labels for each NOVA user role (Admin, Therapist, Student) and translates them into an implementation plan. Menu definitions align with the existing PRDs under `app/wiki/*` to ensure navigation reflects each persona’s responsibilities without renaming functional modules.

## Admin Menu

-   **Schools** — Onboard/edit schools, manage contacts, time zones, managers, and service rate overrides that feed SSA, scheduling, and invoicing workflows.
-   **Therapists** — Maintain therapist employment data, credentials, and per-service pay rates while controlling activation status and availability for assignments.
-   **Students** — Capture student demographics, guardians, school associations, and activation states that drive SSA creation, reminders, and reporting.
-   **Service Support Agreements** — Define SSA scope (services, frequency, minutes, therapist assignment), monitor lifecycle statuses, and reconcile served minutes.
-   **Services** — Steward the canonical service catalog, documentation requirements, and default client/provider rates referenced by schools, SSAs, therapists, and billing.

## Therapist Menu

-   **My Dashboard** — Landing view with widgets for upcoming sessions, pending notes, expiring SSAs, and billing reminders.
-   **My Students** — Caseload management with SSA context, goals, contact logs, and assignment notifications.
-   **My Schedule** — Calendar that enforces SSA frequency limits and student availability; entry point for creating sessions.
-   **My Sessions** — Workspace for drafting, editing, and submitting session records using service-specific documentation templates.
-   **My Bills** — Visibility into submitted sessions, approval status, disputes, and pay statements plus related notifications/tasks.

## Student Menu

-   **Dashboard** — Post-login summary of today’s schedule, overdue assignments, and recent progress against SSA goals.
-   **Schedule Calendar** — Read-only view of upcoming sessions sourced from SSA schedules and approved `sessions` records, with sanitized detail pages.
-   **Progress & Goals** — Dedicated area displaying SSA goals, milestone achievements, therapist-provided updates, and optional student reflections.

## Implementation Plan

### 1. Information Architecture & Access Control

-   Update shared navigation components to surface the new labels per role; confirm route names and middleware (`role:admin`, `role:therapist`, `role:student`) already gate access.
-   Review authorization policies (e.g., `StudentProfilePolicy`, `SessionPolicy`) to ensure menu exposure matches data-access rules, especially before opening student portal routes.

### 2. Admin Web App

-   Align existing admin routes (`/admin/schools`, `/admin/therapists`, `/admin/students`, `/admin/ssas`, `/admin/services`) with the renamed menu entries; ensure breadcrumbs/toasts reuse the new labels.
-   Audit each module for required metrics/components referenced in the menus (e.g., service rate overrides, SSA served minutes) and capture any gaps as backlog items.
-   Add smoke tests covering navigation clicks for each menu to guard against regressions when new modules are introduced.

### 3. Therapist Workspace

-   Extend `resources/views/layouts/navigation.blade.php` (therapist section) with the “My …” labels and link to planned routes (`/therapist/schedule`, `/therapist/sessions`, `/therapist/billing`).
-   Sequence feature delivery: start with dashboard widgets, then caseload enhancements, followed by schedule/session tooling, and finally billing visibility once finance APIs are ready.
-   Ensure session creation/submission flows respect SSA constraints and emit notifications/tasks that populate the dashboard/Bills views.

### 4. Student Portal

-   Stand up student-specific layout/navigation using the Dashboard, Schedule Calendar, and Progress & Goals entries once authentication for `role:student` is enabled.
-   Prioritize read-only schedule + progress views before tackling messaging/settings to keep scope manageable; reuse SSA/session data with visibility flags (`is_visible_to_student`).
-   Design guardian-access controls alongside the menu rollout to avoid exposing Progress data before permissions are enforced.

### 5. QA, Rollout, and Documentation

-   Update onboarding/playbook materials to reflect the new menu language for admins, therapists, and students.
-   Create end-to-end test scripts per persona that validate navigation, data visibility, and authorization boundaries.
-   Coordinate release communication so all user types know where to find their respective workflows once the menus go live.
