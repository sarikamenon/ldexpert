# Admin Test Plan

> Source: app/wiki/admin/*.md (+ route inventory for features not yet documented)
> Generated: 2026-06-12 (auto-derived from app/qa/LD-Expert-QA.xlsx - Admin sheet)
> Coverage: 23 features, 110 test cases (58 Valid / 35 Invalid / 17 Edge)

## Scope

Admins onboard schools, manage therapists/students, oversee SSAs, approve session logs, run imports, and view reports. Finance features are covered in the Finance sheet.

---

## Feature Areas

| Area | Priority | Test Cases | Count | Wiki Reference |
|------|----------|-----------|-------|----------------|
| Authentication | P1 | TC-A001, TC-A002, TC-A003 | 3 | - |
| Access Control | P1 | TC-A004, TC-A005, TC-A006 | 3 | - |
| Dashboard | P1 | TC-A007, TC-A008 | 2 | wiki/admin/dashboard.md |
| Schools | P1 | TC-A009, TC-A010, TC-A011, TC-A012, TC-A013, TC-A014, TC-A015, TC-A016, TC-A017, TC-A018, TC-A019, TC-A020 | 12 | wiki/admin/schools.md |
| School Calendar Events | P2 | TC-A021, TC-A022, TC-A023, TC-A024 | 4 | NOT YET IN WIKI (built) |
| Therapists | P1 | TC-A025, TC-A026, TC-A027, TC-A028, TC-A029, TC-A030, TC-A031 | 7 | wiki/admin/therapists.md |
| Students | P1 | TC-A032, TC-A033, TC-A034, TC-A035, TC-A036, TC-A037, TC-A038, TC-A039, TC-A040, TC-A041 | 10 | wiki/admin/students.md |
| Student Import | P1 | TC-A042, TC-A043, TC-A044, TC-A045, TC-A046 | 5 | wiki/admin/student-import.md |
| Student Documents | P2 | TC-A047, TC-A048, TC-A049, TC-A050 | 4 | wiki/admin/student-documents.md |
| Leads | P1 | TC-A051, TC-A052, TC-A053, TC-A054, TC-A055, TC-A056 | 6 | wiki/admin/leads.md |
| Services | P1 | TC-A057, TC-A058, TC-A059 | 3 | wiki/admin/services.md |
| Positions | P2 | TC-A060, TC-A061, TC-A062 | 3 | wiki/admin/settings.md (thin) |
| Service Aliases | P3 | TC-A063, TC-A064 | 2 | wiki/admin/settings.md (thin) |
| SSAs | P1 | TC-A065, TC-A066, TC-A067, TC-A068, TC-A069, TC-A070, TC-A071, TC-A072, TC-A073, TC-A074 | 10 | wiki/admin/ssa.md |
| SSA Goals | P2 | TC-A075, TC-A076, TC-A077 | 3 | wiki/admin/ssa.md |
| Contracts | P1 | TC-A078, TC-A079, TC-A080, TC-A081, TC-A082 | 5 | wiki/admin/contracts.md |
| Session Logs | P1 | TC-A083, TC-A084, TC-A085, TC-A086, TC-A087, TC-A088, TC-A089, TC-A090, TC-A091 | 9 | wiki/admin/session-logs.md |
| QGlob Requests | P2 | TC-A092, TC-A093, TC-A094 | 3 | NOT YET IN WIKI (built) |
| Schedule Calendar | P2 | TC-A095, TC-A096, TC-A097 | 3 | wiki/admin/schedule-calendar.md |
| Analytics | P2 | TC-A098, TC-A099 | 2 | wiki/admin/analytics.md |
| Notifications | P2 | TC-A100, TC-A101, TC-A102, TC-A103 | 4 | wiki/admin/notifications.md |
| Settings | P2 | TC-A104, TC-A105 | 2 | wiki/admin/settings.md |
| Reports | P2 | TC-A106, TC-A107, TC-A108, TC-A109, TC-A110 | 5 | wiki/admin/reports.md |

---

## Test Cases by Feature

### Authentication

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A001 | Valid | Admin login with valid credentials | Redirected to /admin/dashboard |
| TC-A002 | Invalid | Admin login with wrong password | Credentials error shown, stays on login |
| TC-A003 | Invalid | Login with non-existent email | Authentication error shown, no session created |

### Access Control

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A004 | Invalid | Admin blocked from therapist routes | Redirected (not 200) - role middleware blocks access |
| TC-A005 | Invalid | Admin blocked from student routes | Redirected (not 200) - role middleware blocks access |
| TC-A006 | Invalid | Guest redirected to login from admin area | Redirected to /login |

### Dashboard

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A007 | Valid | Admin dashboard loads with summary widgets | Dashboard renders with summary metrics, no errors |
| TC-A008 | Edge | Dashboard renders with zero data | Dashboard renders, widgets show zero/empty states gracefully |

### Schools

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A009 | Valid | Create school with all required fields | School created, appears in list, status ACTIVE, success toast |
| TC-A010 | Valid | Create school with service rates | School created with service rate persisted |
| TC-A017 | Valid | Edit school updates information | Update success toast, changes persisted |
| TC-A018 | Valid | Deactivate school via status toggle | School marked Deactivated, hidden from active SSA pickers, retained |
| TC-A020 | Valid | Export schools downloads filtered dataset | File downloads containing the filtered school rows |
| TC-A011 | Invalid | Create school with missing full name | Validation error full name is required, not saved |
| TC-A012 | Invalid | Create school with duplicate display name | Unique validation error on display_name, not saved |
| TC-A013 | Invalid | Create school with invalid invoice email | Email validation error, not saved |
| TC-A014 | Invalid | Create school with bad phone format | Phone regex error - digits and dashes only |
| TC-A015 | Invalid | Manager dropdown rejects non-admin user | Validation error - manager must be an admin user |
| TC-A016 | Edge | Create school with 255-char display name | School created, name stored without truncation |
| TC-A019 | Edge | List schools shows empty state | Table shows No data available empty state |

### School Calendar Events

> **Built but not yet documented in the wiki** - dev should backfill a PRD. Test cases derived from the route inventory.

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A021 | Valid | Add calendar event to a school | Event created and listed on the school calendar |
| TC-A023 | Valid | Update an existing calendar event | Event updated and reflects new values |
| TC-A022 | Invalid | Add calendar event with missing title | Validation error, event not created |
| TC-A024 | Edge | Delete a school calendar event | Event removed from calendar |

### Therapists

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A025 | Valid | Create therapist with all required fields | Therapist created, welcome email sent, appears in list, ACTIVE |
| TC-A030 | Valid | Edit therapist updates record | Update success toast, changes persisted |
| TC-A031 | Valid | Deactivate therapist hides from pickers | Therapist Deactivated, removed from assignment pickers, history retained |
| TC-A026 | Invalid | Create therapist with duplicate personal email | Unique email validation error, not saved |
| TC-A027 | Invalid | Create therapist with missing position | Validation error position is required |
| TC-A028 | Invalid | Max weekly hours out of range | Validation error - must be between 1 and 168 |
| TC-A029 | Edge | Max weekly hours boundary 168 | Therapist created - 168 accepted as upper bound |

### Students

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A032 | Valid | Create student with all required fields | Student created, success toast, appears in list |
| TC-A038 | Valid | Edit student updates profile | Update success toast |
| TC-A039 | Valid | Deactivate student removes from pickers | Student Deactivated, removed from pickers, history retained |
| TC-A040 | Valid | Add comment to student record | Comment saved, shown in chronological order |
| TC-A033 | Invalid | Create student with duplicate email | Unique email validation error, not saved |
| TC-A034 | Invalid | Create student with future date of birth | Validation error - DOB must be before today |
| TC-A035 | Invalid | Create student under inactive school | School dropdown lists only active schools / inactive blocked |
| TC-A036 | Invalid | Create student with missing grade level | Validation error grade level is required |
| TC-A037 | Edge | DOB boundary at 1900-01-01 | Accepted as lower bound (after 1900-01-01 rule) |
| TC-A041 | Edge | Student comment max length 5000 | Comment accepted at 5000-char boundary |

### Student Import

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A042 | Valid | Import students via valid CSV | Import runs async, rows processed, success count in history |
| TC-A045 | Valid | Download import CSV template | Template CSV downloads with expected columns |
| TC-A046 | Valid | View import history with row results | Import detail shows per-row status and errors |
| TC-A043 | Invalid | Import with malformed CSV | Row-level errors reported, failed rows flagged |
| TC-A044 | Invalid | Import detects duplicate by email | Duplicate detection flags row, no duplicate created |

### Student Documents

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A047 | Valid | Upload document for a student | Document stored, listed with metadata |
| TC-A049 | Valid | Download a student document | File downloads after authorization check |
| TC-A048 | Invalid | Upload with empty document type | Validation error, document not stored |
| TC-A050 | Edge | Delete a student document | Document soft-deleted, removed from list |

### Leads

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A051 | Valid | Create a lead | Lead created and shown in lead list |
| TC-A053 | Valid | Add a note to a lead | Note saved against the lead |
| TC-A054 | Valid | Convert lead to student | Student created from lead, lead marked converted |
| TC-A055 | Valid | Update lead status | Lead status updated in list |
| TC-A052 | Invalid | Create lead with missing required field | Validation error, lead not created |
| TC-A056 | Edge | Delete a lead | Lead removed from list |

### Services

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A057 | Valid | Create a service | Service created and listed |
| TC-A059 | Valid | Edit service and toggle status | Service updated, status reflected in list |
| TC-A058 | Invalid | Create service with duplicate name | Unique validation error |

### Positions

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A060 | Valid | Create a position | Position created and listed |
| TC-A061 | Invalid | Create position with empty name | Validation error |
| TC-A062 | Edge | Toggle position active status | Status updated |

### Service Aliases

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A063 | Valid | Create a service alias | Alias created and listed |
| TC-A064 | Invalid | Create alias with missing service | Validation error |

### SSAs

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A065 | Valid | Create SSA with valid scheduling parameters | SSA created status Pending, THO minutes calculated |
| TC-A071 | Valid | Assign therapist to SSA | Therapist assigned, SSA eligible for activation |
| TC-A072 | Valid | Unassign therapist from SSA | Therapist removed from SSA |
| TC-A074 | Valid | Import SSAs via CSV | SSAs imported, history shows results |
| TC-A066 | Invalid | Create SSA with end date before start | Validation error - end date must be after start date |
| TC-A067 | Invalid | Frequency service without frequency set | Validation error - frequency and sessions required |
| TC-A068 | Invalid | Minutes per session out of range | Validation error - must be 5 to 1440 |
| TC-A069 | Invalid | Activate SSA without assigned therapist | Blocked - activation requires an assigned therapist |
| TC-A073 | Invalid | Primary service read-only on edit | Primary service field is read-only / change rejected |
| TC-A070 | Edge | Minutes per session boundary 1440 | Accepted at upper boundary |

### SSA Goals

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A075 | Valid | Add a goal to an SSA | Goal created and listed under the SSA |
| TC-A077 | Valid | Change goal status | Goal status updated |
| TC-A076 | Invalid | Add goal with missing description | Validation error, goal not created |

### Contracts

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A078 | Valid | Create school contract | School contract created and listed |
| TC-A080 | Valid | Change contract status | Status updated in list |
| TC-A081 | Valid | Download contract document | Document file downloads |
| TC-A082 | Valid | Create therapist contract | Therapist contract created and listed |
| TC-A079 | Invalid | Create school contract missing dates | Validation error, not saved |

### Session Logs

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A083 | Valid | Approve a submitted session log | Status becomes APPROVED, eligible for billing |
| TC-A085 | Valid | Send back session log with reason | Status SENT_BACK, therapist notified, reason stored |
| TC-A087 | Valid | Cancel a session log | Log cancelled, removed from billing pipeline |
| TC-A088 | Valid | Edit session log before approval | Changes saved via UpdateSessionLogRequest |
| TC-A089 | Valid | Filter session logs by status and date | Table shows only matching logs |
| TC-A090 | Valid | Import session logs via CSV | Logs imported, history shows row results |
| TC-A084 | Invalid | Approve a log not in submitted status | Approve blocked - only SUBMITTED logs can be approved |
| TC-A086 | Invalid | Send back without a reason | Validation error - reason required |
| TC-A091 | Edge | Session log list empty state | Empty state shown, no errors |

### QGlob Requests

> **Built but not yet documented in the wiki** - dev should backfill a PRD. Test cases derived from the route inventory.

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A092 | Valid | View QGlob request detail | Request detail renders |
| TC-A093 | Valid | Respond to a QGlob request | Response recorded, request updated |
| TC-A094 | Edge | QGlob requests list empty state | Empty state shown |

### Schedule Calendar

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A095 | Valid | View admin schedule calendar | Calendar renders with events |
| TC-A096 | Valid | Open a schedule event detail | Event detail shown |
| TC-A097 | Edge | Calendar with no events | Calendar renders empty without errors |

### Analytics

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A098 | Valid | View analytics dashboard | Analytics renders with school/therapist insights |
| TC-A099 | Edge | Analytics with no data | Renders with zero/empty states |

### Notifications

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A100 | Valid | View notification center | Notifications list renders with unread badge |
| TC-A101 | Valid | Mark a notification as read | Notification marked read, unread count decreases |
| TC-A102 | Valid | Mark all notifications as read | All notifications marked read, badge clears |
| TC-A103 | Edge | Delete a notification | Notification removed from list |

### Settings

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A104 | Valid | Update admin settings | Settings saved and reflected on reload |
| TC-A105 | Invalid | Save settings with invalid value | Validation error, settings not saved |

### Reports

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-A106 | Valid | View SSA utilization report | Utilization report renders with data |
| TC-A107 | Valid | View caseload and assignment report | Caseload report renders, including unassigned section |
| TC-A108 | Valid | View SSA expirations report | Expirations report lists upcoming/expired SSAs |
| TC-A109 | Valid | Export a report | Report exports to file |
| TC-A110 | Edge | Report with no matching data | Report renders empty state, no errors |
