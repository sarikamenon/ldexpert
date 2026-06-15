---
name: qa-generate-tests
description: Write Laravel Dusk browser tests for LD Expert Bird from QA test plans. Reads the master Excel file qa/LD-Expert-QA.xlsx and produces PHP Dusk test files in tests/BrowserQA/. Use when converting a QA test plan into automated browser tests. Triggers on "write dusk tests", "automate test cases", "generate browser tests", "implement test plan".
---

# QA Engineer — Dusk Test Writer for LD Expert Bird

You are a QA engineer for LD Expert Bird, a school therapy management platform. Your job is to read test cases and turn them into automated Dusk browser tests.

---

## Pre-Generation Checklist

**Before running the `/qa-generate-tests` skill, follow these steps to ensure tests will work:**

- [ ] **1. Browser open to running app** — `docker compose up -d`, then visit `http://localhost:8080`
- [ ] **2. Login to test environment** — Log in as system admin: `develop.ldexpert@gmail.com` / `Password123!`
- [ ] **3. Inspect key pages** — For each route in the Excel, open that page in the browser
- [ ] **4. Discover selectors** — Use DevTools Inspector (F12) to find actual HTML selectors (id, name, data-testid, etc.)
- [ ] **5. Document selectors** — Create `qa/{role}/selectors.md` with selector reference (see Locator Discovery section)
- [ ] **6. Update Excel Step columns** — Replace generic descriptions with actual selectors
- [ ] **7. Validate selectors** — In DevTools Console, run `document.querySelector('your-selector')` to confirm each one
- [ ] **8. Run `/qa-generate-tests`** — Generate tests using the updated Excel with actual selectors

**Example before & after:**

| Before (Generic) | After (Specific Selectors) |
|---|---|
| Step 1: "Visit /admin/schools" | Step 1: "Visit /admin/schools" |
| Step 2: "Click Create button" | Step 2: "Click `a[href='/admin/schools/create']`" |
| Step 3: "Type school name" | Step 3: "Type `input#full_name` as 'QA School'" |
| Step 4: "Select state" | Step 4: "Select `select#state` as 'CA'" |
| Step 5: "Submit" | Step 5: "Click `button[type='submit']`" |

---

## Where to read from

The master test case file is `qa/LD-Expert-QA.xlsx`. It has one sheet per role:

| Sheet | Write files to (project root) | Docker run path |
|-------|-------------------------------|-----------------|
| Admin | `app/tests/BrowserQA/Admin/` | `tests/BrowserQA/Admin/` |
| Therapist | `app/tests/BrowserQA/Therapist/` | `tests/BrowserQA/Therapist/` |
| Student | `app/tests/BrowserQA/Student/` | `tests/BrowserQA/Student/` |
| Finance | `app/tests/BrowserQA/Finance/` | `tests/BrowserQA/Finance/` |
| E2E | `app/tests/BrowserQA/E2E/` | `tests/BrowserQA/E2E/` |

Read **all rows** from the first row after the column headers to the last non-empty row. Never stop at a hardcoded TC ID — the number of test cases grows over time as new scenarios are added, and the skill must always process all of them.

**Use the Excel MCP to read the file directly** — the `excel` MCP server must be configured in the user's Claude Code settings. This means you can open and read `qa/LD-Expert-QA.xlsx` inline without spawning a separate agent. If the MCP is not available, fall back to the xlsx skill (Python/openpyxl).

> **First time running this skill?** See [`qa/SETUP.md`](../../../../qa/SETUP.md) for installation and configuration of the Excel MCP server, the xlsx skill fallback, and how to verify both before proceeding.

For each row, read all columns. Here's what each column means:

### Excel Column Schema

| Column | Example | Used For | Notes |
|--------|---------|----------|-------|
| **TC ID** | TC-A001 | Test function name | Unique identifier; format: `TC-{Role}{Number}` |
| **Feature** | Authentication | Grouping tests by feature | Used by Pass 0 for incremental generation (diff against already-covered features) |
| **Condition** | Valid \| Invalid \| Edge | Test type classifier | Valid = happy path, Invalid = error handling, Edge = boundary conditions |
| **Test Name** | Admin login with correct credentials | Test description | Becomes the `it('...')` function description in generated PHP |
| **Priority** | P0 \| P1 \| P2 | Execution order | P0 = critical (auth, role isolation), P1 = main features, P2 = nice-to-have |
| **Preconditions** | System admin exists (develop.ldexpert@gmail.com) | Test setup context | Documents what DB state or factory setup is needed before test runs |
| **Step 1, 2, 3...** | Login → click Schools → verify | User actions | Converted 1:1 into browser automation code (`$browser->type()`, `$browser->click()`, etc.) |
| **Expected Result** | Redirects to /admin/dashboard, user is authenticated | Assertions | Converted into `$browser->assert*()` and `$this->assertDatabase*()` calls |
| **Dusk Test File** | QaAdminCoreBrowserTest.php | File destination | Which PHP test file this case goes into; auto-populated after generation |

Also read `qa/{role}/test-data.md` to understand what database records are needed for preconditions.

**After writing tests**, use the Excel MCP to update the Dusk Test File column for any rows where it is empty, so the Excel stays in sync with the generated test files.

---

## Coverage rule — all condition types, every feature

Every feature area must have tests covering all three condition types. The number of tests is not fixed — write as many as the feature needs, but never skip a condition type.

- **Positive tests (Valid)** — the happy path works correctly when given good data and the system is in the right state
- **Negative tests (Invalid)** — the system correctly rejects bad input, wrong status, missing fields, or unauthorised access
- **Edge case tests** — boundary conditions such as empty state, maximum field length, zero amounts, duplicate actions, or rapid repeated submissions

A simple feature like login may need 3 tests. A complex feature like billing or imports may need 8 or more. Use your judgment based on how many realistic scenarios exist. What matters is that no condition type is missing — not that the count hits an exact number.

Read the Condition Type column in the Excel to know which type each test case is. Complete all condition types for one feature before moving to the next.

## File naming convention

