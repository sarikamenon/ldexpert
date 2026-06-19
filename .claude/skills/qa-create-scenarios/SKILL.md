---
name: qa-create-scenarios
description: Generate complete, enumerated QA test cases for LD Expert Bird with 100% menu and feature coverage. Systematically reads wiki/ PRDs, discovers every menu item and feature, generates test cases covering all condition types (Valid/Invalid/Edge), and outputs: test-plan.md (feature reference), test-data.md (factory setup), and Excel rows (TC-A001+) with steps, expected results, and preconditions. Enforces: every feature tested, every menu item covered, no gaps. Runs incrementally by default — on re-runs it diffs against existing Excel rows (Pass 0) and generates ONLY features new since the last run, never re-emitting or duplicating covered features. Use when planning test coverage for admin, therapist, student, finance, or e2e roles. Triggers on "create test plan", "plan QA for", "write test scenarios", "generate test cases for".
---

# QA Test Case Generator — Role-Based Comprehensive Coverage

> **Purpose:** Generate complete, enumerated QA test cases for LD Expert Bird with 100% menu and feature coverage across all user roles.

**Important:** Read wiki PRDs only. Never modify `app/wiki/` files.

**Before you start:** Use the `/ld-expert-domain` skill to understand role routes, business logic, status lifecycles, and factory chain order. This skill is your reference for domain rules while writing test scenarios.

---

## Overview

This skill generates three outputs **per role**:
1. **test-plan.md** — Human-readable feature reference with menu structure and scenarios
2. **test-data.md** — PHP factory setup for all test preconditions
3. **Excel rows in app/qa/LD-Expert-QA.xlsx** — Enumerated test cases by role (TC-A001+, TC-T001+, TC-S001+, TC-F001+)

### Key Features
- **Systematic menu-based discovery** ensures 100% coverage of every feature  
- **Role-wise organization** — separate Excel sheet per role (Admin, Therapist, Student, Finance, E2E)  
- **Condition type enforcement** — every feature tested as Valid/Invalid/Edge  
- **Precondition mapping** — each test case links to factory setup in test-data.md  

---

## How to Use This Skill

See **`app/qa/QUICK-START.md`** for exact commands, workflow steps, and examples.

---

## Generation Modes — Incremental by Default (NEVER Re-emit Existing Features)

**CRITICAL:** This skill must NOT regenerate test cases for features that are already in `app/qa/LD-Expert-QA.xlsx`. Re-running a role must only add cases for features that are new since the last run. Three modes:

| Mode | When it applies | What it generates |
|---|---|---|
| **Full** | The role sheet is empty (first ever run for that role) | All discovered features |
| **Incremental** (DEFAULT) | The role sheet already has rows | ONLY features present in the wiki but **not yet** in the sheet's `Feature` column |
| **Scoped** | The user names a feature (e.g. "generate cases for School Calendar Events only") | ONLY that named feature, even if other new features exist |

**How the skill decides:** Before generating anything, run **Pass 0 (Existing Coverage Diff)** — read the role sheet, collect every distinct value in the `Feature` column into a `coveredFeatures` set, then generate cases **only** for discovered features whose name is NOT in that set (or, in Scoped mode, only the named feature).

**Hard rules:**
- The `Feature` column value MUST match the wiki menu item / feature heading **exactly and stably** across runs — this is the key the diff matches on. Never rename a feature between runs.
- Continue TC ID numbering from the **last existing ID** in the sheet (e.g. if last is `TC-A087`, new rows start at `TC-A088`).
- If Pass 0 finds **zero** new features, STOP and report "No new features to generate — all wiki features already covered." Do not append anything.

---

## Feature Coverage Validation Checklist

**Purpose:** Before generating test cases, verify that Pass 1 will discover ALL features. After generating, use this checklist to validate no features are missing.

### Admin Features (from app/wiki/admin/*)

Verify your test-plan.md includes:
- [ ] Schools (Create, edit, deactivate, manage service rates)
- [ ] Users (Create therapists, students; manage roles; deactivate)
- [ ] Therapists (View, manage profiles, assign students)
- [ ] Students (View, manage profiles, assign to schools)
- [ ] Contracts & SSAs (Create, approve, track compliance)
- [ ] Services (Define service types, manage rates)
- [ ] Session Logs (View, approve, send back, audit)
- [ ] Invoices (Generate, send, preview PDF, record payments)
- [ ] Bills (Create, track, manage disputes)
- [ ] Imports (Batch student import)
- [ ] Reports (Generate, export, view dashboards)
- [ ] Schedule Calendar (View, manage scheduling)
- [ ] Settings (Configure timezones, templates, rules)
- [ ] Notifications (Manage email, SMS rules)
- [ ] Analytics (View insights, trends)
- [ ] Leads (Manage lead pipeline)
- [ ] Student Documents (Upload, manage files)

