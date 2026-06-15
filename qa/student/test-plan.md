# Student Test Plan

> Source: app/wiki/student/*.md (+ route inventory for features not yet documented)
> Generated: 2026-06-12 (auto-derived from qa/LD-Expert-QA.xlsx - Student sheet)
> Coverage: 7 features, 12 test cases (6 Valid / 5 Invalid / 1 Edge)

## Scope

Students currently have only a Dashboard implemented (/student/dashboard). Schedule Calendar, Progress & Goals, Session History and Account Settings are documented in the wiki but NOT yet built - flagged as Not Built.

---

## Feature Areas

| Area | Priority | Test Cases | Count | Wiki Reference |
|------|----------|-----------|-------|----------------|
| Authentication | P1 | TC-S001, TC-S002, TC-S003 | 3 | - |
| Access Control | P1 | TC-S004, TC-S005, TC-S006 | 3 | - |
| Dashboard | P1 | TC-S007, TC-S008 | 2 | wiki/student/portal.md |
| Schedule Calendar | P3 | TC-S009 | 1 | wiki/student/portal.md (NOT BUILT - planned route) |
| Progress & Goals | P3 | TC-S010 | 1 | wiki/student/portal.md (NOT BUILT - planned route) |
| Session History | P3 | TC-S011 | 1 | wiki/student/portal.md (NOT BUILT) |
| Account Settings | P3 | TC-S012 | 1 | NOT BUILT (not routed) |

---

## Test Cases by Feature

### Authentication

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-S001 | Valid | Student login with valid credentials | Redirected to /student/dashboard |
| TC-S002 | Invalid | Student login with wrong password | Credentials error shown, stays on login |
| TC-S003 | Invalid | Login with non-existent student email | Authentication error, no session |

### Access Control

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-S004 | Invalid | Student blocked from admin routes | Redirected (not 200) - role middleware blocks access |
| TC-S005 | Invalid | Student blocked from therapist routes | Redirected (not 200) |
| TC-S006 | Invalid | Guest redirected to login from student area | Redirected to /login |

### Dashboard

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-S007 | Valid | Student dashboard loads after login | Dashboard renders without errors |
| TC-S008 | Edge | Dashboard with no upcoming sessions | Dashboard renders empty states gracefully |

### Schedule Calendar

> **Not implemented** - documented in the wiki as a planned feature but no route exists. Listed as Not Built; no executable tests until built.

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-S009 | Not Built | Schedule Calendar - NOT IMPLEMENTED | Deferred until /student/schedule is implemented |

### Progress & Goals

> **Not implemented** - documented in the wiki as a planned feature but no route exists. Listed as Not Built; no executable tests until built.

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-S010 | Not Built | Progress and Goals - NOT IMPLEMENTED | Deferred until /student/goals is implemented |

### Session History

> **Not implemented** - documented in the wiki as a planned feature but no route exists. Listed as Not Built; no executable tests until built.

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-S011 | Not Built | Session History - NOT IMPLEMENTED | Deferred until student session history is implemented |

### Account Settings

> **Not implemented** - documented in the wiki as a planned feature but no route exists. Listed as Not Built; no executable tests until built.

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-S012 | Not Built | Account Settings - NOT IMPLEMENTED | Deferred until student account settings is implemented |
