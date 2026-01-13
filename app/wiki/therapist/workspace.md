# Therapist Workspace PRD

## Purpose

Give therapists a unified workspace to manage caseloads, schedules, documentation, and billing visibility. The workspace must streamline daily workflows while enforcing SSA and compliance rules.

## Personas

-   **Therapist (primary)** — delivers services, documents sessions, reviews assignments, submits hours.
-   **Clinical Supervisor** — monitors therapist workload and approvals (future role).

## Current Implementation

-   Auth & routing: therapists authenticate via standard Laravel auth and are routed through `routes/therapist.php` with middleware `role:therapist`.
-   Dashboard: `Therapist\DashboardController@index` uses `DashboardService` + `UserRepositoryInterface` to show metrics. View: `resources/views/dashboard.blade.php`.
-   **Student management is admin-only** — Students are created and managed by admins through the SSA-backed student workflow. Therapists have read-only access to assigned students via `/therapist/students` and `/therapist/students/{student}`.
-   Scheduling (implemented):
    -   Routes under `/therapist/schedule*` handle calendar, create, edit, update, delete, and billing status updates (`updateBillingStatus`, `bulkUpdateBillingStatus`).
    -   Data model (`schedules`): `therapist_id`, `student_id`, `ssa_id`, `service_id`, `school_id`, `schedule_date`, `start_time`, `end_time`, `recurrence_type` + `recurrence_end_date`, `is_group`, `recurring_batch_number`, `group_batch_number`, `status`, `billing_status`, `notes`, `location_details`, soft deletes.
    -   Supports single and recurring schedules; recurrence/group batches tracked by batch numbers; `parent_schedule_id` links occurrences.
    -   **Recurring Schedules**: Therapists can create recurring schedules (daily, weekly, bi-weekly, monthly) with end date. System generates individual schedule occurrences linked via `parent_schedule_id` and `recurring_batch_number`. Changes to parent schedule can propagate to future occurrences (with confirmation). See Recurring Schedule section below.
    -   Billing status transitions managed per schedule or in bulk; statuses include pending, billed, waived, not billable (per enums).
    -   Deletes handled via controller destroy; schedules respect SSA/service/therapist availability rules in `ScheduleService`.
-   **Student Comments** (implemented):
    -   Therapists can add comments on student records visible to all admins and assigned therapists.
    -   Comments appear chronologically on student detail page.
    -   Maximum length: 5000 characters.
    -   See [Student Comments PRD](./student-comments.md) for complete documentation.
-   SSA view: therapists can view assigned SSAs read-only via `/therapist/ssas` and `/therapist/ssas/{ssa}`.
-   No session notes or therapist-facing billing statements yet; those remain in the planned scope.

## Recurring Schedules

### Overview
Therapists can create recurring schedules that automatically generate individual schedule occurrences based on a recurrence pattern (daily, weekly, bi-weekly, monthly). Recurring schedules reduce manual entry for regular therapy sessions and maintain consistency across a series.

### Data Model
- **Parent Schedule**: The initial schedule record that defines the recurrence pattern (`recurrence_type`, `recurrence_end_date`).
- **Occurrences**: Individual schedule records generated from the parent, linked via `parent_schedule_id` and `recurring_batch_number`.
- **Recurrence Types**: `NONE` (single), `DAILY`, `WEEKLY`, `BI_WEEKLY`, `MONTHLY` (enum: `RecurrenceType`).
- **Batch Tracking**: All occurrences in a series share the same `recurring_batch_number` for efficient querying.

### Recurrence Rules
- **End Date**: Recurrence continues until `recurrence_end_date` (defaults to SSA end date or configurable limit, e.g., 1 year from start).
- **Occurrence Generation**: System generates all occurrences up to end date when parent schedule is created.
- **Time Consistency**: All occurrences maintain the same `start_time` and `end_time` as parent schedule.
- **Student/SSA Consistency**: All occurrences maintain the same `student_id`, `ssa_id`, `service_id`, `school_id` as parent.

### Management Operations
- **Edit Parent Schedule**: Changes to parent can propagate to future occurrences (with confirmation dialog).
- **Edit Individual Occurrence**: Individual occurrences can be edited without affecting the series.
- **Cancel Occurrence**: Single occurrence can be cancelled without affecting other occurrences.
- **Cancel Series**: Cancelling parent schedule can cancel all future occurrences (with confirmation).
- **Delete**: Deleting parent schedule soft-deletes parent; occurrences remain but lose parent link.

### Constraints
- Recurring schedules must respect SSA frequency limits and therapist availability.
- Occurrences cannot extend beyond SSA end date or student active period.
- Group schedules (multiple students) can also be recurring; all students in group follow same recurrence pattern.

### UI/UX
- Calendar view shows all occurrences with visual grouping by batch number.
- Parent schedule detail page shows list of all occurrences with status.
- Recurrence pattern displayed in schedule detail (e.g., "Weekly, every Monday, until 2026-12-31").
- Confirmation dialogs for operations that affect multiple occurrences.

## Planned Scope

-   Extend dashboard with actionable widgets: upcoming sessions, pending notes, expiring SSAs, billing reminders.
-   **Read-only caseload view** with SSA context, goals, and contact logs. Therapists can view assigned students but cannot create or edit student records.
-   Calendar-based scheduling integrated with student availability + SSA limits; support manual session creation for non-RSM/CAVA schools.
-   Session documentation forms tied to service templates (from Services module) with goal tracking and attachments.
-   Billing visibility: therapists see submitted sessions, bill status, disputes, and pay statements (from Billing module).
-   Notifications (in-app + email) for new assignments, SSA amendments, schedule changes, billing approvals.
-   Recurring schedule management UI improvements (bulk edit occurrences, holiday handling, skip dates).