### Therapist Features (from app/wiki/therapist/*)

Verify your test-plan.md includes:
- [ ] Schedule Management (Create, edit, manage recurrence, view calendar)
- [ ] Student Management (View assigned, track progress, manage documents)
- [ ] Session Logging (Create logs, assign service codes, update status)
- [ ] Sub Coverage (Request, manage substitutes, approve handoffs)
- [ ] Billing & Payments (View invoices, track payments, comment)
- [ ] Session History (View past, search, filter, access notes)
- [ ] Student Comments (Create, view, manage feedback)

### Student Features (from app/wiki/student/*)

Verify your test-plan.md includes:
- [ ] Dashboard (View upcoming, progress summary, announcements)
- [ ] Schedule Calendar (View therapy schedule, timezone display)
- [ ] Progress & Goals (View goals, track milestones, recommendations)
- [ ] Session History (View past, access notes, filter by date)
- [ ] Account Settings (Update profile, manage notifications, timezone)

### Finance Features (from app/wiki/finance/*)

Verify your test-plan.md includes:
- [ ] Invoice Management (Generate, send, preview, record payments)
- [ ] Bill Processing (Create, track approval, manage line items)
- [ ] Payment Recording (Record school payments, refunds, overpayments)
- [ ] Ledger & Audit (View entries, verify balances, transaction history)
- [ ] Expense Management (Record, categorize, report)
- [ ] Billing Automation (Run automated billing, handle failures)
- [ ] Pay Stub Report (Generate, review therapist pay)

### E2E Workflows (cross-role processes)

Verify your test-plan.md includes:
- [ ] School Onboarding (Admin creates school → Therapist joins → Students enroll)
- [ ] Contract Lifecycle (Admin creates contract → Therapist accepts → Student benefits)
- [ ] Session to Billing (Therapist logs → Admin approves → Finance invoices → payment recorded)
- [ ] Sub Coverage Request (Therapist requests → Sub accepts → Finance tracks)

---

## Input — Prerequisites & Wiki PRD Locations

### Prerequisite: Domain Knowledge

Before starting, run `/ld-expert-domain` skill to:
- Understand role routes and access control
- Learn status lifecycles and business rules
- Review factory chain order for test data setup
- Check timezone rules and soft delete behavior

**Purpose:** Single source of truth for domain rules before writing test scenarios

---

### Wiki Files to Read (Never Modify)

| Role / Area | Read These Wiki Files |
|---|---|
| `student` | `app/wiki/student/portal.md`, `app/wiki/student/menu.md` |
| `therapist` | `app/wiki/therapist/workspace.md`, `app/wiki/therapist/session-logs.md`, `app/wiki/therapist/student-comments.md`, `app/wiki/therapist/sub-coverage.md`, `app/wiki/therapist/menu.md` |
| `admin` | `app/wiki/admin/*.md` (all files in admin/) |
| `finance` | `app/wiki/finance/*.md` (all files in finance/) |
| `e2e` | All wiki files relevant to the workflow requested |

### Role Selection

Choose ONE role per run:

```
ADMIN    → Generate test cases for admin menu items
THERAPIST → Generate test cases for therapist workflows  
STUDENT  → Generate test cases for student features
FINANCE  → Generate test cases for billing/payment workflows
E2E      → Generate test cases for cross-role workflows
```

### Coverage Reference Table (Discovered Dynamically)

| Role | Expected Menu Items | Expected Test Cases | Last Updated | Typical Features |
|------|---|---|---|---|
| **Admin** | Multiple | Extensive | Last Updated | Schools, Therapists, Students, SSAs, Services, Session Logs, Contracts, Invoices, Bills, Imports, Reports, and more |
| **Therapist** | Multiple | Extensive | Last Updated | Schedule, Sessions, Students, Bills, Sub Coverage, Comments, and more |
| **Student** | Multiple | Comprehensive | Last Updated | Dashboard, Schedule, Progress & Goals, Session History, and more |
| **Finance** | Multiple | Comprehensive | Last Updated | Invoicing, Billing, Payments, Ledger, Expenses, and more |
| **E2E** | Multiple | Comprehensive | Last Updated | Cross-role workflows and integrations |

> **Note:** These are reference expectations based on current wiki structure. The skill **discovers and verifies 100% of actual menu items found**. If items are added/removed, the skill automatically adapts and reports actual count vs. expected.

---

## Logical Execution Order (Based on Business Flow Dependencies)

**Generate test cases in this sequence — each role builds on data from the previous:**