Every QA-authored test file must:
- Live under `app/tests/BrowserQA/{Role}/` (project-root path for writing files)
- Start with `Qa` prefix
- End with `BrowserTest.php`

Never modify any file that does not have the `Qa` prefix. Never write into `app/tests/Browser/` — that is the developer test directory.

> Note: When referencing paths inside Docker commands use `tests/BrowserQA/{Role}/` (without the `app/` prefix) because the container WORKDIR is already `/var/www/html/app`.

---

## How many files to generate per role

**Default: one file per role.** All test cases for that role go into a single file, grouped by feature area using section comments.

| Role | Structure |
|---|---|
| Student | 1 file |
| Therapist | Split by feature group (see below) |
| Finance | 1 file |
| E2E | Split by workflow (see below) |
| Admin | Split by feature group (see below) |

**Therapist — split by feature group** because schedule creation and session management are independent workflows that will grow with new test cases over time:

| Feature group | File |
|---|---|
| Auth, dashboard, role isolation | `QaTherapistAuthBrowserTest.php` |
| School calendar events | `QaTherapistCalendarBrowserTest.php` |
| Schedule creation (single + recurring), edit, cancel | `QaTherapistScheduleBrowserTest.php` |
| Session log create, submit, resubmit SENT_BACK | `QaTherapistSessionLogsBrowserTest.php` |
| SSA goals, student comments, documents | `QaTherapistStudentsBrowserTest.php` |
| Paystub report | `QaTherapistPaystubBrowserTest.php` |

**E2E — split by user journey workflow.** Each workflow maps to its spec file in `qa/e2e/`:

| Workflow | Spec file | Test file |
|---|---|---|
| Student Journey | `qa/e2e/student-journey.md` | `QaStudentJourneyBrowserTest.php` |
| Therapist Session to Billing | `qa/e2e/therapist-session-to-billing.md` | `QaTherapistSessionToBillingBrowserTest.php` |
| Admin Audit Flow | `qa/e2e/admin-audit-flow.md` | `QaAdminAuditFlowBrowserTest.php` |

When a new E2E workflow is added, a new spec file goes in `qa/e2e/` and a corresponding test file goes in `app/tests/BrowserQA/E2E/`.

**Admin — split by feature group:**

| Feature group | File |
|---|---|
| Auth, dashboard, role isolation | `QaAdminCoreBrowserTest.php` |
| Dashboard metrics, overview cards | `QaAdminDashboardBrowserTest.php` |
| Schools: create, edit, view, list, filter, settings | `QaAdminSchoolsBrowserTest.php` |
| Therapists: create, list, contracts, capacity | `QaAdminTherapistsBrowserTest.php` |
| SSA, Session Logs, Imports, Approvals | `QaAdminSessionsBrowserTest.php` |
| Invoices, Therapist Bills, Ledger, Payments | `QaAdminBillingBrowserTest.php` |

**Rule for future growth:** when new test cases are added, place them in the matching feature group file. Only create a new file when a feature group has no existing file. Do not split preemptively.

Inside every file, separate feature areas with section comments:
```php
// ─── Authentication ──────────────────────────────────────────

it('student login with valid credentials...

// ─── Dashboard ───────────────────────────────────────────────

it('dashboard shows upcoming schedules...
```

---

## What each test file must contain

Every file must begin with `declare(strict_types=1)` and import all models and enums used.

Every test file must open with this structure:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);
```

**Why `QaDuskTestCase`:** This custom base class (defined in `app/tests/BrowserQA/QaDuskTestCase.php`) identifies all tests as part of the QA automation framework. It:
- ✅ Extends Laravel's `DuskTestCase` (browser automation + database assertions)
- ✅ Provides `createQaUser()` and `createQaSchool()` test helpers with auto-cleanup
- ✅ Uses targeted `tearDown()` that deletes only `qa*`-prefixed test records
- ✅ Preserves the seeded system admin (`develop.ldexpert@gmail.com`) across test runs
- ✅ Safe for daily CI runs without data pollution

**SAFETY:** Tests use `.env.testing` which connects to `bird_test` database, **never** production `bird`.

Every test follows three parts:
1. **Arrange** — create the database records the test needs using factories
2. **Act** — open a browser, log in, and perform the steps from the test case
3. **Assert** — verify the expected result happened, both in the browser and in the database

---

## Logging in

For most tests, log the user in directly without going through the login form — this is faster and more reliable. Only test the actual login form when the test case is specifically about authentication (valid credentials, invalid credentials, locked account).

---

## Seeded System Admin — Always Available for All Tests

The database seeder (`AdminUserSeeder.php`) creates a system admin at initial setup:
- **Email:** `develop.ldexpert@gmail.com`
- **Password:** `Password123!`

Since `QaDuskTestCase` does NOT run `migrate:fresh` (it uses targeted cleanup instead), this seeded admin persists across all test runs. Both Admin and Finance tests can rely on it always existing.

### How to use it:
```php
// Fetch the seeded admin (always exists, never deleted)
$admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();

// For tests that need a manager_id, reuse the seeded admin's ID
$manager_id = User::where('email', 'develop.ldexpert@gmail.com')->value('id');
```

### What NOT to do:
- ❌ ~~`User::factory()->admin()->create()`~~ — seeded admin already exists, no need to create
- ❌ ~~`User::factory()->admin()->qa()->create()`~~ — forbidden; `createQaUser('admin')` throws `InvalidArgumentException`
- ❌ ~~Use a `??` factory fallback~~ — if the seeded admin is missing, the test should fail (it means setup is broken)

---

## Factory Method Reference

### User Factories

```php
// Therapist — use this, not admin factory
$therapist = User::factory()->therapist()->create();
$therapist = User::factory()->therapist()->qa()->create();  // auto-cleanup version
// Then create profile:
TherapistProfile::factory()->for($therapist, 'user')->create();

// Student — use this
$student = User::factory()->student()->create();
$student = User::factory()->student()->qa()->create();      // auto-cleanup version
// Then link to school:
$student->studentProfile()->update(['school_id' => $school->id]);

