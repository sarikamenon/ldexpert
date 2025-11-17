# Student Portal PRD

## Purpose
Provide students (and optionally guardians) with visibility into their schedules, session history, progress against SSA goals, and communications with therapists. The portal should promote engagement while respecting privacy and access controls.

## Personas
- **Student** — primary user viewing schedule, goals, messaging therapists.
- **Guardian/Parent** — proxy access (read-only or limited interactions) for minors.
- **Case Manager** — monitors student-facing content (announcements, progress).

## Current Implementation
- Students exist as `users` with `role=student` plus `student_profiles` storing demographics/guardian contacts. No student-specific UI or routes exist; students cannot log in.
- Guardian data lives inside `student_profiles` and `users` (parents) but lacks portal integration.

## Planned Scope
- Enable student authentication (standard Laravel auth) with role-based home page.
- Dashboard summarizing today’s schedule, overdue assignments, and recent progress updates.
- Calendar of upcoming sessions (read-only) sourced from SSA schedules and actual `sessions` records.
- Progress center showing SSA goals, last session notes, milestone achievements.
- Communication channel (messages or announcements) with therapists/admins, subject to moderation.
- Settings page for timezone, notification preferences, and guardian management.

## Domain Model
| Entity | Notes |
| --- | --- |
| `users` | Students authenticate; enforce MFA option for older students.
| `student_profiles` | Already holds address, guardian, school info; extend with portal prefs (language, accessibility needs).
| `sessions` | Provide schedule + history; include visibility flags (`is_visible_to_student`).
| `ssa_goals` | From SSA module; portal surfaces target vs. progress metrics.
| `student_notifications` (new) | in-app updates (schedule change, new note, invoice for private family).
| `guardian_access` (new) | join table linking parent users to students with role + permissions.

## Routes & UI
### Web
- `GET /student/dashboard` — landing page after login.
- `GET /student/schedule` — calendar; filters for week/day/month.
- `GET /student/sessions/{session}` — detail view with note summary, attachments flagged as student-visible.
- `GET /student/goals` — list SSA goals with progress meters.
- `GET /student/messages` & `POST /student/messages` — optional messaging center (subject to compliance review).
- `GET /student/settings` & `PATCH /student/settings` — update timezone, notification prefs; guardian access managed here when allowed.

### API (for mobile app or guardian portal)
- `GET /api/v1/student/schedule` — upcoming sessions (requires OAuth token, role=student/guardian).
- `GET /api/v1/student/goals` — goal data.
- `POST /api/v1/student/messages` — send message to therapist (moderated).

## Workflows
1. **Login & Onboarding**
   1. Student receives welcome email (already sent today via `WelcomeUserMail`); update template with portal instructions.
   2. First login triggers forced password reset + profile confirmation (timezone, pronouns, accessibility preferences).
2. **Schedule Consumption**
   1. Portal reads upcoming sessions (approved) and displays them with SSA context.
   2. If a session is canceled or rescheduled, notification sent; student acknowledges.
3. **Progress Tracking**
   1. After therapist submits session note, they mark visibility for summary.
   2. Student portal shows sanitized note, goal progress, and allows optional reflection (stored in `student_feedback`).

## Security & Privacy
- Enforce role-based middleware `role:student` (future) similar to therapist routes.
- Guardian access rules: guardians need explicit consent; actions logged.
- Sensitive data (diagnoses) should only display if flagged as shareable.
- Comply with FERPA/IDEA: provide audit log for all accesses, allow data export if requested.

## Integrations & Dependencies
- Requires SSA, Sessions, Services modules to expose sanitized data.
- Notifications rely on queue + email; consider push notifications for mobile.
- If RSM/CAVA remains authoritative for schedules, sync module must mark which sessions can show in portal.

## Metrics
- Portal engagement (logins/week per student).
- Session acknowledgement rate (did student view upcoming session?).
- Goal insight usage.

## Risks & Open Questions
- Need legal sign-off for minors communicating directly with therapists via platform.
- Determine accessibility requirements (WCAG 2.1 AA) and localization needs.
- Clarify guardian vs. student permissions, especially when student is over 18.