| Order | Role | Depends On | Why This Order | TC Prefix |
|-------|------|-----------|---|---|
| **1** | **Admin** | None | Foundation: creates all entities (schools, users, contracts, services) needed by other roles | TC-A |
| **2** | **Therapist** | Admin | Needs admin-created accounts, schools, students. Generates session data (billing source) | TC-T |
| **3** | **Student** | Admin + Therapist | Reads data created by therapist. Cannot test without active schedules/sessions | TC-S |
| **4** | **Finance** | Admin + Therapist | Cannot invoice without submitted sessions from therapist. Depends on both setup and data | TC-F |
| **5** | **E2E** | All previous | Tests complete workflows across all roles. Requires all preconditions from steps 1–4 | TC-E |

---

---

## Excel Organization — Test Cases By Role

**Master file: `app/qa/LD-Expert-QA.xlsx`** — Contains separate sheets for each role with complete test case enumeration (discovered via Pass 1).

| Sheet | TC ID Prefix | Distribution | Row Format |
|-------|--------------|--------------|-----------|
| **Admin** | TC-A (TC-A001, TC-A002, ...) | ~30% Valid, ~50% Invalid, ~20% Edge | TC ID \| Feature \| Condition \| Test Name \| Priority \| Preconditions \| Steps \| Expected Result \| Status |
| **Therapist** | TC-T (TC-T001, TC-T002, ...) | ~30% Valid, ~50% Invalid, ~20% Edge | Same format |
| **Student** | TC-S (TC-S001, TC-S002, ...) | ~30% Valid, ~50% Invalid, ~20% Edge | Same format |
| **Finance** | TC-F (TC-F001, TC-F002, ...) | ~25% Valid, ~55% Invalid, ~20% Edge | Same format |
| **E2E** | TC-E (TC-E001, TC-E002, ...) | ~40% Valid, ~40% Invalid, ~20% Edge | Same format |

---

## Output Files — Three Per Role

### E2E Exception

**For the `e2e` role ONLY:**
- **DO NOT** write `app/qa/e2e/test-plan.md`
- **DO** write workflow spec files: `app/qa/e2e/{workflow-name}.md` (e.g., `student-journey.md`, `therapist-session-to-billing.md`)
  - Each file covers one end-to-end user journey with a step table, pass criteria, and factory setup comment
- **DO** write `app/qa/e2e/test-data.md` (factories for all workflows)
- **DO** append Excel rows to E2E sheet (TC-E001, TC-E002, etc.)
- See existing files in `app/qa/e2e/` for format reference

---

### File 1: app/qa/{role}/test-plan.md — Feature Reference

**Purpose:** Human-readable feature inventory by menu item with scenarios

```markdown
# {Role} Test Plan

> **Source:** app/wiki/{role}/*.md  
> **Generated:** YYYY-MM-DD  
> **Coverage:** N menu items, M test cases, X% of requirements  
> **Condition Distribution:** Y% Valid | Z% Invalid | W% Edge

## Scope
Brief description of role capabilities and testing scope.

## Menu Structure & Feature Areas

### {Menu Item Name}

| Feature | Priority | Wiki File | Test Cases | Coverage |
|---------|----------|-----------|-----------|----------|
| Feature Name | P1 | wiki/admin/file.md | TC-A001, TC-A002, TC-A003 | 3/3 |

## Test Scenarios

### {Feature Area Name}

#### SC-001: {Scenario Title}
- **Priority:** P1
- **Given:** System state before action
- **When:** User action
- **Then:** Expected outcome
- **Edge cases:** Boundary conditions, invalid inputs, empty states
- **Related Test Cases:** TC-A001, TC-A002, TC-A003

#### SC-002: {Scenario Title}
...
```

---

### File 2: app/qa/{role}/test-data.md — Factory Setup Reference

**Purpose:** PHP factory code for preconditions (run in Laravel Tinker)

```markdown
# {Role} Test Data

## TD-001: {Feature Name} — {TC-A001, TC-A002, TC-A003}

### Preconditions
What must exist in database before test runs.  
Example: School (ACTIVE), 3 approved SessionLogs (Aug 2026)

### Factory Setup
```php
// Run inside Docker:
// docker compose exec -T app bash -lc 'php artisan tinker'

$school = School::factory()->create([
    'status' => SchoolStatus::ACTIVE,
    'name' => 'Greenfield Academy',
]);

SessionLog::factory(3)
    ->approved()
    ->dateRange('2026-08-01', '2026-08-31')
    ->for($school)
    ->create();
```

### Data Values
| Field | Value | Reason |
|-------|-------|--------|
| status | ACTIVE | Only active schools appear in lists |
| name | Greenfield Academy | Distinguishable in test output |
| session_date | 2026-08-15 | Within billing period Aug 1-31 |

### Reset Between Tests
- Run `php artisan migrate:fresh --seed` for full reset
- Or use `RefreshDatabase` trait in Dusk test

## TD-002: {Feature Name} — {TC-A004, TC-A005}

[Continue for all precondition patterns...]
```

