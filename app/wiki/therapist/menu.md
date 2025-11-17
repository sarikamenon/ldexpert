# Therapist Menu Specification

## Purpose
Define the therapist-facing navigation (“My …” labels) for the NOVA workspace so caseload, scheduling, documentation, and billing flows are easy to discover. This document anchors the UI plan to the Therapist Workspace PRD in `app/wiki/therapist/workspace.md`.

## Menu Items
| Menu | Description | Planned Routes | Notes |
| --- | --- | --- | --- |
| My Dashboard | Landing page with widgets for upcoming sessions, pending notes, expiring SSAs, and billing reminders. | `/therapist/dashboard` | Extend existing dashboard controller to include new widgets fed by Sessions, SSA, Billing data. |
| My Students | Caseload management list showing assigned students, SSA context, goals, and contact logs with CRUD limited by policy. | `/therapist/students`, `/therapist/students/{user}`, `/therapist/students/{user}/edit` | Reuses current student CRUD screens; ensure assignments filter results. |
| My Schedule | Calendar view (week/month) for booking sessions that respect SSA frequency and student availability. | `/therapist/schedule` | Requires new scheduling UI and integration with SSA + Sessions modules. |
| My Sessions | Workspace to create, edit, and submit session records using service-specific documentation templates. | `/therapist/sessions`, `/therapist/sessions/{session}`, `/therapist/sessions/{session}/submit` | Session submission locks notes and emits notifications/audit logs. |
| My Bills | Transparency into submitted sessions, approval status, disputes, and pay statements, plus notification/task hooks. | `/therapist/billing`, `/therapist/billing/{bill}` | Depends on Billing module; should surface disputes and confirmations. |

## Implementation Notes
1. **Navigation Layout** – Update therapist layout to render these labels and highlight active entries. Ensure routes use `auth` + `role:therapist`.
2. **Data Sources** – Dashboard, Schedule, Sessions, and Bills rely on SSA, Sessions, and Billing APIs; plan data contracts before UI work.
3. **Tasks & Notifications** – Notifications triggered by SSA assignments, schedule changes, or billing events should surface badges on the relevant menu items (e.g., My Sessions).
4. **Responsive UX** – Therapists may access NOVA on tablets. Verify each menu destination maintains usability down to 1024px width.
5. **Testing** – Add feature specs to confirm role-based navigation, ensure unauthorized roles cannot hit `/therapist/*`, and verify that menu badges reflect pending tasks.

## Backlog / Open Questions
- Define whether “My Students” retains create/edit capabilities once admins control the master student record.
- Determine offline support or calendar sync needs before finalizing the My Schedule experience.
- Clarify how My Bills handles therapists with multiple employment types (W2 + 1099) and whether statements should split by type.

