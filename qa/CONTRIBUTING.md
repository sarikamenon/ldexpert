# Contributing Test Cases to LD Expert Bird QA Framework

A guide for adding new test cases to the QA automation framework. Test cases are defined in Excel, auto-generated as Dusk tests, and tracked in this changelog.

---

## Overview

The contribution process is **simple and safe**:

1. **Add a row** to `qa/LD-Expert-QA.xlsx` with test details
2. **Run `/qa-generate-tests`** to auto-generate PHP test files
3. **Run the tests** with `/qa-{role}` to verify they work
4. **Update the changelog** to document the addition
5. **Commit both files** to the repo

No manual PHP coding required — the framework generates Dusk tests from your Excel rows.

---

## Prerequisites

- **Read Access:**
  - [`qa/LD-Expert-QA.xlsx`](./LD-Expert-QA.xlsx) — Master test plan
  - [`qa/CHANGELOG.md`](./CHANGELOG.md) — Version history
  - [`app/docs/BLADE_GUIDELINES.md`](../app/docs/BLADE_GUIDELINES.md) — UI/UX standards
  - [`app/wiki/`](../app/wiki/) — Feature PRDs (e.g., `wiki/admin/dashboard.md`)

- **Write Access:**
  - `qa/LD-Expert-QA.xlsx` (add rows)
  - `qa/CHANGELOG.md` (document additions)

- **Tools:**
  - Claude Code with `/qa-generate-tests` skill
  - Docker (for running tests)
  - GitHub (for committing)

---

## Step 1: Plan Your Test Cases

Before adding rows to Excel, plan what you're testing.

### Choose a Feature Area

Test cases are organized by **role**:

| Role | Sheet | Prefix | Feature Examples |
|------|-------|--------|------------------|
| **Admin** | Admin | TC-A* | Schools, students, therapists, SSAs, invoices, bills |
| **Therapist** | Therapist | TC-T* | Schedule, calendar, session logs, students, paystubs |
| **Student** | Student | TC-S* | Dashboard, schedule, goals, messages |
| **Finance** | Finance | TC-F* | Invoicing, billing, payments, ledger |
| **E2E** | E2E | TC-E* | Cross-role workflows, end-to-end flows |

### Read the PRD

Every feature should have a **Product Requirements Document (PRD)** in `app/wiki/`. For example:

- `app/wiki/admin/dashboard.md` — Admin Dashboard spec
- `app/wiki/admin/schools.md` — Schools CRUD spec
- `app/wiki/therapist/schedule.md` — Therapist Schedule spec

**Read the PRD first** to understand:
- What UI elements exist (buttons, forms, tables)
- What workflows the user performs
- What data is displayed
- What validations apply

### Identify Test Scenarios

For a feature, identify **happy path + error cases**:

```
Feature: Admin Login
├─ Happy Path:  Valid email + password → Logged in
├─ Error Case 1: Invalid password → Error shown
├─ Error Case 2: Non-existent email → Error shown
└─ Error Case 3: Empty form → Validation error
```

---

## Step 2: Add Rows to Excel

Open `qa/LD-Expert-QA.xlsx` and add test case rows to the appropriate sheet.

### Row Format

Each test case row contains:

| Column | Format | Example | Notes |
|--------|--------|---------|-------|
| **Test Code** | `TC-{ROLE}{NUMBER}` | `TC-A006` | Must be unique per sheet |
| **Description** | Plain text, ≤100 chars | `Admin can create a school with all required fields` | Describes what's being tested |
| **Steps** | Numbered list, 1–10 steps | 1. Navigate to /admin/schools/create<br>2. Fill "School Name"<br>3. Click "Add School" | Each step = one user action |
| **Expected Result** | Plain text | `User is redirected to schools list, new school shown` | What should happen on success |
| **Priority** | P1 / P2 / P3 | P1 | P1 = critical, P2 = important, P3 = nice-to-have |
| **Last Run** | `YYYY-MM-DD` or empty | `2026-06-08` | Leave empty for new cases |
| **Last Result** | PASS/FAIL/PENDING | PENDING | Leave as PENDING for new cases |

### Example: Admin School Creation

```
Test Code       | TC-A006
Description     | Admin can create a school with all required fields
Steps           | 1. Login as admin
                | 2. Navigate to /admin/schools/create
                | 3. Enter full name: "QA Test Elementary"
                | 4. Enter display name: "QA Elementary"
                | 5. Select state: "CA"
                | 6. Select timezone: "America/Los_Angeles"
                | 7. Click "Add School/Family" button
                | 8. Wait for redirect to /admin/schools
Expected Result | Page shows "QA Elementary" in schools list
Priority        | P1
Last Run        | (empty)
Last Result     | PENDING
```

### Naming Conventions

**Test Code:**
- Must follow `TC-{ROLE}{NUMBER}` format
- Number should continue sequence from existing tests
- Examples: `TC-A031` (next Admin), `TC-T026` (next Therapist)