---

### File 3: app/qa/LD-Expert-QA.xlsx — Master Test Case Spreadsheet

**Append rows to the correct role sheet (Admin, Therapist, Student, Finance, or E2E) with ALL columns:**

#### Example Rows Format

| TC ID | Feature | Condition | Test Name | Priority | Preconditions | Step 1 | Step 2 | Step 3 | Step 4 | Step 5 | Expected Result | Test Data Ref | Status | Actual Result | Notes |
|-------|---------|-----------|-----------|----------|---------------|--------|--------|--------|--------|--------|-----------------|---------------|--------|---------------|-------|
| TC-A001 | Schools | Valid | Create school with all required fields | P1 | None (new school) | Navigate to /admin/schools/create | Enter name: "Greenfield Academy" | Set state: NY, timezone: America/New_York | Click "Create School" | | School created, appears in list, status = ACTIVE | TD-A001 | Not Run | | |
| TC-A002 | Schools | Invalid | Create school with missing name | P1 | Schools form open | Navigate to /admin/schools/create | Leave name empty | Fill state: NY, timezone set | Click "Create School" | | Validation error: "Name is required" | | Not Run | | |
| TC-A003 | Schools | Edge | Create with max-length name (255 chars) | P2 | Schools form open | Navigate to /admin/schools/create | Enter 255-char name | Fill other fields | Click "Create School" | | School created, displays without truncation | TD-A001 | Not Run | | Boundary test |

#### Column Specifications

| Column | Purpose | Example | Rules |
|--------|---------|---------|-------|
| **TC ID** | Test case identifier | TC-A001, TC-T050, TC-F035 | Sequential per role (A→Admin, T→Therapist, S→Student, F→Finance, E→E2E) |
| **Feature** | Feature/menu area name | Schools, Invoices, Schedule | From wiki heading |
| **Condition** | Test type | Valid / Invalid / Edge | Only these 3 values allowed |
| **Test Name** | Brief descriptive title | "Create school with all fields" | 20-50 chars, action-focused |
| **Priority** | Importance | P1 / P2 / P3 | P1 = critical, P2 = important, P3 = nice-to-have |
| **Preconditions** | System state before test | "School (ACTIVE), 3 logs" | Entity list + state + context (or "None") |
| **Steps 1-5** | Sequential actions | "Navigate to /admin/...", "Enter name", etc. | One action per column; leave blank if <5 steps |
| **Expected Result** | Specific outcome | "School created, appears in list" | Measurable, testable |
| **Test Data Ref** | Factory reference | TD-A001, TD-A002 | Links to test-data.md (blank if no setup) |
| **Status** | Test execution state | Not Run / Pass / Fail | Always "Not Run" for new rows |
| **Actual Result** | What really happened | (filled by QA during execution) | Leave blank initially |
| **Notes** | Optional context | "Boundary condition test" | Edge case details, verification hints |

---

## Test Case Types: Functional vs. Technical

**Every test case must be classified by its focus — functional or technical.**

### Functional Test Cases (User-Focused)

**Purpose:** Verify user workflows and feature behavior from the user's perspective.  
**Language:** Non-technical, user-friendly (e.g., "Create school", "Delete removes from list", "Shows error message")  
**Examples:**
- Valid: "Create school with all required fields"
- Invalid: "Create school with missing name shows validation error"
- Edge: "School name field accepts 255 characters without truncation"

**When to use:** Forms, buttons, workflows (create/edit/delete), business processes, user-visible errors/confirmations, data filtering.  
**Audience:** QA engineers, business analysts, product managers.

---

### Technical Test Cases (System-Focused)

**Purpose:** Verify system behavior, performance, security, and error codes.  
**Language:** Technical (e.g., "HTTP 201", "database transaction", "AES-256 encryption")  
**Examples:**
- Valid: "POST /api/schools returns HTTP 201 when all required fields provided"
- Invalid: "POST /api/schools returns HTTP 422 when name field is null"
- Edge: "GET /api/schools endpoint responds within 200ms for 1000 records"

**When to use:** API endpoints, database constraints, performance, security (encryption, auth), error codes, integrations.  
**Audience:** QA engineers, developers, DevOps engineers, security team.

---

## Test Case Naming Guidelines

### Rule 1: Start with the Action
```
GOOD: "Create school with all required fields"
BAD:  "School creation"
BAD:  "Admin creates school"
```

### Rule 2: Include the Condition (for Invalid/Edge)
```
GOOD: "Create school with missing name shows validation error" (Invalid)
BAD:  "School creation fails"

GOOD: "Accept 255-character school name without truncation" (Edge)
BAD:  "Max length test"
```

