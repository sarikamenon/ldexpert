# Therapist Test Plan

> Source: app/wiki/therapist/*.md (+ route inventory for features not yet documented)
> Generated: 2026-06-12 (auto-derived from app/qa/LD-Expert-QA.xlsx - Therapist sheet)
> Coverage: 14 features, 62 test cases (40 Valid / 15 Invalid / 7 Edge)

## Scope

Therapists manage schedules (single + recurring), view assigned students/SSAs read-only, log sessions through the approval lifecycle, request substitute coverage, and view their bills.

---

## Feature Areas

| Area | Priority | Test Cases | Count | Wiki Reference |
|------|----------|-----------|-------|----------------|
| Authentication | P1 | TC-T001, TC-T002 | 2 | - |
| Access Control | P1 | TC-T003, TC-T004, TC-T005 | 3 | - |
| Dashboard | P1 | TC-T006, TC-T007 | 2 | wiki/therapist/workspace.md |
| Schedule | P1 | TC-T008, TC-T009, TC-T010, TC-T011, TC-T012, TC-T013, TC-T014, TC-T015, TC-T016, TC-T017, TC-T018, TC-T019, TC-T020 | 13 | wiki/therapist/workspace.md |
| Schedule Calendar | P2 | TC-T021 | 1 | wiki/therapist/workspace.md |
| School Calendar | P3 | TC-T022, TC-T023 | 2 | NOT YET IN WIKI (built) |
| SSAs | P2 | TC-T024, TC-T025, TC-T026 | 3 | wiki/therapist/workspace.md |
| SSA Goals | P3 | TC-T027, TC-T028 | 2 | wiki/therapist/workspace.md |
| Students | P2 | TC-T029, TC-T030, TC-T031, TC-T032, TC-T033 | 5 | wiki/therapist/workspace.md + student-comments.md |
| QGlob Requests | P2 | TC-T034, TC-T035, TC-T036 | 3 | NOT YET IN WIKI (built) |
| Sub Requests | P1 | TC-T037, TC-T038, TC-T039, TC-T040, TC-T041, TC-T042, TC-T043, TC-T044, TC-T045, TC-T046 | 10 | wiki/therapist/sub-coverage.md |
| Session Logs | P1 | TC-T047, TC-T048, TC-T049, TC-T050, TC-T051, TC-T052, TC-T053, TC-T054, TC-T055, TC-T056 | 10 | wiki/therapist/session-logs.md |
| Billing | P2 | TC-T057, TC-T058, TC-T059, TC-T060 | 4 | wiki/therapist/menu.md |
| Pay Stub | P3 | TC-T061, TC-T062 | 2 | NOT YET IN WIKI (built) |

---

## Test Cases by Feature

### Authentication

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T001 | Valid | Therapist login with valid credentials | Redirected to /therapist/dashboard |
| TC-T002 | Invalid | Therapist login with wrong password | Credentials error shown, stays on login |

### Access Control

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T003 | Invalid | Therapist blocked from admin routes | Redirected (not 200) - role middleware blocks access |
| TC-T004 | Invalid | Therapist blocked from student routes | Redirected (not 200) |
| TC-T005 | Invalid | Therapist cannot edit student profile | Blocked - students are admin-only, read-only for therapist |

### Dashboard

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T006 | Valid | Therapist dashboard loads with metrics | Dashboard renders with caseload/schedule metrics |
| TC-T007 | Edge | Dashboard with no assignments | Dashboard renders empty states gracefully |

### Schedule

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T008 | Valid | Create single schedule | Schedule created and shown on calendar |
| TC-T009 | Valid | Create recurring weekly schedule | Parent schedule + occurrences generated, linked by batch number |
| TC-T013 | Valid | Edit a single occurrence without affecting series | Only that occurrence changes, series unaffected |
| TC-T014 | Valid | Edit parent propagates to future occurrences | Future occurrences updated after confirmation |
| TC-T015 | Valid | Delete a schedule | Schedule soft-deleted, removed from calendar |
| TC-T016 | Valid | Remove a student from a group schedule | Student removed from that schedule |
| TC-T017 | Valid | Update billing status of a schedule | Billing status updated |
| TC-T018 | Valid | Bulk update billing status | All selected schedules updated |
| TC-T019 | Valid | View pending schedules | Pending schedules listed |
| TC-T010 | Invalid | Create schedule outside SSA frequency limits | Blocked - respects SSA frequency and availability rules |
| TC-T011 | Invalid | Create schedule with end before start time | Validation error - end must be after start |
| TC-T012 | Invalid | Recurrence end date beyond SSA end date | Occurrences cannot extend beyond SSA end date |
| TC-T020 | Edge | Calendar with no schedules | Calendar renders empty without errors |

### Schedule Calendar

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T021 | Valid | View full calendar | Full calendar renders with events |

### School Calendar

> **Built but not yet documented in the wiki** - dev should backfill a PRD. Test cases derived from the route inventory.

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T022 | Valid | View read-only school calendar | School events render read-only |
| TC-T023 | Edge | School calendar with no events | Renders empty without errors |

### SSAs

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T024 | Valid | View assigned SSA list | Assigned SSAs listed read-only |
| TC-T025 | Valid | View SSA detail with goals | SSA detail and goals shown read-only |
| TC-T026 | Invalid | Therapist cannot view unassigned SSA | Access blocked / not visible |

### SSA Goals

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T027 | Valid | Add goal to assigned SSA | Goal created under the SSA |
| TC-T028 | Valid | Update goal status | Goal status updated |

### Students

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T029 | Valid | View assigned students list | Assigned students listed read-only |
| TC-T030 | Valid | View student detail | Student detail shown read-only |
| TC-T031 | Valid | Add comment on student | Comment saved, visible to admins and assigned therapists |
| TC-T033 | Valid | Upload a student document | Document stored against the student |
| TC-T032 | Edge | Student comment max length 5000 | Accepted at 5000-char boundary |

### QGlob Requests

> **Built but not yet documented in the wiki** - dev should backfill a PRD. Test cases derived from the route inventory.

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T034 | Valid | Create a QGlob request | Request created and listed |
| TC-T035 | Valid | View QGlob request detail | Request detail renders |
| TC-T036 | Edge | Delete own QGlob request | Request removed |

### Sub Requests

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T037 | Valid | Raise sub request for owned schedule | Request created status open, invitees invited, emails sent |
| TC-T041 | Valid | Invitee accepts a request | Status accepted, schedule sub_therapist set, sub-SSA snapshot written |
| TC-T043 | Valid | Invitee declines a request | Invitee row declined, parent stays open for others |
| TC-T044 | Valid | Requester cancels own open request | Request cancelled, invitees superseded, schedule restored to original |
| TC-T045 | Valid | Sync invitee list while open | Invitees synced, new invites emailed, withdrawn rows flipped |
| TC-T038 | Invalid | Raise sub request within cutoff window | Blocked - must be more than cutoff hours ahead |
| TC-T039 | Invalid | Raise duplicate open request for schedule | Blocked - only one open request per schedule |
| TC-T040 | Invalid | Invite an ineligible therapist | Ineligible therapist not selectable / rejected server-side |
| TC-T042 | Invalid | Second invitee accept after one accepted | 422 already accepted error - first writer wins |
| TC-T046 | Invalid | Requester accepts own request | Blocked - requester cannot accept own request |

### Session Logs

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T047 | Valid | Create session log from schedule | Draft session log created with schedule context |
| TC-T048 | Valid | Create standalone (non-schedule) session log | Draft session log created |
| TC-T049 | Valid | Submit a draft session log | Status DRAFT -> SUBMITTED, queued for admin approval |
| TC-T051 | Valid | Edit a sent-back session log | Log updated and resubmitted (SENT_BACK -> SUBMITTED) |
| TC-T053 | Valid | Add comment to a session log | Comment saved on the log |
| TC-T054 | Valid | Upload document to session log | Document attached to the log |
| TC-T055 | Valid | Cancel a session log | Log cancelled |
| TC-T050 | Invalid | Submit log with missing required fields | Validation error, not submitted |
| TC-T052 | Invalid | Edit an approved session log | Blocked - approved logs are locked from therapist edits |
| TC-T056 | Edge | Session log list empty state | Empty state shown |

### Billing

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T057 | Valid | View my bills list | Bills listed for the therapist |
| TC-T058 | Valid | View a bill detail | Bill detail renders |
| TC-T059 | Valid | Download a bill PDF | Bill PDF downloads |
| TC-T060 | Edge | Billing list empty state | Empty state shown |

### Pay Stub

> **Built but not yet documented in the wiki** - dev should backfill a PRD. Test cases derived from the route inventory.

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-T061 | Valid | View pay stub | Pay stub renders |
| TC-T062 | Valid | Download pay stub | Pay stub downloads |