## Domain Model

### Existing Data

| Table                | Usage                                         |
| -------------------- | --------------------------------------------- |
| `users`              | Therapist accounts (`role=therapist`).        |
| `therapist_profiles` | Contact + credential info.                    |
| `therapist_student`  | Caseload assignments.                         |
| `student_profiles`   | Student demographics shown in caseload views. |

### Planned Extensions

| Table                     | Addition                                                                                                      |
| ------------------------- | ------------------------------------------------------------------------------------------------------------- |
| `sessions`                | Therapist-created sessions (shared with invoicing/billing) including `status`, `submitted_at`, `approved_at`. |
| `session_notes`           | `session_id`, `author_id`, `note_body`, `goal_updates` (json), `attachments`.                                 |
| `therapist_tasks`         | Reminders (complete note, update SSA, confirm schedule).                                                      |
| `therapist_notifications` | queue for in-app notifications.                                                                               |

## UI / Routes

### Existing Routes (Implemented)

```
GET    /therapist/dashboard
GET    /therapist/schedule          (calendar)
GET    /therapist/schedule/calendar (calendar JSON)
GET    /therapist/schedule/schedules (JSON list for calendar)
GET    /therapist/schedule/pending  (pending schedules)
GET    /therapist/schedule/create
POST   /therapist/schedule          (store)
GET    /therapist/schedule/{id}
GET    /therapist/schedule/{id}/edit
PUT    /therapist/schedule/{id}     (update)
DELETE /therapist/schedule/{id}     (destroy)
POST   /therapist/schedule/{id}/remove-student
PUT    /therapist/schedule/{id}/billing-status
POST   /therapist/schedule/bulk-billing-status
GET    /therapist/ssas
GET    /therapist/ssas/{ssa}
GET    /therapist/students
GET    /therapist/students/{student}
POST   /therapist/students/{student}/comments  (add comment)
```

Controllers live under `App\Http\Controllers\Therapist`. Previous student CRUD routes have been removed (admin-only now).

### Planned Routes

-   `POST /therapist/sessions` — create session tied to SSA/service, includes note stub.
-   `GET /therapist/sessions/{session}` / `PATCH ...` — edit details until submitted.
-   `POST /therapist/sessions/{session}/submit` — lock note, queue for approval.
-   `GET /therapist/billing` & `GET /therapist/billing/{bill}` — pay visibility (ties to Billing PRD).
-   Notification APIs (mark read, list) under `/therapist/notifications`.

## Workflows

1. **Caseload Intake (SSA-Based)**
    1. Admin creates SSA with student information.
    2. Admin assigns therapist to student via SSA.
    3. Therapist receives notification when admin assigns SSA service.
    4. Caseload list updates to include new student (read-only); SSA details + goals visible.

2. **Recurring Schedule Creation**
    1. Therapist opens schedule calendar, clicks "Create Schedule".
    2. Selects student(s), SSA, service, and initial date/time.
    3. Chooses recurrence type: Daily, Weekly, Bi-weekly, or Monthly.
    4. Sets recurrence end date (optional; defaults to SSA end date or configurable limit).
    5. System generates parent schedule and all occurrences up to end date.
    6. Occurrences linked via `parent_schedule_id` and `recurring_batch_number`.
    7. Individual occurrences can be edited or cancelled without affecting series.
    8. Parent schedule edits can propagate to future occurrences (with confirmation dialog).

3. **Scheduling & Session Documentation**
    1. Therapist opens schedule, selects available slot abiding by SSA frequency and student availability.
    2. After delivering session, therapist completes note template (auto-filled goals) and submits within 24 hours.
    3. Submission locks edits, triggers admin/clinical supervisor review if needed.
    4. Therapist can upload documents to session log (Progress Reports, IEPs, Consent Forms, Assessments).

4. **Student Comments**
    1. Therapist views student detail page.
    2. Adds comment in comments section (visible to all admins and assigned therapists).
    3. Comments appear chronologically with author name and timestamp.
    4. Comments are soft-deleted and retain audit trail.

5. **Billing Review**
    1. Prior to billing cycle close, therapist sees list of unsubmitted sessions.
    2. After AP approves bill, therapist can view statement; disputes initiated from the same screen.

## Authorization & Security

-   Use policies (future `SessionPolicy`, `SchedulePolicy`) to restrict data by assignment.
-   All POST/PATCH routes protected by middleware stack: `auth`, `role:therapist`, CSRF.
-   Audit log every session note submission (user_id, timestamp, IP).
-   Student data is read-only for therapists; only admins can modify student profiles.

## Dependencies

-   Requires SSA, Services, Sessions, Billing modules to expose APIs.
-   Notification center depends on queue + broadcasting stack (Laravel events + websockets, optional).

## Metrics

-   Sessions submitted on time (% within 24h).
-   Caseload distribution (students per therapist, hours per week).
-   Outstanding tasks (notes, schedule confirmations).

## Risks & Open Questions

-   **Resolved:** Therapists cannot edit student demographics. All student data is managed by admins via SSA workflow.
-   Confirm whether therapists can create sessions for RSM/CAVA schools or only mark attendance (if schedule imported via Sync).
-   Determine offline/mobile access requirements.
