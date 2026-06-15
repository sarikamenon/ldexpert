# Browser QA Test Flow

Complete overview of the QA browser testing process for LD Expert Bird.

---

## What Is Browser QA?

**Browser QA** = Automated testing of user workflows using Dusk (Selenium-based browser automation)

Tests real user interactions:
- Login flows
- Create/edit/delete operations
- Form submissions
- Navigation
- Role-based access control
- End-to-end workflows

---

## The Complete Flow

```
┌─────────────────────────────────────────────────────────────┐
│ Step 1: Wiki Update (Developer)                            │
│ └─ Developer updates app/wiki/admin/dashboard.md           │
└────────────────┬────────────────────────────────────────────┘
                 │
┌─────────────────────────────────────────────────────────────┐
│ Step 2: Generate Test Scenarios (QA Engineer)              │
│ └─ Run: /qa-create-scenarios admin                         │
│ └─ Output: qa/admin/test-plan.md + Excel rows              │
└────────────────┬────────────────────────────────────────────┘
                 │
┌─────────────────────────────────────────────────────────────┐
│ Step 3: Generate Dusk Tests (Auto)                         │
│ └─ Run: /qa-generate-tests                                 │
│ └─ Reads: qa/LD-Expert-QA.xlsx                             │
│ └─ Outputs: app/tests/BrowserQA/Admin/*.php                │
└────────────────┬────────────────────────────────────────────┘
                 │
┌─────────────────────────────────────────────────────────────┐
│ Step 4: Run Tests (QA)                                     │
│ ├─ Run: /qa-smoke (quick check)                            │
│ ├─ Run: /qa-admin (admin tests)                            │
│ ├─ Run: /qa-therapist (therapist tests)                    │
│ ├─ Run: /qa-student (student tests)                        │
│ ├─ Run: /qa-finance (billing tests)                        │
│ └─ Run: /qa-e2e (cross-role flows)                         │
│ └─ Output: qa/reports/role-YYYY-MM-DD-HHMM.html            │
└────────────────┬────────────────────────────────────────────┘
                 │
┌─────────────────────────────────────────────────────────────┐
│ Step 5: Review Results (QA)                                │
│ ├─ Check HTML report                                        │
│ ├─ Review screenshots (failures)                            │
│ ├─ Fix tests if needed                                      │
│ └─ Commit: qa/LD-Expert-QA.xlsx + qa/CHANGELOG.md           │
└─────────────────────────────────────────────────────────────┘
```

---

## Step-by-Step Commands

### Step 1: Detect Wiki Change (Manual)
```bash
# Developer commits wiki update
# QA gets notified via Slack or PR comment
# QA reviews what changed
```

### Step 2: Generate Test Scenarios
```bash
/qa-create-scenarios admin
# Reads: app/wiki/admin/*.md
# Writes: qa/admin/test-plan.md
# Appends: Rows to qa/LD-Expert-QA.xlsx
```

### Step 3: Generate Dusk Tests
```bash
/qa-generate-tests
# Reads: qa/LD-Expert-QA.xlsx (all sheets)
# Writes: app/tests/BrowserQA/{Role}/*.php
# Generates: PHP Dusk test code
```

### Step 4: Run Tests by Role

```bash
# Quick sanity check (all roles, ~3 min)
/qa-smoke

# Admin workflows (create school, student, therapist, etc.)
/qa-admin

# Therapist workflows (schedule, session logs, paystubs)
/qa-therapist

# Student workflows (view schedule, goals, etc.)
/qa-student

# Finance/billing workflows
/qa-finance

# End-to-end cross-role flows
/qa-e2e

# Or run all at once
/qa
```

### Step 5: Review Reports

```
qa/reports/
├─ smoke-2026-06-08-1537.html        ← HTML visual report
├─ smoke-2026-06-08-1537.md          ← Markdown summary
├─ admin-2026-06-08-1442.html        ← Admin test results
├─ therapist-2026-06-08-1500.html    ← Therapist results
└─ ...
```

---

## Key Components