// Admin — DO NOT USE
$admin = User::factory()->admin()->create();  // ❌ FORBIDDEN
// Instead fetch the seeded admin:
$admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
```

### School Factories

```php
// Regular school
$school = School::factory()->create();

// QA school (auto-cleanup with 'QA ' prefix)
$school = School::factory()->qa()->create();

// With specific attributes
$school = School::factory()->create([
    'status' => SchoolStatus::ACTIVE,
    'full_name' => 'Test School Name',
    'school_type' => SchoolType::BRICK_MORTAR,
]);
```

### Profile Factories

```php
// Therapist profile
TherapistProfile::factory()->for($therapist, 'user')->create([
    'manager_id' => $admin->id,
    'timezone' => 'America/New_York',
]);

// Student profile
$student->studentProfile()->update([
    'school_id' => $school->id,
    'timezone' => 'America/Los_Angeles',
]);
```

### Service & SSA Factories

```php
// Service
$service = Service::factory()->create(['name' => 'Speech Therapy']);

// Service Support Agreement (SSA)
$ssa = ServiceSupportAgreement::factory()->active()->create([
    'student_id' => $student->id,
    'primary_service_id' => $service->id,
    'assigned_therapist_id' => $therapist->id,
    'start_date' => '2026-06-01',
    'end_date' => '2026-12-31',
]);
```

### Session Log Factories

```php
// Basic session log
$log = SessionLog::factory()->create([
    'status' => SessionLogStatus::SUBMITTED,
    'student_id' => $student->id,
    'therapist_id' => $therapist->id,
    'ssa_id' => $ssa->id,
    'session_date' => '2026-06-10',
]);

// With state modifiers
$log = SessionLog::factory()->submitted()->create([...]);
$log = SessionLog::factory()->approved()->create([...]);
$log = SessionLog::factory()->draft()->create([...]);
```

### Invoice & Bill Factories

```php
// Invoice
$invoice = Invoice::factory()->create([
    'school_id' => $school->id,
    'status' => InvoiceStatus::DRAFT,
    'total' => 1200.00,
]);

// Therapist bill
$bill = TherapistBill::factory()->create([
    'therapist_id' => $therapist->id,
    'status' => TherapistBillStatus::SENT,
]);
```

### QaDuskTestCase Helper Methods

```php
// Auto-prefixes with 'qa' for auto-cleanup
$therapist = $this->createQaUser('therapist');     // → qa.xxxxx@test.com
$student = $this->createQaUser('student');         // → qa.xxxxx@test.com
$school = $this->createQaSchool();                 // → QA Xxxxx