### Rule 3: Specify the Expected Outcome
```
GOOD: "Login with wrong password shows 'Invalid credentials' error" (Invalid)
BAD:  "Wrong password test"

GOOD: "Generate invoice creates PDF file with correct totals" (Valid)
BAD:  "Invoice generation"
```

### Rule 4: Use User Language for Functional, Technical Language for Technical
```
FUNCTIONAL: "User sees validation error when email field is empty"
TECHNICAL:  "POST /api/register returns HTTP 422 when email is null"

FUNCTIONAL: "Delete session removes it from schedule view"
TECHNICAL:  "DELETE /api/sessions/{id} cascades and removes related audit records"
```

### Rule 5: Be Specific, Not Generic
```
GOOD: "Contract start date cannot be before today's date"
BAD:  "Contract date validation"

GOOD: "Invoice shows line item amounts in school's local currency"
BAD:  "Currency handling"
```

---

### LD Expert Bird Test Case Focus

**For LD Expert Bird, most test cases will be FUNCTIONAL** because:
- Primary focus: user workflows (create schools, log sessions, generate invoices, view schedules)
- User interface: forms, buttons, lists, validations
- Business logic: state transitions, role permissions, feature availability

**Include TECHNICAL test cases for:**
- API integration points (if applicable)
- Database constraints and relationships
- Security-critical features (authentication, role-based access)
- Performance-critical features (large data lists, file uploads)
- Error handling that affects backend systems

---

## Output Format: Excel Rows (app/qa/LD-Expert-QA.xlsx)

**Use the Excel MCP to write directly.** Open `app/qa/LD-Expert-QA.xlsx`, go to the correct role sheet, read the last existing TC ID to avoid duplicates, then append one row per test case.

Note: First time running this skill? See `app/qa/SETUP.md` for Excel MCP installation and configuration.

### TC ID Prefixes By Sheet

| Sheet | Prefix | Format | Notes |
|-------|--------|--------|-------|
| Admin | TC-A | TC-A001, TC-A002, TC-A003, ... | Sequential numbering starting from TC-A001 |
| Therapist | TC-T | TC-T001, TC-T002, TC-T003, ... | Sequential numbering starting from TC-T001 |
| Student | TC-S | TC-S001, TC-S002, TC-S003, ... | Sequential numbering starting from TC-S001 |
| Finance | TC-F | TC-F001, TC-F002, TC-F003, ... | Sequential numbering starting from TC-F001 |
| E2E | TC-E | TC-E001, TC-E002, TC-E003, ... | Sequential numbering starting from TC-E001 |

### Columns to Fill Per Row

| Column | Value |
|--------|-------|
| TC ID | Next sequential ID (e.g., TC-A031 if last was TC-A030) |
| Feature | Feature area name matching test-plan.md section |
| Condition | `Valid` / `Invalid` / `Edge` (mandatory) |
| Test Name | Short descriptive title, 20-50 chars, action-focused |
| Priority | `P1` (critical) / `P2` (important) / `P3` (nice-to-have) |
| Preconditions | What DB state must exist (e.g., "School (ACTIVE), 3 approved SessionLogs") or "None" |
| Step 1–5 | One action per column; leave blank if fewer than 5 steps |
| Expected Result | Specific, measurable outcome |
| Test Data Ref | Reference to test-data.md entries (e.g., TD-A001) or blank |
| Status | `Not Run` (always for new rows) |
| Actual Result | Leave blank (filled by QA during execution) |
| Notes | Optional context, edge case details, or hints |
| Dusk Test File | Leave blank (filled by qa-generate-tests after automation) |

### Excel Rules (Essential)

1. **Append, never overwrite** — Find last TC ID, add new rows below
2. **Dedupe by Feature, not just TC ID** — Before generating, read the `Feature` column (Pass 0). Skip any feature already present. Never add a second set of rows for a feature that already has coverage.
3. **One row per test case** — All columns filled per row
4. **Condition is mandatory** — Every row MUST have Valid / Invalid / Edge
5. **Every feature needs all 3 types** — No missing condition types
6. **Incremental by default** — Only generate for features new since the last run (see Generation Modes + Pass 0)

---

## Execution Algorithm — Pass 0 + 5 Passes

### Pass 0: Existing Coverage Diff (Incremental Mode Gate)

**Goal:** Determine which features are NEW so the skill only generates those. Run this FIRST, before discovery output is finalized.