### 1. Master Test Plan (Excel)
```
qa/LD-Expert-QA.xlsx
├─ Admin sheet (TC-A001 to TC-A040)
├─ Therapist sheet (TC-T001 to TC-T025)
├─ Student sheet (TC-S001 to TC-S020)
├─ Finance sheet (TC-F001 to TC-F015)
└─ E2E sheet (TC-E001 to TC-E020)

Columns: Test Code | Description | Steps | Expected Result | Priority
```

### 2. Test Plans (Markdown)
```
qa/admin/test-plan.md
├─ Feature areas (Schools, Students, Therapists, SSAs, etc.)
├─ Test scenarios per area
└─ References to PRDs (app/wiki/admin/*.md)
```

### 3. Generated Dusk Tests
```
app/tests/BrowserQA/
├─ Admin/
│  ├─ QaAdminCoreBrowserTest.php (login, dashboard)
│  ├─ QaAdminSchoolsBrowserTest.php (school CRUD)
│  └─ ...
├─ Therapist/
├─ Student/
├─ Finance/
└─ E2E/
```

### 4. Test Reports
```
qa/reports/
├─ HTML: Visual report with pass/fail summary
├─ Markdown: Test results table
└─ Artifacts: Screenshots of failures
```

---

## Test Data & Cleanup

### QA Test Data Prefix
Tests create data with "QA " prefix for easy identification:
```
School Name: "QA Test School"
Student Email: "qa.student@test.com"
Therapist Email: "qa.therapist@test.com"
```

### Automatic Cleanup
After each test run:
1. Delete all `QA *` schools
2. Delete all `qa*` email users
3. Cascade delete related records (session logs, SSAs, etc.)
4. Database stays clean ✅

---

## Running Tests

### Local Development
```bash
# Ensure Docker is running
docker compose ps

# Run smoke tests (quick)
/qa-smoke

# Run specific role tests
/qa-admin
/qa-therapist
/qa-student
/qa-finance
/qa-e2e

# Or all together
/qa
```

### CI/CD (Automatic)
```yaml
# Runs daily at 8 AM via GitHub Actions
.github/workflows/browser-qa.yml
├─ Triggers: schedule + manual dispatch
├─ Database: Fresh migrate on bird_test
├─ Tests: Runs all BrowserQA tests
└─ Reports: Email + GitHub artifacts
```

---

## Test Types

```
┌────────────────────────────────────────────────────┐
│ Smoke Tests (/qa-smoke)                           │
├────────────────────────────────────────────────────┤
│ • Admin login                                      │
│ • Student login                                    │
│ • Therapist login                                  │
│ • Home page redirect                               │
│ • Role isolation (403 checks)                      │
│                                                    │
│ Time: ~3-4 minutes                                 │
│ Purpose: Quick sanity check                        │
└────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────┐
│ Admin Tests (/qa-admin)                           │
├────────────────────────────────────────────────────┤
│ • Create/edit/delete schools                       │
│ • Create/edit/delete students                      │
│ • Create/edit/delete therapists                    │
│ • Create/manage SSAs                               │
│ • Approve session logs                             │
│ • Generate invoices                                │
│ • Record payments                                  │
│ • View reports                                     │
│                                                    │
│ Time: ~10-15 minutes                               │
│ Purpose: Test admin workflows                      │
└────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────┐
│ Therapist Tests (/qa-therapist)                   │
├────────────────────────────────────────────────────┤
│ • Login                                            │
│ • View/manage schedule                             │
│ • View calendar                                    │
│ • Log sessions                                     │
│ • Access student list                              │
│ • View paystubs                                    │
│                                                    │
│ Time: ~8-10 minutes                                │
│ Purpose: Test therapist workflows                  │
└────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────┐
│ Student Tests (/qa-student)                       │
├────────────────────────────────────────────────────┤
│ • Login                                            │
│ • View dashboard                                   │
│ • View schedule                                    │
│ • Track goals                                      │
│ • Message therapist                                │
│                                                    │
│ Time: ~5-8 minutes                                 │
│ Purpose: Test student workflows                    │
└────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────┐
│ Finance Tests (/qa-finance)                       │
├────────────────────────────────────────────────────┤
│ • Create invoices                                  │
│ • Record payments                                  │
│ • Therapist billing                                │
│ • Ledger entries                                   │
│ • Reconciliation                                   │
│                                                    │
│ Time: ~5-8 minutes                                 │
│ Purpose: Test billing workflows                    │
└────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────┐
│ E2E Tests (/qa-e2e)                               │
├────────────────────────────────────────────────────┤
│ • Student Journey                                  │
│ • Therapist Session to Billing                     │
│ • Admin Audit Flow                                 │
│                                                    │
│ Time: ~10-15 minutes                               │
│ Purpose: Cross-role integration flows              │
└────────────────────────────────────────────────────┘
```