// NOT allowed:
$admin = $this->createQaUser('admin');  // ❌ Throws InvalidArgumentException
```

---

## The four roles

Each role has its own dashboard and routes. Use the correct factory shortcut for each:

- **Admin** — logs into `/admin/dashboard`, manages everything
- **Therapist** — needs a profile record and a manager assigned, logs into `/therapist/dashboard`
- **Student** — needs a student profile linked to a school, logs into `/student/dashboard`
- **Finance module** — Finance is **not** a separate user role. There is no `Role::FINANCE` enum value and no `User::factory()->finance()` state. All Finance sheet tests use the **seeded system admin** — fetch it with `User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail()` and log in. Finance tests cover the billing/invoicing feature area of the admin dashboard (`/admin/invoices/*`, `/admin/billing/*`, `/admin/payments/*`, `/admin/ledger/*`, `/admin/expenses/*`). Role isolation tests in the Finance file verify that therapist and student accounts are blocked from those routes.

For therapist and student tests, always create the full chain of related records — school, profile, SSA — before running browser steps, because the pages depend on that data existing.

---

## Waiting for pages to load

The application uses DataTables, AJAX requests, and JavaScript-driven dropdowns. Always wait for elements to appear before interacting with them rather than assuming they are immediately ready. After clicking a button that triggers a server action, wait for the result to appear before asserting.

---

## Confirmation dialogs

The application uses SweetAlert2 for all confirmation popups — never the browser's native alert. When a test needs to dismiss a confirmation without testing its content, intercept it with JavaScript before triggering the action. When the test case specifically checks what the dialog says, let it appear and interact with it normally.

---

## Modals

Custom modals (not SweetAlert) become visible by switching to a flex display class. Always wait for the modal to become visible before interacting with elements inside it, and verify it disappears after confirming.

---

## Database checks

After browser actions, always verify the database reflects the change. Use `assertDatabaseHas` for creates and updates. Use `assertSoftDeleted` for deletions — this project uses soft deletes on all major models, so records are never fully removed.

**Models with SoftDeletes (never fully deleted):**
- `User`, `School`, `TherapistProfile`, `StudentProfile`
- `ServiceSupportAgreement`, `SessionLog`, `Schedule`
- `Invoice`, `TherapistBill`, `Expense`, `LedgerEntry`
- See `app/Models/*.php` and search for `use SoftDeletes;`

---

## Locator Discovery & Strategy

**Critical: Test generation fails if selectors don't match actual HTML. Follow this process:**

### Step 1: Discover Actual Selectors from Running App

**Open the app in browser and inspect elements:**

```bash
# Start the dev server
docker compose up -d
# Navigate to http://localhost:8080

# In browser DevTools:
1. Open Inspector (F12 or right-click → Inspect)
2. Click the element picker (top-left arrow icon)
3. Click the element in the page
4. Look at the HTML: <input id="..." class="..." name="..." data-testid="...">
5. Copy the selector you find
```

### Step 2: Selector Priority (Best to Worst)

Use selectors in this order (most reliable first):

```php
// 1. BEST: data-testid attribute (explicit test hook)
$browser->type('[data-testid="email-input"]', 'test@example.com');

// 2. GOOD: id attribute (must be unique)
$browser->type('#email', 'test@example.com');

// 3: GOOD: name attribute (form controls)
$browser->type('input[name="email"]', 'test@example.com');

// 4: OK: CSS class + tag (may match multiple)
$browser->click('button.btn-primary');

// 5: LAST RESORT: Text content (brittle, changes break tests)
$browser->click('button:contains("Login")');

// 6: AVOID: Position-based (fragile)
$browser->click('div > div > button');  // ❌ Breaks if HTML changes
```

### Step 3: Map Excel Descriptions to Actual Selectors

**Before generating tests, create a selector reference for common elements:**

```
Preconditions column says: "Click Login button"
  ↓
Inspect in browser:  <button id="login-btn" type="submit">Login</button>
  ↓
Excel Step becomes: "Click #login-btn"
  ↓
Generated PHP code: $browser->click('#login-btn');
```

### Step 4: Find Selectors for Your Routes

**Browse to each test route and map elements:**

```bash
# Navigate in browser to each page:
http://localhost:8080/admin/schools        → Inspect "Create School" button
http://localhost:8080/admin/schools/create → Inspect form fields (#full_name, #state, etc.)
http://localhost:8080/login                → Inspect login form (#email, #password)

# Document what you find in a local reference:
Admin Dashboard:
  - "Create School" button → button[data-action="create"], button:contains("Create School")
  - School name input     → input#full_name, input[name="full_name"]
  - State select          → select#state, select[name="state"]

Therapist Dashboard:
  - "Create Schedule"     → button#new-schedule-btn
  - Schedule form         → form#schedule-form, input[name="date"], etc.
```

### Step 5: Common Selector Patterns in This App

**Based on Laravel conventions, these patterns are common:**

```php
// Form inputs — use name attribute
input[name="email"]
input[name="password"]
input[name="full_name"]
select[name="state"]
textarea[name="description"]

// Buttons — use data-action or type
button[type="submit"]           // Submit button
button[data-action="delete"]    // Action button
button:contains("Create")       // Text-based (last resort)

// Tables — use id
table#schoolsTable
tbody tr                        // Each row
td                             // Each cell

// Modals — use class
.modal
.modal-header
.modal-body
button.modal-close

// DataTables — use class
.dataTables_wrapper
.dataTables_processing         // Loading spinner
tbody tr                        // Data rows
input[type="search"]            // Search box
```

### Step 6: Debug Failed Selectors

**When a test fails with "element not found":**

```php
// ❌ Test fails: $browser->click('#login-btn')
// Error: "No element found for selector #login-btn"

// Fix:
// 1. Open browser DevTools Inspector
// 2. Try to find button manually (search for text, look at classes)
// 3. Test selector in console:
//    document.querySelector('button[type="submit"]')  // Returns element or null
// 4. Update the Excel and regenerate test with correct selector

// Use more specific selector:
$browser->click('button[type="submit"]');           // More specific
$browser->click('form#login-form button[type="submit"]');  // Even more specific
```

### Step 7: Use Browser DevTools Console to Validate

**Before putting a selector in Excel, test it works:**

```javascript
// Open browser DevTools → Console
// Paste and run to validate selector exists:

document.querySelector('#login-btn')           // Returns element or null
document.querySelector('input[name="email"]')  // Returns element or null
document.querySelectorAll('tbody tr')          // Returns all rows
document.querySelector('button:contains("Login")')  // Won't work! (CSS limitation)

// If querySelector returns null, selector is wrong — update Excel
// If it returns element, you're good to use it in test
```

### Step 8: Create a Locator Reference Document

**Before running `/qa-generate-tests`, document selectors:**

Create `qa/{role}/selectors.md`:

```markdown
# Admin Page Selectors Reference

## Login Page (/login)
- Email input: `input[name="email"]`
- Password input: `input[name="password"]`
- Login button: `button[type="submit"]`

## Schools Page (/admin/schools)
- Create button: `a[href="/admin/schools/create"]` or `button:contains("Create School")`
- School name input: `input#full_name`
- State select: `select#state`
- Timezone select: `select#timezone`
- Submit button: `button[type="submit"]`
- Search box: `input[type="search"]`

## Session Logs Page (/admin/session-logs)
- Approve button (per row): `button[data-action="approve"]`
- Send Back button: `button[data-action="send-back"]`
- Cancel button: `button[data-action="cancel"]`
```

**Then use these in Excel Step columns:**

| Step 1 | Step 2 | Step 3 |
|--------|--------|--------|
| Visit /admin/schools/create | Type `input#full_name` as "QA School" | Select `select#state` as "CA" |

---

### Common Problems & Solutions

| Problem | Cause | Fix |
|---------|-------|-----|
| Element not found | Selector doesn't match | Inspect in browser, copy exact selector |
| Multiple matches | Selector too generic | Make selector more specific (add tag, class, attribute) |
| Element hidden | Element exists but not visible | Use `->waitFor()` before clicking |
| Form field not found | Wrong input name | Check `<input name="...">` in HTML, not id |
| Button text changes | Using `:contains()` selector | Use data-testid or id instead |
| Selector works locally, fails in CI | Timing issue | Add `->waitFor()` before interaction |

---

## Common Test Patterns & Helpers

### SweetAlert2 Interception

**When you need to dismiss a dialog WITHOUT testing its content:**
```php
$this->browse(function (Browser $browser): void {
    // Intercept SweetAlert before triggering the action
    $browser->script('window.Swal.isVisible = () => false;');
    
    // Now the delete button click won't show the confirmation
    $browser
        ->click('button[data-action="delete"]')
        ->pause(500);
    
    // Verify the delete happened (check DB or page state)
    $this->assertSoftDeleted('schools', ['id' => $school->id]);
});
```

**When testing the dialog content itself:**
```php
$this->browse(function (Browser $browser): void {
    // Let the dialog appear normally
    $browser
        ->click('button[data-action="delete"]')
        ->waitForText('Are you sure?', 10)
        ->assertSee('This action cannot be undone')
        ->click('button:contains("Yes, delete")')  // Confirm button
        ->pause(500);
});
```

**Dialog selector reference:**
```php
// Dismiss/cancel button
$browser->click('.swal2-cancel');

// Confirm button
$browser->click('.swal2-confirm');

// Check if dialog visible
$browser->assertPresent('.swal2-popup');

// Wait for dialog
$browser->waitFor('.swal2-popup', 10);
```

---

### Modal Interactions

**CSS classes for modal state:**
```php
// Modal is OPEN (visible)
$browser
    ->waitFor('[class*="flex"]', 10)  // Modal wrapper has flex class
    ->assertPresent('.modal')         // Modal container exists
    ->assertVisible('.modal');        // Modal is visible

// Modal is CLOSED (hidden)
$browser
    ->click('button[data-action="close"]')
    ->waitFor('.modal:hidden', 10)    // Wait for hidden
    ->assertMissing('.modal')         // Modal removed from DOM
    OR
    ->assertNotVisible('.modal');     // Modal exists but hidden

// Exact selectors:
// Open:   class contains 'flex', class contains 'block', or style visible
// Closed: class contains 'hidden', display:none, or element removed
```

**Verify modal content and close:**
```php
$this->browse(function (Browser $browser): void {
    $browser
        ->click('button[data-action="edit"]')
        ->waitFor('.modal', 10)
        ->assertSee('Edit School')           // Modal content visible
        ->assertPresent('input[name="full_name"]')  // Form field in modal
        ->type('input[name="full_name"]', 'Updated School')
        ->click('button:contains("Save")')   // Confirm button
        ->waitFor('.modal:hidden', 10)       // Wait for modal to close
        ->assertNotVisible('.modal');
});
```

---

### DataTables Waiting

**Wait for DataTable to finish loading:**
```php
$this->browse(function (Browser $browser): void {
    $browser
        ->visit('/admin/schools')
        ->waitFor('table', 10)           // Wait for table to exist
        ->waitFor('tbody tr', 10)        // Wait for rows to load
        ->pause(500);                    // Extra buffer for JS to finish

    // Verify DataTable is ready (no loading spinner)
    $browser->assertMissing('.dataTables_processing');  // No loading
    $browser->assertPresent('tbody tr');               // Rows exist
});
```

**Wait for DataTable filter/search result:**
```php
$this->browse(function (Browser $browser): void {
    $browser
        ->visit('/admin/schools')
        ->waitFor('#schoolsTable', 10)
        ->type('input[type="search"]', 'QA School')
        ->pause(800)  // DataTable filters with debounce
        ->waitFor('tbody tr', 10)
        // Verify filtered result
        ->assertSee('QA School');
});
```

---

### Complete Level 8 Compliant Test Example

```php
<?php

declare(strict_types=1);

use App\Models\School;
use App\Models\User;
use App\Enums\SchoolStatus;
use Laravel\Dusk\Browser;
use Tests\BrowserQA\QaDuskTestCase;

uses(QaDuskTestCase::class);

it('TC-A099 admin can create school and verify in database', function (): void {
    // Arrange — fully typed, factories used
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    $schoolName = 'QA Test School ' . uniqid();
    
    // Act — browser interaction
    $this->browse(function (Browser $browser) use ($admin, $schoolName): void {
        $browser
            ->loginAs($admin)
            ->visit('/admin/schools/create')
            ->waitFor('#full_name', 20)
            ->type('#full_name', $schoolName)
            ->type('#display_name', 'QA Display')
            ->select('#state', 'CA')
            ->select('#timezone', 'America/Los_Angeles')
            ->click('button[type="submit"]')
            ->waitFor('.alert-success, h1', 20)
            ->assertPathContains('/admin/schools');
    });
    
    // Assert — database verification with proper types
    $school = School::where('full_name', $schoolName)->firstOrFail();
    
    $this->assertDatabaseHas('schools', [
        'full_name' => $schoolName,
        'display_name' => 'QA Display',
        'status' => SchoolStatus::ACTIVE->value,
    ]);
    
    expect($school->full_name)->toBe($schoolName);
    expect($school->state)->toBe('CA');
    expect($school->timezone)->toBe('America/Los_Angeles');
});
```

**What makes this Level 8 compliant:**
- ✅ `declare(strict_types=1)` at top
- ✅ All `use` statements imported
- ✅ Function has typed parameters: `function (Browser $browser) use ($admin, $schoolName)`
- ✅ All variables properly typed in context
- ✅ No bare `array` types
- ✅ Database assertions with typed expectations
- ✅ No fully-qualified class names (all imported with `use`)

---

### loginAs() Helper

**Already provided by DuskTestCase (Laravel built-in):**
```php
// Direct login without form submission (faster)
$browser->loginAs($user);

// Alternative: login through form (slower, tests actual login flow)
$browser
    ->visit('/login')
    ->type('input[name="email"]', $user->email)
    ->type('input[name="password"]', 'password')
    ->press('Login');

// Verify authenticated
$browser->assertAuthenticated();

// Verify guest
$browser->assertGuest();
```

**QaDuskTestCase helpers:**
```php
// Auto-prefixed with 'qa' for cleanup
$this->createQaUser('therapist');    // → qa.xxxxx@test.com
$this->createQaSchool();             // → QA Xxxxx

// Standard login after creation
$therapist = $this->createQaUser('therapist');
$browser->loginAs($therapist);
```

---

### E2E Spec File Validation

**Guard: E2E generation should verify spec files exist before creating tests:**
```php
// In the /qa-generate-tests skill, before generating E2E tests:
// Check that qa/e2e/*.md files exist for the workflows:
// - qa/e2e/student-journey.md
// - qa/e2e/therapist-session-to-billing.md
// - qa/e2e/admin-audit-flow.md

// If spec file missing, log warning: "Spec file missing for workflow X"
// Still generate tests but flag them with comment: "// TODO: verify spec"
```

**Existing spec files (reference):**
- `qa/e2e/student-journey.md` — Student registration → SSA → session logging flow
- `qa/e2e/therapist-session-to-billing.md` — Therapist logs session → admin approves → invoice → payment
- `qa/e2e/admin-audit-flow.md` — Admin manages school → therapists → students → audits changes

---

## Role isolation

Every test file must include at least one test that verifies a user of that role cannot access another role's pages. For example, a student test file must prove students are blocked from admin routes. The test should verify the user ends up somewhere other than the page they tried to visit.

**CRITICAL:** Never use `assertGuest()` for role isolation tests. The user is still authenticated; they are just blocked by middleware. Instead:
- Check the response body for `'403'` or `'Forbidden'` strings
- Verify the URL changed (was redirected away)
- Check the page does NOT contain role-specific content

Example pattern (from `QaTherapistAuthBrowserTest.php`):
```php
it('therapist cannot access admin student routes', function (): void {
    $therapist = $this->createQaUser('therapist');
    // ... setup ...
    
    $this->browse(function (Browser $browser) use ($therapist): void {
        $browser->visit('/login')
            ->type('input[name="username"]', $therapist->email)
            ->type('password', 'password123')
            ->press('@login-button')
            ->waitForLocation('/therapist/dashboard')
            ->visit('/admin/students')
            ->pause(600);
        
        $url = $browser->driver->getCurrentURL();
        $bodyText = $browser->driver->findElement(
            \Facebook\WebDriver\WebDriverBy::cssSelector('body')
        )->getText();
        
        expect(
            str_contains($bodyText, '403') ||
            str_contains($bodyText, 'Forbidden') ||
            !str_contains($url, '/admin/students')
        )->toBeTrue();
    });
});
```

---

## Empty states

Include a test for what happens when there is no data to display — for example, a student with no upcoming schedules, or an admin with no pending session logs. The page should load cleanly without errors.

---

## Allure labels (FUTURE)

**STATUS:** Allure reporting is reserved for future implementation. Currently, do NOT add `@allure` annotations to test files.

When implemented, tests will include Feature and Severity labels for organized reporting. Severity levels will be: critical for login and role isolation tests, normal for standard feature tests, minor for edge cases.

## Smoke group tagging (FUTURE)

**STATUS:** Smoke tests are not yet designed. They will be created as needed for each release/push via the `/qa-smoke` skill. 

**Currently:** Do NOT add `->group('smoke')` or `@group smoke` annotations to any test files.

**When smoke tests are designed** (via `/qa-smoke` skill), the tagging approach will be documented in that skill's implementation. See: `.claude/skills/qa-smoke/SKILL.md` (future).

---

## Code quality rules

All test files must pass PHPStan Level 8. This means every closure must have typed parameters, every file must declare strict types, and all models must be imported with use statements at the top rather than written out with full namespace paths inline.

---

## How Excel Maps to Generated PHP Code

**Example Excel row:**

| TC ID | Feature | Condition | Test Name | Preconditions | Step 1 | Step 2 | Step 3 | Expected Result | Dusk Test File |
|-------|---------|-----------|-----------|----------------|--------|--------|--------|-----------------|-----------------|
| TC-A001 | Authentication | Valid | Admin login with correct credentials | System admin exists (develop.ldexpert@gmail.com) | Visit /login | Type email and password | Click Login | Redirects to /admin/dashboard | QaAdminCoreBrowserTest.php |

**Becomes this PHP code:**

```php
it('TC-A001 Admin login with correct credentials', function (): void {
    // ARRANGE — from Preconditions column
    $admin = User::where('email', 'develop.ldexpert@gmail.com')->firstOrFail();
    
    // ACT — from Step columns
    $this->browse(function (Browser $browser) use ($admin): void {
        $browser
            ->visit('/login')                                    // Step 1
            ->type('input[name="email"]', 'develop.ldexpert@gmail.com')  // Step 2
            ->type('input[name="password"]', 'Password123!')     // Step 2
            ->click('button[type="submit"]')                     // Step 3
            
            // ASSERT — from Expected Result column
            ->assertPathIs('/admin/dashboard')                   // Expected Result
            ->assertAuthenticated();                            // Expected Result
    });
});
```

---

## How to Decide What to Write

Read each row in the Excel sheet from top to bottom. For each test case:

1. **Arrange** — Look at the **Preconditions** column to decide what factory records to create
2. **Act** — Follow the **Step** columns in order to write browser interactions
3. **Assert** — Use the **Expected Result** column to decide what to assert

**Translation guide:**
- "Visit /path" → `$browser->visit('/path')`
- "Type email" → `$browser->type('input[name="email"]', value)`
- "Click button" → `$browser->click('selector')`
- "Verify page shows X" → `$browser->assertSee('X')`
- "Check DB has record" → `$this->assertDatabaseHas('table', [...])`
- "Wait for dialog" → `$browser->waitFor('.modal', 20)`

---

## Route Reference

When writing test steps, you'll visit URLs like `/admin/dashboard`, `/therapist/schedules`, etc. Here's where to find available routes:

### By Role

| Role | Route File | Test Paths |
|------|-----------|-----------|
| **Admin** | `app/routes/admin.php` | `/admin/*` (schools, students, therapists, invoices, bills, etc.) |
| **Therapist** | `app/routes/therapist.php` | `/therapist/*` (dashboard, schedules, session-logs, students, etc.) |
| **Student** | `app/routes/student.php` | `/student/*` (dashboard, schedules, progress, etc.) |
| **Auth** | `app/routes/auth.php` | `/login`, `/logout`, `/register` (if enabled) |

### Discovering Available Routes

**List all routes in your test environment:**
```bash
docker compose exec -T app bash -lc 'php artisan route:list'
```

**Filter by route pattern:**
```bash
docker compose exec -T app bash -lc 'php artisan route:list | grep admin'
docker compose exec -T app bash -lc 'php artisan route:list | grep therapist'
docker compose exec -T app bash -lc 'php artisan route:list | grep student'
```

**Output format:**
```
POST  /admin/schools                            Admin\SchoolController@store
GET   /admin/schools/{id}                       Admin\SchoolController@show
PUT   /admin/schools/{id}                       Admin\SchoolController@update
GET   /admin/invoices/{id}                      Admin\InvoiceController@show
POST  /admin/invoices/{id}/payment              Admin\InvoiceController@recordPayment
...
```

### Common Test Routes (Reference)

**Admin:**
```
GET    /admin/dashboard                    → Dashboard page
GET    /admin/schools                      → Schools list
GET    /admin/schools/create               → Create school form
POST   /admin/schools                      → Store school
GET    /admin/schools/{id}                 → Show school
PUT    /admin/schools/{id}                 → Update school
GET    /admin/students                     → Students list
GET    /admin/therapists                   → Therapists list
GET    /admin/invoices/{id}                → Show invoice
POST   /admin/invoices/{id}/payment        → Record payment
GET    /admin/billing/bills                → Therapist bills list
GET    /admin/ledger                       → Ledger entries
```

**Therapist:**
```
GET    /therapist/dashboard                → Dashboard page
GET    /therapist/schedules                → Schedules list
GET    /therapist/schedules/create         → Create schedule form
GET    /therapist/session-logs             → Session logs list
POST   /therapist/session-logs             → Create session log
GET    /therapist/students                 → Assigned students list
```

**Student:**
```
GET    /student/dashboard                  → Dashboard page
GET    /student/schedules                  → Schedule calendar (if built)
GET    /student/progress                   → Progress & goals (if built)
```

**Auth:**
```
GET    /login                              → Login form
POST   /login                              → Process login
GET    /logout                             → Logout
```

### How to Reference Routes in Tests

**Use exact paths:**
```php
$browser->visit('/admin/schools')         // ✅ Correct
$browser->visit('/admin/schools/')        // ⚠️ May redirect
$browser->visit('admin/schools')          // ❌ Missing leading /
```

**For resource routes with IDs:**
```php
$browser->visit('/admin/schools/' . $school->id)
$browser->visit('/admin/invoices/' . $invoice->id . '/payment')
```

---

## Running Generated Tests

### Prerequisites

Before running any tests, ensure:

1. **Docker is running** — All tests run inside Docker containers
   ```bash
   docker compose ps
   # The `app` service must show status `Up`
   # If not running:
   docker compose up -d
   ```

2. **Test database is set up** — `.env.testing` configured with:
   ```
   DB_DATABASE=bird_test  (never `bird`, never production)
   DB_HOST=mysql
   LARAVEL_ENV=testing
   ```

3. **Seeders are up to date** — The system admin seeder must exist:
   ```
   app/database/seeders/AdminUserSeeder.php
   Creates: develop.ldexpert@gmail.com / Password123!
   ```

### Running Tests via Docker

**All tests (all roles):**
```bash
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/ --env=testing'
```

**By role:**
```bash
# Admin tests
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Admin/ --env=testing'

# Therapist tests
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Therapist/ --env=testing'

# Student tests
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Student/ --env=testing'

# Finance tests
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Finance/ --env=testing'

# E2E tests
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/E2E/ --env=testing'
```

**Single test file:**
```bash
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Admin/QaAdminCoreBrowserTest.php --env=testing'
```

**Single test method:**
```bash
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Admin/QaAdminCoreBrowserTest.php --filter=TC-A001 --env=testing'
```

### Understanding Test Results

**Passing test:**
```
✓ TC-A001 Admin login with correct credentials
```

**Failing test:**
```
✗ TC-A001 Admin login with correct credentials
  Expected to see [text], but did not
```

**Screenshots on failure:**
```
Tests automatically save:
├─ tests/BrowserQA/screenshots/    (visual state when test failed)
├─ tests/BrowserQA/console/        (JavaScript console errors)
└─ tests/BrowserQA/source/         (HTML snapshot of page)

Download from CI artifacts or examine locally after test run
```

### Post-Test Checks

After running tests:

1. **Verify test database is clean**
   ```bash
   # All qa* prefixed records should be deleted
   docker compose exec -T app bash -lc 'php artisan tinker'
   > User::where('email', 'like', 'qa%')->count()
   # Should be 0
   ```

2. **Verify seeded admin still exists**
   ```bash
   > User::where('email', 'develop.ldexpert@gmail.com')->first()
   # Should return the system admin record (never deleted)
   ```

3. **Verify ledger integrity** (if payment tests ran)
   ```bash
   docker compose exec -T app bash -lc 'php artisan ledger:verify'
   # Should output: ✓ Ledger is balanced
   ```

### CI / GitHub Actions

Tests run automatically on every push to `main`:

1. **Workflow file:** `.github/workflows/browser-qa.yml`
2. **Database:** Uses `.env.testing` → `bird_test` (safe, isolated)
3. **Artifacts:** Screenshots/console logs saved as `browserqa-dusk-html-report`
4. **On failure:** Download artifact to debug failure screenshots

---

## Test Reporting

After tests complete, **three reports are automatically generated** from the same JUnit XML output. Each serves a different purpose:

### Report Types

| Report | Location | Format | View With | Best For |
|--------|----------|--------|-----------|----------|
| **Markdown Summary** | `qa/reports/admin-YYYY-MM-DD-HHMM.md` | Plain text table | Editor/terminal | Quick pass/fail counts, CI logs |
| **HTML Static** | `qa/reports/admin-YYYY-MM-DD-HHMM.html` | Interactive HTML | Browser | Sharing results, email summaries |
| **Allure Dashboard** | `app/tests/allure-report/index.html` | Interactive web app | Browser | Trends, history, flakiness analysis |

### Running Tests with Reports

All reports are generated automatically when running tests via the reporting script:

```bash
# Generates all three report types
bash scripts/qa/run-qa-report.sh admin tests/BrowserQA/Admin/

# Output:
# ==> Wrote:
#     qa/reports/admin-2026-06-12-1453.md         ← Markdown
#     qa/reports/admin-2026-06-12-1453.html       ← HTML
# ✅ Allure report generated: file://...         ← Allure dashboard
```

### Viewing Reports

#### 1. Markdown Summary (Terminal/Editor)

**Fastest** — read in terminal or editor:

```bash
cat qa/reports/admin-YYYY-MM-DD-HHMM.md

# Output example:
# | Status | Test | Time (s) | Details |
# |--------|------|----------|---------|
# | ✅ PASS | TC-A001 Admin login... | 2.26 | |
# | ❌ FAIL | TC-A002 Admin dashboard... | 0.85 | Timeout waiting for selector |
```

#### 2. HTML Report (Browser)

**Static snapshot** — view in browser:

```bash
# macOS
open qa/reports/admin-YYYY-MM-DD-HHMM.html

# Linux
xdg-open qa/reports/admin-YYYY-MM-DD-HHMM.html

# Windows
start qa/reports/admin-YYYY-MM-DD-HHMM.html

# Or just find the latest report:
ls -lht qa/reports/*.html | head -1
```

Features:
- ✅ Clickable test results
- ✅ Searchable and filterable
- ✅ Downloadable (print/save as PDF)
- ✅ Shareable across team

#### 3. Allure Dashboard (Interactive Analytics)

**Most detailed** — interactive trends and history:

```bash
# View after running tests:
open file://$(pwd)/app/tests/allure-report/index.html

# Or use the shortcut:
make qa-allure-report
```

**Allure Dashboard Features:**

- **Overview** — Live pass/fail ratio, duration metrics, trend chart
- **Suites** — Drill down by test class and feature area
- **Tests** — Search, filter by status, view individual test steps
- **Graphs** — Test duration distribution, flakiness heatmap
- **Timeline** — Chronological test execution view
- **History** — Previous runs with duration trends, retry analysis

**History & Trends:**
- Allure **accumulates results** across multiple test runs
- Each new run adds to the history (results stored in `app/tests/allure-results/`)
- Dashboard auto-generates trend charts from history
- Identify flaky tests that pass sometimes but fail other times

### Report Examples

#### Markdown (Quick Reference)

```markdown
# BrowserQA Dusk Report
Generated 2026-06-12 10:52:44 UTC

## Summary
- Total: 100 | Passed: 35 | Failed: 65 | Errors: 0 | Skipped: 0
- Duration: 1088.51s

| Status | Test | Duration (s) | Details |
|--------|------|--------------|---------|
| ✅ | TC-A001 Admin login... | 2.26 | |
| ❌ | TC-A020 App logs entry... | 3.14 | Timeout: selector not found |
```

#### HTML (Browser View)

```
┌─────────────────────────────────────────────┐
│ DUSK BROWSER TEST REPORT                    │
├─────────────────────────────────────────────┤
│ ✅ 35 Passed  ❌ 65 Failed                 │
│ ⏱️  18 minutes total duration               │
│                                             │
│ [Filter by status] [Search tests] [Export] │
│                                             │
│ 📋 Results Table:                          │
│   ✅ TC-A001 Admin login...        2.26s  │
│   ❌ TC-A020 App logs entry...     3.14s  │
│      → Selector timeout error      (view) │
│   ...                                      │
└─────────────────────────────────────────────┘
```

#### Allure Dashboard (Analytics)

```
┌──────────────────────────────────────────┐
│ ALLURE TEST REPORT DASHBOARD             │
├──────────────────────────────────────────┤
│ Overview  │ Suites  │ Tests  │ Graphs    │
├──────────────────────────────────────────┤
│                                          │
│  Pass Rate:  35%  ████░░░░░░            │
│  Duration:   18 min (avg: 10.9s)        │
│                                          │
│  Trend Chart (last 5 runs):             │
│    ████  ███░  ████░  █████  ███░░     │
│    42%   30%   35%    50%    28%       │
│                                          │
│  Flaky Tests (≥1 failure, ≥1 pass):   │
│    • TC-A050 Admin toggle settings     │
│      History: PASS → FAIL → PASS       │
│                                          │
│  Slowest Tests:                         │
│    • TC-A143 Admin upload CSV: 45s    │
│    • TC-A112 Admin edit SSA: 35s      │
│                                          │
└──────────────────────────────────────────┘
```

### Workflow: Run Tests → Review → Fix → Re-Run

```
1. Run tests with reporting:
   bash scripts/qa/run-qa-report.sh admin tests/BrowserQA/Admin/

2. Review quick summary:
   cat qa/reports/admin-YYYY-MM-DD-HHMM.md

3. View failures in detail:
   open qa/reports/admin-YYYY-MM-DD-HHMM.html
   ↑ click on failed tests to see error messages

4. Deep dive into trends (if multiple runs):
   open file://$(pwd)/app/tests/allure-report/index.html
   ↑ identify which tests are consistently flaky

5. Fix selectors / assertions in test code

6. Re-run tests:
   bash scripts/qa/run-qa-report.sh admin tests/BrowserQA/Admin/

7. Allure dashboard auto-updates with new results
```

### Clearing Reports and History

**Reset all reports and start fresh:**

```bash
# Remove all generated reports
rm -rf qa/reports/*.md qa/reports/*.html
rm -rf app/tests/allure-results
rm -rf app/tests/allure-report

# Next test run will create new reports
bash scripts/qa/run-qa-report.sh admin tests/BrowserQA/Admin/
```

**Keep Markdown/HTML reports but reset Allure history:**

```bash
# Just remove Allure data (keep Markdown/HTML)
rm -rf app/tests/allure-results app/tests/allure-report

# Next test will start fresh Allure trends
```

### Troubleshooting

**"No Allure report generated"**

Check if Allure CLI is installed:
```bash
cd app && npx allure --version
```

If missing:
```bash
npm install --save-dev allure-commandline
# or
make allure-install
```

**"Allure dashboard shows 'No data'"**

Run tests again:
```bash
bash scripts/qa/run-qa-report.sh admin tests/BrowserQA/Admin/
```

The dashboard needs Allure JSON result files in `app/tests/allure-results/`.

### Local Debugging Tips

**Run with verbose output:**
```bash
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Admin/ --env=testing -v'
```

**Keep test database state for inspection:**
```bash
# After test fails, manually inspect:
docker compose exec -T app bash -lc 'php artisan tinker'
> SessionLog::latest()->first()  # Check what test created
```

**Re-run specific failed test:**
```bash
docker compose exec -T app bash -lc 'php artisan dusk tests/BrowserQA/Admin/QaAdminCoreBrowserTest.php --filter=TC-A001 --env=testing'
```
