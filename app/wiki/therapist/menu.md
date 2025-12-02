# Therapist Menu Specification

## Purpose
Define the therapist-facing navigation (“My …” labels) for the NOVA workspace so caseload, scheduling, documentation, and billing flows are easy to discover. This document anchors the UI plan to the Therapist Workspace PRD in `app/wiki/therapist/workspace.md`.

## Menu Items
| Menu | Description | Current / Planned Routes | Notes |
| --- | --- | --- | --- |
| My Dashboard | Landing page with widgets for upcoming sessions, pending notes, expiring SSAs, and billing reminders. | `/therapist/dashboard` | **Implemented.** Needs extension to include widgets fed by Sessions, SSA, Billing data. |
| My Schedule | Calendar view (week/month) for booking sessions that respect SSA frequency and student availability. | `/therapist/schedule`, `/therapist/schedule/calendar`, `/therapist/schedule/create`, `/therapist/schedule/{id}` | **Implemented (first iteration).** Backed by `schedules` table and SSA assignments; future work will add richer SSA-limit enforcement and session-note shortcuts. |
| My Students | Read-only caseload view of students assigned via SSA, with quick links into SSA and schedule views. | `/therapist/students`, `/therapist/students/{student}` | **Implemented.** Therapists cannot create or edit students; all student data is admin-managed. |
| My SSAs | Read-only SSA summary list for the therapist’s assigned agreements. | `/therapist/ssas`, `/therapist/ssas/{ssa}` | **Implemented (read-only).** Used as context for scheduling and documentation. |
| My Sessions | Workspace to create, edit, and submit session records using service-specific documentation templates. | `/therapist/sessions`, `/therapist/sessions/{session}`, `/therapist/sessions/{session}/submit` | **Planned.** Session submission will lock notes and emit notifications/audit logs. |
| My Bills | Transparency into submitted sessions, approval status, disputes, and pay statements, plus notification/task hooks. | `/therapist/billing`, `/therapist/billing/{bill}` | **Planned.** Depends on Billing module; should surface disputes and confirmations. |

## Implementation Notes
1. **Navigation Layout** – Update therapist layout to render these labels and highlight active entries. Ensure routes use `auth` + `role:therapist`.
2. **Data Sources** – Dashboard, Schedule, Sessions, and Bills rely on SSA, Sessions, and Billing APIs; plan data contracts before UI work.
3. **Tasks & Notifications** – Notifications triggered by SSA assignments, schedule changes, or billing events should surface badges on the relevant menu items (e.g., My Sessions).
4. **Responsive UX** – Therapists may access NOVA on tablets. Verify each menu destination maintains usability down to 1024px width.
5. **Testing** – Add feature specs to confirm role-based navigation, ensure unauthorized roles cannot hit `/therapist/*`, and verify that menu badges reflect pending tasks.

## Backlog / Open Questions
- **Student Management Removed** — Old "My Students" CRUD has been removed. Students are now managed by admins via SSA workflow. Therapists will have read-only access to assigned students through Sessions/Schedule views.
- Determine offline support or calendar sync needs before finalizing the My Schedule experience.
- Clarify how My Bills handles therapists with multiple employment types (W2 + 1099) and whether statements should split by type.