**Description:**
- Start with action verb: "Admin can...", "User can...", "System validates..."
- Include the specific action and result
- Under 100 characters for clarity

**Steps:**
- One action per step, numbered 1–N
- Use imperative: "Fill email field", "Click Submit", not "The user fills..."
- Reference UI elements: buttons, form fields, links by their visible labels
- Use page URLs when navigating: `/admin/schools`, `/therapist/schedule`

**Expected Result:**
- Describe the visible outcome
- Include: page redirect, element visibility, success message, data change
- Example: "User is redirected to /admin/schools, new school visible in table"

---

## Step 3: Generate Tests

Run the skill to auto-generate Dusk test files from your Excel rows:

```bash
/qa-generate-tests
```

**What happens:**
1. Reads `qa/LD-Expert-QA.xlsx` (all sheets)
2. For each row, generates a Pest `it()` test block
3. Converts steps into Dusk browser actions (`.visit()`, `.type()`, `.click()`, etc.)
4. Creates/updates files under `app/tests/BrowserQA/{Role}/`
5. Adds factory calls for test data (QA-prefixed users)

**Generated file structure:**
```
app/tests/BrowserQA/
├── Admin/
│   ├── QaAdminCoreBrowserTest.php
│   ├── QaAdminSchoolsBrowserTest.php
│   └── ... (split by feature area)
├── Therapist/
│   ├── QaTherapistScheduleBrowserTest.php
│   └── ...
├── Student/
│   ├── QaStudentBrowserTest.php
│   └── ...
├── Finance/
│   ├── QaFinanceBrowserTest.php
│   └── ...
├── E2E/
│   ├── QaStudentJourneyBrowserTest.php
│   └── ...
└── Smoke/
    └── QaAppSmokeBrowserTest.php
```

**Generated test example:**
```php
it('TC-A006 admin can create a school with all required fields', function (): void {
    $admin = User::factory()->admin()->qa()->create();
    
    $this->loginAndVisit($admin, '/admin/schools/create')
        ->waitFor('input[name="full_name"]')
        ->type('full_name', 'QA Test Elementary')
        ->type('display_name', 'QA Elementary')
        ->select('state', 'CA')
        ->select('timezone', 'America/Los_Angeles')
        ->press('Add School/Family')
        ->waitForLocation('/admin/schools')
        ->assertSee('QA Elementary');
});
```

---

## Step 4: Run & Verify Tests

Run the test suite to verify your new tests work:

```bash
# Run all tests for a role
/qa-admin
/qa-therapist
/qa-student
/qa-finance
/qa-e2e

# Or run smoke tests (fast subset)
/qa-smoke
```

**Monitor for:**
- ✅ All tests pass (green)
- ❌ Test fails → Debug the generated code or Excel row
- ⚠️ Test times out → Issue with `waitFor()` selectors

**If tests fail:**
1. Check the HTML report in `qa/reports/`
2. Look at screenshots in `app/tests/BrowserQA/screenshots/`
3. Review the generated PHP in `app/tests/BrowserQA/{Role}/`
4. Fix the Excel row (steps, selectors, expected results)
5. Re-run `/qa-generate-tests`
6. Re-run the test

---

## Step 5: Update the Changelog

Document your addition in `qa/CHANGELOG.md`:

### Entry Template

```markdown
#### Entry X.X.X — Feature Name
- **Date Added:** YYYY-MM-DD (today's date)
- **Test Codes:** TC-A031 to TC-A040
- **Feature Areas:**
  - Feature Area 1 (test codes)
  - Feature Area 2 (test codes)
  - Feature Area 3 (test codes)
- **App Version:** vX.Y.Z+ (minimum supported version)
- **Source:** `qa/{role}/test-plan.md`, `qa/LD-Expert-QA.xlsx` — {Sheet} sheet
- **Status:** ✅ Generated & Passing (or ⏳ In Progress)
- **Notes:** [Optional special handling, known issues, etc.]
```

### Example Entry

```markdown
#### Entry 1.0.6 — Admin SSA Management Tests (NEW)
- **Date Added:** 2026-06-15
- **Test Codes:** TC-A031 to TC-A040
- **Feature Areas:**
  - SSA Creation (TC-A031–A034)
  - Therapist Assignment (TC-A035–A037)
  - Goal Management (TC-A038–A040)
- **App Version:** v1.0.0+
- **Source:** `qa/admin/test-plan.md`, `qa/LD-Expert-QA.xlsx` — Admin sheet
- **Status:** ✅ Generated & Passing
- **Notes:** Tests verify UTC timezone handling for SSA dates; goal CRUD operations
```

---

## Step 6: Commit & Push

Commit both the Excel file and changelog:

```bash
git add qa/LD-Expert-QA.xlsx qa/CHANGELOG.md
git commit -m "Add TC-A031–A040 SSA Management test cases

- Added 10 test cases for SSA creation, assignment, and goal management
- Updated qa/LD-Expert-QA.xlsx (Admin sheet)
- Updated qa/CHANGELOG.md with Entry 1.0.6
- All tests passing locally"

git push origin your-feature-branch
```

---

## Best Practices

### ✅ DO

- **Reference PRDs:** Link to `app/wiki/` docs in test descriptions
- **Follow naming conventions:** `TC-{ROLE}{NUMBER}`, unique codes
- **Keep steps focused:** Each step = one user action
- **Test the happy path first:** Then add error cases
- **Use factory helpers:** `User::factory()->admin()->qa()` creates QA-prefixed users
- **Include waits:** Use `waitFor()` for AJAX, `waitForLocation()` for redirects
- **Mark smoke tests:** Add `.group('smoke')` to fast critical tests
- **Document timezone handling:** If testing dates/times, note UTC conversions
- **Run tests before committing:** Verify they pass locally

### ❌ DON'T

- **Hardcode selectors:** Use `@dusk` attributes or visible text (buttons)
- **Test the same thing twice:** Avoid duplicate test cases
- **Skip error cases:** Include happy path + relevant failures
- **Write too many steps:** Keep tests focused (8–10 steps max)
- **Use flaky waits:** Avoid `pause()` — use `waitFor()` instead
- **Mix roles in one test:** E2E tests should stay in E2E sheet
- **Forget QA data cleanup:** Tests should use `->qa()` factory, cleanup is auto
- **Commit failing tests:** Fix them before pushing

---

## Common Issues & Fixes

### Test Times Out

**Symptom:** Test hangs waiting for element

**Fixes:**
- Verify selector is correct (use browser DevTools to inspect)
- Add longer timeout: `waitFor('selector', 10)` (seconds)
- Check if page load requires AJAX — use `waitFor()` not `pause()`
- Verify element exists in app (check browser manually first)

**Example:**
```php
// Bad: Times out
$browser->pause(3000)->assertSee('Success');

// Good: Waits for element
$browser->waitFor('[data-test="success-message"]')->assertSee('Success');
```

### Test Passes Locally, Fails in CI

**Symptom:** Works in Docker, fails in GitHub Actions

**Likely causes:**
- Database not seeded (use factories)
- Timezone mismatch (use UTC in tests)
- Missing data dependencies (create related records)
- Flaky selectors (verify elements are stable)

**Fix:**
- Run tests in Docker locally first: `docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/{Role}/'`
- Add factories for dependent data
- Verify timezone handling

### Test Data Not Cleaning Up

**Symptom:** QA-prefixed records remain in database after test

**Fix:**
- Verify `QaDuskTestCase` tearDown cleanup is working
- Check if records are truly QA-prefixed (`qa*` in email, `QA *` in school name)
- Run cleanup manually: Check `cleanUpQaTestData()` in `QaDuskTestCase`

---

## Testing Checklist Before Commit

- [ ] Excel row added to correct sheet
- [ ] Test code is unique (TC-A031, not duplicate)
- [ ] Description is clear and concise (≤100 chars)
- [ ] Steps are specific and actionable
- [ ] Expected result describes visible outcome
- [ ] Priority is set (P1/P2/P3)
- [ ] Last Run & Last Result columns are empty (or filled if already run)
- [ ] `/qa-generate-tests` ran successfully
- [ ] Tests run and pass: `/qa-{role}`
- [ ] Screenshot cleanup happens (tests use `.qa()` factory)
- [ ] Changelog updated with new Entry
- [ ] Both files committed: `qa/LD-Expert-QA.xlsx` + `qa/CHANGELOG.md`

---

## Questions?

Refer to:
- **Framework setup:** [`qa/SETUP.md`](./SETUP.md)
- **Test strategy:** [`qa/TEST_PLAN.docx`](./TEST_PLAN.docx)
- **Feature PRDs:** [`app/wiki/`](../app/wiki/) (e.g., `wiki/admin/dashboard.md`)
- **Generated tests:** [`app/tests/BrowserQA/`](../app/tests/BrowserQA/)

---

## Related Files

| File | Purpose |
|------|---------|
| `qa/LD-Expert-QA.xlsx` | Master test plan (rows = tests) |
| `qa/CHANGELOG.md` | Version history & test additions |
| `qa/SETUP.md` | Framework dependencies & setup |
| `qa/{role}/test-plan.md` | Feature area breakdown (Admin, Therapist, Student, Finance) |
| `qa/{role}/test-data.md` | Test data requirements & setup |
| `app/tests/BrowserQA/` | Generated Dusk test files |
| `app/wiki/` | Feature PRDs (read before writing tests) |
| `app/docs/BLADE_GUIDELINES.md` | UI/UX standards (reference for selectors) |