```
Step 1: Open app/qa/LD-Expert-QA.xlsx → go to the role's sheet (Admin/Therapist/Student/Finance/E2E)

Step 2: If the sheet is empty (header row only):
  → MODE = Full. Skip to Pass 1, generate everything. Note: "First run — full generation."

Step 3: If the sheet has data rows:
  → Read the entire `Feature` column.
  → Build coveredFeatures = { distinct, trimmed Feature values }.
  → Read the last `TC ID` → set nextId = lastId + 1 (e.g. TC-A087 → TC-A088).
  → MODE = Incremental (unless the user named a specific feature → MODE = Scoped).

Step 4: After Pass 1 discovers all wiki features, compute the work list:
  - Incremental: newFeatures = discoveredFeatures − coveredFeatures
  - Scoped:      newFeatures = { the feature the user named }  (must be a real discovered feature)
  - Full:        newFeatures = discoveredFeatures

Step 5: If newFeatures is empty:
  → STOP. Report: "No new features to generate — all N wiki features already covered (last ID: TC-Xnnn)."
  → Do NOT write any rows or modify test-plan.md/test-data.md.

Step 6: Passes 2–5 operate ONLY on newFeatures. Existing rows are never touched, re-read for content, or duplicated.
```

**Matching note:** the diff keys on the `Feature` column string. Discovery (Pass 1) must name features identically to how they were named in prior runs (same menu item / wiki heading). If a feature legitimately needs a new name, treat it as new and the operator must manually retire the old rows.

### Pass 1: Menu-Based Feature Discovery

**Goal:** Identify EVERY feature systematically

```
Step 1: Read menu file
  - Admin: app/wiki/admin/menu.md
  - Therapist: app/wiki/therapist/menu.md
  - Student: app/wiki/student/menu.md
  - Finance: List all files in app/wiki/finance/

Step 2: Extract menu items
  Example (Admin): Dashboard, Schools, Therapists, Students, SSAs, Services, Session Logs, Contracts, Invoices, Bills, Imports, Reports, Schedule Calendar, Settings, Notifications, Analytics, Leads, Student Documents (19 total)

Step 3: Map wiki files to menu items
  Verify 100% coverage — all menu items have wiki documentation

Step 4: Verify coverage
  Count: N/N menu items mapped
```

### Pass 2: Feature-by-Feature Test Case Enumeration

**Goal:** Generate Valid + Invalid + Edge for EVERY feature

For each feature identified in Pass 1:

```
Feature: "Invoice Generation"

  TEST CASE 1: Valid (Happy Path)
    TC ID: TC-F001
    Test Name: "Generate invoice with approved session logs"
    Preconditions: "School (ACTIVE), 3 approved SessionLogs (Aug 2026)"
    Steps: Navigate, Select school, Set period, Click Generate
    Expected Result: "Invoice created (DRAFT), 3 line items, total correct"
    Condition: Valid

  TEST CASE 2: Invalid (Error Handling)
    TC ID: TC-F002
    Test Name: "Generate invoice with no approved logs"
    Preconditions: "School, 0 approved logs"
    Steps: [navigation, no approved found]
    Expected Result: "Warning shown OR $0.00 invoice"
    Condition: Invalid

  TEST CASE 3: Edge (Boundary Condition)
    TC ID: TC-F003
    Test Name: "Generate invoice with single $0.00 line item"
    Preconditions: "School, 1 non-billable session"
    Steps: [navigation, generate]
    Expected Result: "Invoice created cleanly, no errors"
    Condition: Edge
```

**VALIDATION:** Every feature has Valid + Invalid + Edge cases

### Pass 3: Test Data Precondition Mapping

**Goal:** Link every precondition to test-data.md entry

For each test case's precondition, create factory code entry:
```
TEST CASE: "Generate invoice with 3 approved SessionLogs"
Precondition: "School (ACTIVE), 3 approved SessionLogs (Aug 2026)"

→ Create TD-F001: School (Active)
→ Create TD-F002: SessionLog (Approved, Aug 2026)
→ Excel row includes: "Test Data Reference: TD-F001, TD-F002"
→ Add to test-data.md with PHP factory setup
```

### Pass 4: Excel Row Generation

**Goal:** Append test case rows to app/qa/LD-Expert-QA.xlsx — **only for `newFeatures` from Pass 0.**

Using Excel MCP:
1. Open app/qa/LD-Expert-QA.xlsx
2. Navigate to sheet: {role}
3. Find last row with data; start appending at `nextId` from Pass 0 (continue the sequence — never restart at 001)
4. Append rows ONLY for features in the `newFeatures` work list. Never write a row whose `Feature` already exists in `coveredFeatures`.
5. For each test case, write ONE row with ALL columns:
   - TC ID, Feature, Condition, Test Name, Priority, Preconditions, Step 1-5, Expected Result, Test Data Ref, Status, Actual Result, Notes, Dusk Test File

**Format (EXACT, no changes):**
- Condition: MUST be `Valid` / `Invalid` / `Edge`
- Status: Always `Not Run`
- TC ID: Sequential (TC-A001, TC-A002, ..., TC-F001, etc.)

