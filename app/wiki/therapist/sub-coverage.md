# Substitute Therapist Coverage PRD

## Purpose

Enable a therapist who cannot perform an upcoming scheduled session (vacation, illness, conflict) to formally hand it off to a peer through the platform, instead of arranging the swap over email and having admin record the work under whoever performed it. The flow creates an auditable "A was scheduled, B covered" record on the schedule, gives the covering therapist a scoped SSA reference so they can submit their own session log, and lets the importer auto-resolve coverage when CSVs come in. Billing follows the performer because dual-billing already accepts the performing therapist independently — the sub's contract drives therapist-side billing; school-side billing is unchanged.

## Personas

- **Original therapist (requester)** — owns a schedule they can no longer perform and raises a sub request targeting specific eligible peers.
- **Substitute therapist (invitee/sub)** — receives an invitation, decides to accept or decline; when accepted, becomes the performer of record for the session.

## Current Implementation

- Therapists can raise a sub request for any owned schedule before the configured cutoff (default 2 hours pre-session) and target one or more eligible peers from a multi-select picker.
- Each invitee receives an invitation email; they see only requests they were invited to and the schedules they have accepted.
- The first invitee to accept wins atomically — concurrent accepts are blocked with a `lockForUpdate()` re-check; the losing accept gets a clear "already accepted" error.
- On acceptance, the schedule's `sub_therapist_id` and `sub_request_status` flip to the accepted state and a **sub-SSA snapshot** row is written so the sub has a scoped SSA reference for session-log submission and import resolution.
- Requesters can manage the invitee list while the request is still `open` — add invitees, withdraw `invited` rows, and re-invite previously-declined therapists.
- Calendar, schedule lists, and dashboard widgets render a coverage role badge (`Covering for …`, `Covered by …`, `Sub requested`) so each viewer's role on the session is glanceable.
- A scheduled command (`sub-requests:expire-overdue`, runs every two hours) auto-marks open requests as `expired` once their session is within the cutoff window.
- CSV importer auto-resolves coverage via the sub-SSA snapshot when the imported row's therapist matches an accepted assignment.
- `original_therapist_id` is captured on session logs created by a sub so the audit trail keeps both identities.

## Planned Scope

- Per-school override of the cutoff hours (currently a single env-driven global).
- Admin management surface — today admins cannot view, cancel, or otherwise manage sub requests. Planned: an admin index of all open requests across the org, plus the ability to cancel/sync invitees on behalf of either side and to raise a request for a therapist they don't own.
- Notification when an invitee declines (currently silent; the requester checks the panel).

## Domain Model

### Tables

| Table                            | Fields                                                                                                                                                                                                                                                                                                                                                                                                  |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `schedule_sub_requests`          | `id`, `schedule_id`, `requested_by_id`, `reason` (nullable text), `status` (open/accepted/cancelled/expired), `accepted_by_id` (nullable), `accepted_at` (nullable), `cancelled_at` (nullable), timestamps, `deleted_at`. Indexes: `(status, requested_by_id)`, `schedule_id`, `(accepted_by_id, status)`.                                                                                                |
| `schedule_sub_request_invitees`  | `id`, `schedule_sub_request_id`, `therapist_id`, `status` (invited/accepted/declined/withdrawn/superseded/expired), `responded_at` (nullable), timestamps, `deleted_at`. Unique: `(schedule_sub_request_id, therapist_id)`. Index: `(therapist_id, status)`.                                                                                                                                              |
| `schedule_sub_ssas`              | `id`, `schedule_sub_request_id`, `schedule_id`, `ssa_id`, `sub_therapist_id`, `student_id`, `service_id`, `school_id` (nullable), `session_date`, timestamps, `deleted_at`. Index: `(sub_therapist_id, session_date, service_id)`. **Snapshot at accept-time** — captures the SSA the original therapist owned so the sub has a scoped reference even if the SSA later changes/ends.                       |
| `schedules` (added columns)      | `sub_therapist_id` (nullable FK → `users.id`), `sub_request_status` (nullable: `requested`/`accepted`). Index: `(sub_therapist_id, schedule_date)`. Mirrors the parent request lifecycle on the schedule itself so a single query can render coverage state without joining.                                                                                                                              |
| `session_logs` (added column)    | `original_therapist_id` (nullable FK → `users.id`). Index: `(original_therapist_id, session_date)`. Captures the therapist who was originally scheduled when a sub performs and logs the session, so the audit trail keeps both identities.                                                   


