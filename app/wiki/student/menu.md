# Student Menu Specification

## Purpose
Capture the student-portal navigation that aligns with the Student Portal PRD (`app/wiki/student/portal.md`). Menu options focus on surfacing dashboards, schedules, and SSA goal progress while respecting privacy, guardian permissions, and feature rollout sequencing.

## Menu Items
| Menu | Description | Planned Routes | Notes |
| --- | --- | --- | --- |
| Dashboard | Home page after login with today’s schedule, overdue assignments, recent progress updates, and announcements. | `/student/dashboard` | Requires student authentication + forced profile confirmation on first login. |
| Schedule Calendar | Read-only calendar of upcoming sessions with ability to open session details (sanitized notes, attachments flagged as student-visible). | `/student/schedule`, `/student/sessions/{session}` | Pulls from SSA schedule + approved Sessions; honors `is_visible_to_student`. |
| Progress & Goals | Dedicated view of SSA goals, milestone progress, and therapist-provided summaries, optionally collecting student reflections. | `/student/goals` | Pulls from SSA goals + session note metadata; reflection storage via `student_feedback`. |

## Implementation Notes
1. **Navigation Shell** – Create a student-specific layout with the three menu items and ensure middleware `auth` + `role:student` guards routes (future guardian middleware attaches via join table).
2. **Data Visibility** – Add per-field visibility flags so sensitive notes or diagnoses only display when marked shareable. Guardian view must inherit the student’s visibility rules.
3. **Notifications** – When sessions are rescheduled or goals updated, push notifications should deep-link into Schedule Calendar or Progress & Goals respectively.
4. **Accessibility & Localization** – Follow WCAG 2.1 AA and plan for localization/internationalization if student portals expand to multiple languages.
5. **Testing** – Implement feature specs ensuring students can navigate among the three menu sections, while therapists/admins cannot access `/student/*` routes.

## Backlog / Open Questions
- Determine whether messaging/settings appear as sub-navigation under Dashboard or as separate future menu entries.
- Decide how guardian accounts switch between multiple students and whether the top-level menu needs a selector.
- Confirm retention rules for student reflections and whether they become part of the legal record.