### Pass 5: Post-Generation Validation

**Goal:** Detect gaps before declaring success

**Incremental-mode rules for the markdown files:**
- `test-plan.md` and `test-data.md` are **appended**, not rewritten. Add a new section for each feature in `newFeatures`; leave existing sections untouched.
- In Incremental/Scoped mode, the validation checklist below applies **only to the newly added features** — do not re-audit or re-touch already-covered features.

**MANDATORY VALIDATION CHECKLIST:**

- test-plan.md covers every feature area in wiki with priority assigned  
- Every feature area has at least one positive, one negative, and one edge case in Excel  
- Every route in wiki has at least one test scenario  
- Every test case row in Excel has a Condition Type (Valid / Invalid / Edge)  
- test-data.md has factory entry for every precondition pattern used in Excel  
- Role isolation tested — each role blocked from other roles' routes  
- Empty state tested — what shows when no data exists  
- Error/validation state tested — what shows when input is wrong  
- Soft delete behaviour tested — deleted records don't appear in lists  
- Status flow tested — actions blocked when record is in wrong status

**FINAL REPORT:**
- Mode: Full / Incremental / Scoped
- Features already covered (skipped): N
- New features generated this run: N (list them)
- Test cases added this run: N (TC ID range, e.g. TC-A088–TC-A104)
- Condition coverage (new rows): Valid N%, Invalid N%, Edge N%
- Test data entries added: N
- Status: COMPLETE

---

## Coverage Rule — All Condition Types, Every Feature

**MANDATORY:** Every feature must have test cases covering all three condition types.

| Condition Type | What to Test | Expected System Behavior | Test Passes When | Minimum |
|---|---|---|---|---|
| **Valid** | Happy path with correct data | System succeeds (creates/updates/displays record) | Record created/updated/displayed correctly | 1+ |
| **Invalid** | Wrong input, missing fields, unauthorized access | System correctly rejects/blocks (shows error message) | Error message appears as expected | 1+ |
| **Edge** | Boundary conditions, empty states, max field length, zero values, duplicates, rapid actions | System handles gracefully (no crash, displays correctly, prevents duplicates) | Boundary handled without error | 1+ |

**Critical:** For INVALID tests, the error/validation message IS the expected outcome. Test PASSES when error appears (validation works). Test FAILS when error doesn't appear (validation broken).

**Examples:**

**Valid (Test PASSES when system succeeds):**
- Create school with all fields → school appears in list

**Invalid (Test PASSES when system correctly rejects):**
- Create school with missing name → validation error shown (validation works)
- Create school with duplicate NOVA name → unique constraint error shown (duplicate prevention works)
- Create school without invoice email → warning on send action (notification works)

**Edge (Test PASSES when system handles boundaries gracefully):**
- Create school with 255-character name → created successfully, displays without truncation
- Create school with 0 service rates → created successfully (optional section)
- List schools when zero exist → shows "No data available" message (empty state handled)

---

## How Many Test Cases to Generate Per Feature?

**Minimum BASELINE:** 3 test cases per feature (1 Valid + 1 Invalid + 1 Edge)

**IMPORTANT:** The minimum (3 cases) is required but **NOT sufficient** for most features. It only covers:
- 1 happy path scenario
- 1 error scenario (but features often have 5+ ways to fail)
- 1 boundary (but features often have 3+ boundaries)

**Most features need significantly more.** The minimum is only acceptable for:
- Very simple features: Login, Logout, View Profile (single workflow, no validation)
- Even then, 4-6 cases is recommended

**Typical counts by feature complexity:**

| Feature Complexity | Valid | Invalid | Edge | Total | Examples |
|---|---|---|---|---|---|
| **Simple** (1-2 workflows) | 1 | 2-3 | 1-2 | 4-6 | Login, Logout, View Profile |
| **Medium** (3-5 workflows) | 2-3 | 3-5 | 2-3 | 7-11 | Create School, Create Student |
| **Complex** (5+ workflows, many validations) | 3-5 | 5-8 | 3-5 | 11-18 | Create Invoice, Import CSV, Generate Bill |
| **Very Complex** (state machine, integrations, external calls) | 5+ | 8+ | 5+ | 18+ | Billing Automation, Sub Coverage, Payment Processing |

**How to decide how many test cases a feature needs:**

For each feature, follow this formula:

