# Therapist Workspace PRD

## Purpose
Give therapists a unified workspace to manage caseloads, schedules, documentation, and billing visibility. The workspace must streamline daily workflows while enforcing SSA and compliance rules.

## Personas
- **Therapist (primary)** — delivers services, documents sessions, reviews assignments, submits hours.
- **Clinical Supervisor** — monitors therapist workload and approvals (future role).

## Current Implementation
- Auth & routing: therapists authenticate via standard Laravel auth and are routed through `routes/therapist.php` with middleware `role:therapist`.
- Dashboard: `Therapist\DashboardController@index` uses `DashboardService` + `UserRepositoryInterface` to show metrics. View: `resources/views/dashboard.blade.php`.
- **Student management has been removed** — Students are now created and managed by admins through the SSA (Student Service Agreement) workflow. Therapists will receive read-only access to assigned students.
- No scheduling, session notes, SSA view, or billing surfaces exist yet.

## Planned Scope
- Extend dashboard with actionable widgets: upcoming sessions, pending notes, expiring SSAs, billing reminders.
- **Read-only caseload view** with SSA context, goals, and contact logs. Therapists can view assigned students but cannot create or edit student records.
- Calendar-based scheduling integrated with student availability + SSA limits; support manual session creation for non-RSM/CAVA schools.
- Session documentation forms tied to service templates (from Services module) with goal tracking and attachments.
- Billing visibility: therapists see submitted sessions, bill status, disputes, and pay statements (from Billing module).
- Notifications (in-app + email) for new assignments, SSA amendments, schedule changes, billing approvals.

## Domain Model
### Existing Data
| Table | Usage |
| --- | --- |
| `users` | Therapist accounts (`role=therapist`).
| `therapist_profiles` | Contact + credential info.
| `therapist_student` | Caseload assignments.
| `student_profiles` | Student demographics shown in caseload views.

### Planned Extensions
| Table | Addition |
| --- | --- |
| `sessions` | Therapist-created sessions (shared with invoicing/billing) including `status`, `submitted_at`, `approved_at`.
| `session_notes` | `session_id`, `author_id`, `note_body`, `goal_updates` (json), `attachments`.
| `therapist_tasks` | Reminders (complete note, update SSA, confirm schedule).
| `therapist_notifications` | queue for in-app notifications.

## UI / Routes
### Existing Routes
```
GET    /therapist/dashboard
```
Controllers live under `App\Http\Controllers\Therapist`.

**Note:** Previous student CRUD routes have been removed. Students are now managed by admins via SSA workflow.

### Planned Routes
- `GET /therapist/schedule` — calendar view (week, month) filtered by student/service.
- `POST /therapist/sessions` — create session tied to SSA/service, includes note stub.
- `GET /therapist/sessions/{session}` / `PATCH ...` — edit details until submitted.
- `POST /therapist/sessions/{session}/submit` — lock note, queue for approval.
- `GET /therapist/ssas` — read-only SSA summary.
- `GET /therapist/billing` & `GET /therapist/billing/{bill}` — pay visibility (ties to Billing PRD).
- Notification APIs (mark read, list) under `/therapist/notifications`.

## Workflows
1. **Caseload Intake (SSA-Based)**
   1. Admin creates SSA with student information.
   2. Admin assigns therapist to student via SSA.
   3. Therapist receives notification when admin assigns SSA service.
   4. Caseload list updates to include new student (read-only); SSA details + goals visible.
2. **Scheduling & Session Documentation**
   1. Therapist opens schedule, selects available slot abiding by SSA frequency and student availability.
   2. After delivering session, therapist completes note template (auto-filled goals) and submits within 24 hours.
   3. Submission locks edits, triggers admin/clinical supervisor review if needed.
3. **Billing Review**
   1. Prior to billing cycle close, therapist sees list of unsubmitted sessions.
   2. After AP approves bill, therapist can view statement; disputes initiated from the same screen.

## Authorization & Security
- Use policies (future `SessionPolicy`, `SchedulePolicy`) to restrict data by assignment.
- All POST/PATCH routes protected by middleware stack: `auth`, `role:therapist`, CSRF.
- Audit log every session note submission (user_id, timestamp, IP).
- Student data is read-only for therapists; only admins can modify student profiles.

## Dependencies
- Requires SSA, Services, Sessions, Billing modules to expose APIs.
- Notification center depends on queue + broadcasting stack (Laravel events + websockets, optional).

## Metrics
- Sessions submitted on time (% within 24h).
- Caseload distribution (students per therapist, hours per week).
- Outstanding tasks (notes, schedule confirmations).

## Risks & Open Questions
- **Resolved:** Therapists cannot edit student demographics. All student data is managed by admins via SSA workflow.
- Confirm whether therapists can create sessions for RSM/CAVA schools or only mark attendance (if schedule imported via Sync).
- Determine offline/mobile access requirements.