---

## Decision Tree: Which Tests to Run

```
Am I...

├─ Just checking if app loads?
│  └─ Run: /qa-smoke (3 min)
│
├─ Testing admin functionality?
│  └─ Run: /qa-admin (15 min)
│
├─ Testing therapist features?
│  └─ Run: /qa-therapist (10 min)
│
├─ Testing student features?
│  └─ Run: /qa-student (8 min)
│
├─ Testing billing/finance?
│  └─ Run: /qa-finance (8 min)
│
├─ Testing cross-role workflows?
│  └─ Run: /qa-e2e (15 min)
│
├─ About to deploy?
│  └─ Run: /qa-smoke + /qa-admin (20 min)
│
└─ Doing comprehensive QA?
   └─ Run: /qa (all tests, ~1 hour)
```

---

## Files & Locations

```
qa/
├─ LD-Expert-QA.xlsx          ← Master test plan (all test cases)
├─ CHANGELOG.md               ← Test version history
├─ CONTRIBUTING.md            ← How to add test cases
├─ SETUP.md                   ← Dependencies setup
├─ BROWSER_QA_FLOW.md         ← This file
│
├─ admin/
│  ├─ test-plan.md            ← Admin feature areas & scenarios
│  └─ test-data.md            ← Admin test data setup
│
├─ therapist/
│  ├─ test-plan.md
│  └─ test-data.md
│
├─ student/
│  ├─ test-plan.md
│  └─ test-data.md
│
├─ finance/
│  ├─ test-plan.md
│  └─ test-data.md
│
├─ e2e/
│  ├─ student-journey.md
│  ├─ therapist-session-to-billing.md
│  ├─ admin-audit-flow.md
│  └─ test-data.md
│
└─ reports/
   └─ role-YYYY-MM-DD-HHMM.html (test results)

app/tests/BrowserQA/
├─ QaDuskTestCase.php         ← Base class (cleanup, helpers)
├─ screenshots/               ← Failure screenshots
├─ console/                   ← Browser console logs
│
├─ Admin/
│  ├─ QaAdminCoreBrowserTest.php
│  ├─ QaAdminSchoolsBrowserTest.php
│  └─ ...
│
├─ Therapist/
├─ Student/
├─ Finance/
└─ E2E/
```

---

## Next Steps After Tests Pass

Once `/qa-smoke` passes (11/11 ✅):

1. **Run comprehensive tests**
   ```bash
   /qa-admin
   /qa-therapist
   /qa-student
   /qa-finance
   /qa-e2e
   ```

2. **Write manual E2E tests** for complex workflows
   - School creation → Student enrollment → Therapist assignment
   - Session logging → Approval → Billing

3. **Add Feature tests** for backend logic
   - Validation rules
   - Business calculations
   - Permission checks

4. **Integrate with CI/CD**
   - Tests run on every push
   - Block deploy if tests fail

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Tests timeout | Increase wait time, check selectors |
| Login fails | Verify credentials, check form structure |
| Screenshots missing | Check `app/tests/BrowserQA/screenshots/` |
| Database dirty | Run cleanup manually or use `migrate:fresh` |
| Docker not running | `docker compose up -d` |
| Tests pass locally, fail in CI | Check .env.testing database config |

---

## Summary

✅ **Browser QA Flow = Test user workflows automatically**

1. Wiki changes → Generate scenarios → Generate tests → Run tests → Review results
2. All tests use Dusk (real browser automation)
3. Tests are generated from Excel (no manual PHP coding for simple tests)
4. Results in HTML/MD reports with pass/fail summary
5. Automatic data cleanup keeps database clean
6. Integrated with CI/CD (daily runs + on-demand)

**Start with:** `/qa-smoke` (quick check, ~3 min)  
**Then run:** `/qa-admin`, `/qa-therapist`, etc. (detailed testing)
