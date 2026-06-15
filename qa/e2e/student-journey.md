# E2E: Student Journey

**Flow:** Admin creates student → Assigns SSA → Therapist schedules → Logs session → Admin approves → Student sees data

**Dusk test:** `tests/BrowserQA/E2E/QaStudentJourneyBrowserTest.php`

## Steps

| # | Actor | Action | Verify |
|---|-------|--------|--------|
| 1 | Admin | Creates student with school assignment | Student appears in admin list |
| 2 | Admin | Creates SSA for student with service | SSA status = PENDING |
| 3 | Admin | Assigns therapist to SSA | SSA status → ACTIVE |
| 4 | Therapist | Logs in, creates schedule for student | Schedule on calendar |
| 5 | Therapist | Submits session log from schedule | Log status → SUBMITTED |
| 6 | Admin | Approves session log | Log status → APPROVED |
| 7 | Student | Logs in, views dashboard | Upcoming schedule visible |
| 8 | Student | Views session history | Approved session listed |
| 9 | Student | Views goals | Active goals visible |

## Pass Criteria
- [ ] Each step transitions correctly
- [ ] Student sees only their own data
- [ ] No step produces a 500 error
- [ ] Timezone displayed correctly on student dashboard

## Factory Setup (minimal)
```php
// See qa/e2e/test-data.md TD-E001
// Chain the full setup: school → therapist → student → SSA → schedule → session log
```