```
Step 1: Count user workflows
  Examples: Create, Read, Edit, Delete, Send, Preview, Generate, Approve, Reject
  Result: N workflows → Need AT LEAST N Valid cases

Step 2: Count validation rules
  Examples: Required fields, format validation, duplicate check, status check, authorization
  Result: M validation rules → Need AT LEAST M Invalid cases (one per rule)

Step 3: Count boundary conditions
  Examples: Max field length, zero values, empty lists, rapid actions, date ranges, overlaps
  Result: K boundaries → Need AT LEAST K Edge cases (one per boundary)

Step 4: Calculate total
  Total = Valid + Invalid + Edge
  
  Example: Create School
    Workflows: Create (1) → 1-2 Valid cases
    Validations: Name, State, Timezone, Email format, Duplicate name (5) → 5 Invalid cases
    Boundaries: Max name length, Zero rates, Empty list (3) → 3 Edge cases
    ─────────────────────────────────
    TOTAL: 9-10 test cases minimum
    
    NOT 3 (the feature baseline)
```

**Complexity classification (for reference):**
- Simple (1-2 workflows, <3 validations, <2 boundaries): 4-6 test cases
- Medium (3-5 workflows, 3-5 validations, 2-3 boundaries): 7-11 test cases
- Complex (5+ workflows, 5+ validations, 3+ boundaries): 11-18+ test cases
```

**Example: Create Invoice Feature**

```
Workflows: Generate, Preview PDF, Send Email, Record Payment = 4 workflows
Validations: No approved logs, no school email, deactivated school, duplicate prevention, future dates = 5 validations
Boundaries: Single line item, $0 total, 100+ items, overpayment, rapid submission = 5 boundaries

Complexity: Medium-to-Complex
Test cases breakdown:
  - Valid: 3 cases (basic, single log, multiple therapists)
  - Invalid: 5 cases (one per validation)
  - Edge: 5 cases (one per boundary)
  Total: 13 test cases
```

---

## Role-Wide Test Distribution

**Apply these percentages to ANY role:**

| Condition Type | Percentage | Rationale |
|---|---|---|
| Valid | 30% | Happy path only needs 1 scenario per workflow |
| Invalid | 50% | Error handling requires testing multiple validation failures |
| Edge | 20% | Boundary conditions are fewer than validation rules |

**Why 50% Invalid?** Most testing focuses on error handling and validation. Features typically have 5+ ways to fail but fewer boundary conditions.

---

## Checklist: Did You Generate Enough Test Cases?

**Critical:** Minimum is 3 cases (1 Valid + 1 Invalid + 1 Edge). Most features need significantly more.

**For EVERY feature:**

- [ ] **Step 1 — Condition Types:** Has ≥1 Valid, ≥1 Invalid, ≥1 Edge? (If missing ANY → INCOMPLETE)
- [ ] **Step 2 — Validation Rules:** For each validation rule, ≥1 Invalid case? (Formula: Invalid cases ≥ # of rules)
- [ ] **Step 3 — Boundaries:** For each boundary condition, ≥1 Edge case? (Formula: Edge cases ≥ # of boundaries)
- [ ] **Step 4 — Workflows:** For each distinct workflow, ≥1 Valid case? (Formula: Valid cases ≥ # of workflows)
- [ ] **Step 5 — Complexity Match:** Simple (4-6 cases) | Medium (7-11) | Complex (11-18+) | Very Complex (18+)?

**Common Mistakes:**
- ❌ Stop at 1 Invalid case when feature has 5 validation rules (4+ bugs missed)
- ❌ Stop at 1 Edge case when feature has 3 boundaries (empty states, zero values missed)
- ❌ Stop at 3 total cases for complex features (only 3% coverage)

**DO:** Generate one Invalid per validation rule, one Edge per boundary, one Valid per workflow.

---

## Domain Rules

**Use the `/ld-expert-domain` skill** as your authoritative reference for:
- Role routes and access control rules
- Status lifecycles and state transitions
- Factory chain order for test data setup
- Timezone rules and UTC conversion
- Soft delete behavior and visibility rules

**Key Testing Reminders:**
- Every role must have a role isolation test (blocked from other roles' routes)
- Every status flow must be tested (blocked actions in wrong status)
- Soft-deleted records must not appear in lists
- Dates must display in user's timezone, not UTC

---

## Checklist Before Finalising Output

- [ ] test-plan.md covers every feature area in the wiki with priority assigned
- [ ] Every feature area has at least one positive, one negative, and one edge case row in Excel
- [ ] Every route in the wiki has at least one test scenario
- [ ] Every test case row in Excel has a Condition Type label (Valid / Invalid / Edge)
- [ ] test-data.md has a factory entry for every precondition pattern used in Excel rows
- [ ] Role isolation tested — each role blocked from other roles' routes
- [ ] Empty state tested — what shows when no data exists
- [ ] Error/validation state tested — what shows when input is wrong
- [ ] Soft delete behaviour tested — deleted records don't appear in lists
- [ ] Status flow tested — actions blocked when record is in wrong status