## How It Works (End-to-End Flow)

> Roles below: **A** = original therapist (requester); **B/C/D** = candidate substitute therapists; **E** = an ineligible therapist (wrong position or no active contract).

### 1. Eligibility check (shared predicate)

A therapist is **eligible** to substitute on a given schedule when **all** of:

- Same `position_id` as the requester's `therapist_profiles.position_id`.
- Has an active `therapist_contract` whose effective range covers `schedules.schedule_date`, with a rate for `schedules.service_id` (validated through the same path as session-log rate resolution).
- Is not the requester themselves.

The predicate lives once on the repository (`applyEligibilityFilter()`) and is reused for the picker endpoint, server-side validation on store/update invitees, and the accept-time race-safe re-check. There is no second-source eligibility check on the FormRequest layer.

### 2. Raising the request

**At schedule creation** — A ticks "Request a sub" on the create form, a panel reveals a reason textarea and a multi-select of eligible therapists (fetched from `GET /therapist/sub-requests/eligible-subs?service_id=&date=` because the schedule doesn't exist yet). A picks B and C, optionally writes a reason, submits the schedule. The schedule is saved first; the sub request is the bundled side-effect that follows.

**From an existing schedule's edit page** — A opens the **Sub coverage** panel and submits invitees from there (`POST /therapist/schedules/{schedule}/sub-request`).

Either path lands in `ScheduleSubRequestService::create($A, $schedule, [$B->id, $C->id], $reason)`, which:

- Asserts A owns the schedule.
- Asserts the schedule starts more than `scheduling.sub_request_cutoff_hours` (default 2) into the future.
- Asserts no `open` request already exists for this schedule.
- Re-runs eligibility on every invitee server-side.
- Inserts one `schedule_sub_requests` row (`status='open'`, `requested_by_id=A`).
- Inserts one `schedule_sub_request_invitees` row per invitee (`status='invited'`).
- Sets `schedules.sub_request_status = 'requested'`.
- Sends invitation emails to each invitee.

For **recurring schedules**, the same invitee list and reason are applied to every generated occurrence in the batch via `createForScheduleAndOccurrences()`. The first occurrence is required (its failure propagates as an error); later occurrences are best-effort — if one fails (cutoff passed, ineligible) the others still succeed and the user gets a "X created, Y skipped" warning rather than a partial rollback.

### 3. Managing invitees after creation

While the request is `open`, A can change the invitee list from the Sub coverage panel (`PATCH /therapist/sub-requests/{request}/invitees`). The panel shows reason + per-invitee status. `ScheduleSubRequestService::syncInvitees()`:

- New ID in payload, no existing row → insert `invited`.
- New ID in payload, existing `declined` row → flip back to `invited`, clear `responded_at`. Re-invitation is explicit.
- Existing `invited` row removed from payload → flip to `withdrawn`.
- Terminal statuses (`accepted`/`declined`/`withdrawn`/`superseded`/`expired`) on rows not in the payload are untouched.
- Triggers a new invitation email to therapists whose row just became `invited`.

The cutoff window enforced on create also enforces here — invitees cannot be changed within the cutoff hours of the session.

### 4. Accepting (race-safe)

B opens their **Sub Requests** dashboard, sees the open invitation, hits Accept (`POST /therapist/sub-requests/{request}/accept`). `ScheduleSubRequestService::accept()` runs inside a single transaction that:

1. Locks the parent `schedule_sub_requests` row (`lockForUpdate`).
2. Re-checks status is still `open` (else: "already accepted by someone else").
3. Locks B's invitee row; re-checks status is still `invited`.
4. Re-runs cutoff + eligibility (the schedule could have moved, B's contract could have ended since invitation).
5. Resolves the original therapist's active SSA for `(student, service, session_date)`.
6. Flips `schedule_sub_requests`: `status='accepted'`, `accepted_by_id=B`, `accepted_at=now()`.
7. Flips B's invitee row to `accepted`, `responded_at=now()`.
8. Single-statement supersede of every other still-`invited` invitee on this request → `superseded`.
9. Updates the schedule: `sub_therapist_id=B`, `sub_request_status='accepted'`.
10. Writes the **sub-SSA snapshot** row pointing at the original SSA so B has a scoped reference for session-log submission and import.

Concurrent accepts from C are caught by step 2 — only one wins, the other gets a 422.

### 5. Declining

B can decline an open invitation (`POST /therapist/sub-requests/{request}/decline`). Only the invited therapist can decline; the invitee row flips to `declined` with `responded_at`. The **parent request stays `open`** — other invitees can still accept. A can later re-invite B from the panel.

### 6. Cancelling

A can cancel their own open request (`POST /therapist/sub-requests/{request}/cancel`):

- Only `open` requests are cancellable (you can't "cancel" an accepted one — that's a separate undo flow not yet built).
- Parent flips to `cancelled` with `cancelled_at`.
- Every still-`invited` invitee on the request flips to `superseded` in a single statement.
- Schedule's `sub_request_status` and `sub_therapist_id` are cleared, restoring the original therapist as the performer.

### 7. Automatic expiry

The `sub-requests:expire-overdue` console command, scheduled every two hours, finds open requests whose schedule is now within the cutoff window. Per request, in a chunked transaction:

- Parent flips to `expired`.
- Every still-`invited` invitee flips to `expired`.
- Schedule's `sub_request_status` and `sub_therapist_id` are cleared.

This stops invitees from accepting requests too close to the session start and keeps the requester's "My sub requests" view from accumulating stale items.

### 8. Performing the session

Once accepted, B sees the schedule in their **Past Sessions queue** (alongside their own schedules). When B submits a session log:

- B's eligible SSAs include the snapshotted sub-SSA (loaded via `ScheduleSubRequestRepositoryInterface::findSubSsaForSchedule()`), so the SSA dropdown surfaces it correctly even though B doesn't normally have that SSA.
- The session log's `therapist_id` is B (the performer). `original_therapist_id` is set to A.
- Billing runs against B's therapist contract for the therapist side; school side is unchanged because school billing is keyed on the school, not the therapist.

A's calendar shows the schedule with a "Covered by B" badge and the **Bill** affordance is suppressed (B will log it, not A).

### 9. Importer behaviour

When a CSV row arrives whose therapist doesn't own an SSA for the listed student, the importer falls back to `findSubSsasForImport($subTherapistId, $sessionDate, $serviceId, $studentId)`. If a sub-SSA snapshot matches, the row is created under the sub's identity with `original_therapist_id` set, mirroring the in-app flow.

## Rules

### Eligibility & visibility

- Eligibility predicate is single-source on the repository — picker, server-side validation, and accept-time re-check all call it.
- The eligible-subs picker requires `service_id` + `date` at create-time (schedule doesn't exist yet) or a `schedule_id` / `subRequest` route at edit-time.
- Invitees can only see requests they were directly invited to. The requester sees their own requests. Admin has no management surface for sub requests today — see Planned Scope.
- Schedules display a coverage badge resolved by `CoverageRoleResolver::for($schedule, $viewerId)` — `covering` for the sub, `covered` for the original, `open_request` for the requester pre-acceptance, null otherwise.

### Lifecycle invariants

- A schedule can have at most **one `open` `schedule_sub_requests` row at a time**. Re-raising while one is open is rejected.
- A schedule can have at most **one accepted sub** at any moment. Concurrent accepts on the same request are atomically resolved — first writer wins; losers get a 422 "already accepted" error.
- Invitee row uniqueness is `(schedule_sub_request_id, therapist_id)` — a therapist can't appear twice on the same request.
- Terminal invitee statuses (`accepted`/`declined`/`withdrawn`/`superseded`/`expired`) are never auto-rewritten by `syncInvitees`. Declined invitees re-enter the flow only when explicitly re-selected.

### Cutoff / timing

- `scheduling.sub_request_cutoff_hours` (default 2, configurable via `SUB_REQUEST_CUTOFF_HOURS` env) is the minimum lead time. It applies to: raising a request, syncing invitees, and accepting.
- The expiry command runs every 2 hours; an open request typically becomes `expired` within 2 hours of crossing into the cutoff window.
- All timestamps stored UTC; cutoff arithmetic is done in PHP/Carbon, never in SQL. Date columns paired with a datetime (`schedule_date`) follow the project convention — display derives from the UTC datetime in the viewer's timezone.

### Authorization

- Only the schedule owner (original therapist) can raise a sub request for it.
- Only invitees with an `invited` row can accept or decline.
- Only the requester can cancel or sync invitees on their own request. There is no admin override path today; cross-cutting admin actions are listed under Planned Scope.
- Requester cannot accept their own request (defensive check; eligibility already excludes self).

### Billing & session logs

- Billing follows the performer. The sub's therapist contract drives therapist-side calculation; the school-side calculation is independent of who performs the session.
- When a sub is covering a schedule, the original therapist's **Bill** affordance is suppressed on calendar, dashboard, and schedule list rows — the sub logs the session, not the requester.
- `original_therapist_id` is set on session logs created by a sub so reporting can answer "who was originally scheduled vs who performed". It is `null` on session logs not involving coverage.
- Sub-SSA snapshots are immutable references: if the original SSA later changes its services or ends, the sub still has the snapshot row to drive their session-log submission.

### Email

- Every newly-invited therapist (whether at create-time or via re-invitation through `syncInvitees`) receives an invitation email. Email failures are logged and swallowed — they never fail the primary domain action (request creation / invitee sync).

### Cascades & soft deletes

- Deleting a schedule soft-deletes its `schedule_sub_requests`, which cascades (via observer) to their `schedule_sub_request_invitees`. Restoring the schedule restores the same rows.
- `schedule_sub_ssas` is FK-cascade-deleted from `schedule_sub_requests` at the database level.
- `original_therapist_id` on `session_logs` is not cascaded — historical logs keep the reference even if the user record is later deactivated.

## Routes

| Method  | URL                                                          | Action                                  |
| ------- | ------------------------------------------------------------ | --------------------------------------- |
| GET     | `/therapist/sub-requests`                                    | Index — Invited / My Requests tabs      |
| POST    | `/therapist/sub-requests/data`                               | DataTables endpoint for the index       |
| GET     | `/therapist/sub-requests/eligible-subs?service_id=&date=`    | Eligible-subs picker (create-time)      |
| GET     | `/therapist/sub-requests/{subRequest}/eligible-subs`         | Eligible-subs picker (edit-time)        |
| POST    | `/therapist/schedules/{schedule}/sub-request`                | Raise a request for an existing schedule |
| POST    | `/therapist/sub-requests/{subRequest}/accept`                | Invitee accepts                         |
| POST    | `/therapist/sub-requests/{subRequest}/decline`               | Invitee declines                        |
| POST    | `/therapist/sub-requests/{subRequest}/cancel`                | Requester cancels their own request     |
| PATCH   | `/therapist/sub-requests/{subRequest}/invitees`              | Sync invitee list                       |

## Background Jobs

| Command                          | Schedule         | Purpose                                                                 |
| -------------------------------- | ---------------- | ----------------------------------------------------------------------- |
| `sub-requests:expire-overdue`    | every 2 hours    | Marks open requests within the cutoff window as `expired`, supersedes their still-invited rows, and clears `schedules.sub_request_status` / `sub_therapist_id`. |
