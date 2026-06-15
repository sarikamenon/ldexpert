# QA Automation Workflow - Step by Step

**Purpose:** Use the QA automation framework to test the complete LD Expert flow

---

## 🎯 Complete Workflow

```
Step 1: Write Test Cases (in Excel)
         ↓
Step 2: Generate Test Files (/qa-generate-tests)
         ↓
Step 3: Run Tests (php artisan dusk)
         ↓
Step 4: Get Report (qa/reports/)
```

---

## 📋 Step 1: Prepare Test Cases in Excel

### **Location:** `qa/LD-Expert-QA.xlsx`

### **Format Required:**

Create a sheet with these columns:

| TC-ID | Title | Module | Steps | Expected Result | Role | Priority |
|-------|-------|--------|-------|-----------------|------|----------|
| TC-A001 | Admin logs in | Authentication | 1. Navigate to /login, 2. Enter credentials, 3. Click Login | Dashboard displays | Admin | P1 |
| TC-A002 | Create school | School Setup | 1. Click Schools, 2. Fill form, 3. Save | School created | Admin | P1 |

### **Using the Reference:**

See `qa/docs/TEST_CASES_CORE_FLOW.md` for all 12 test cases ready to copy.

---

## 🚀 Step 2: Generate Test Files

### **Run the `/qa-generate-tests` Skill**

```bash
/qa-generate-tests
```

**What it does:**
1. Reads `qa/LD-Expert-QA.xlsx`
2. Parses each test case
3. Generates PHP test files in `tests/BrowserQA/`
4. Creates files organized by role:
   ```
   tests/BrowserQA/
   ├─ Admin/
   │  ├─ CreateSchoolTest.php
   │  ├─ ApproveSessionTest.php
   │  └─ GenerateInvoiceTest.php
   ├─ Therapist/
   │  ├─ LoginTest.php
   │  ├─ LogSessionTest.php
   │  └─ SubmitSessionTest.php
   └─ E2E/
      └─ CompleteFlowTest.php
   ```

**Output:**
```
✓ Generated: tests/BrowserQA/Admin/CreateSchoolTest.php
✓ Generated: tests/BrowserQA/Admin/ApproveSessionTest.php
✓ Generated: tests/BrowserQA/Therapist/LoginTest.php
... (etc)

Total: 12 test files generated
```

---

## 🧪 Step 3: Run the Tests

### **Option A: Run All Tests**

```bash
docker compose exec -T app php artisan dusk tests/BrowserQA/
```

**Output:**
```
Running tests for LD Expert QA Suite...

✓ TC-A001: Admin logs in                    [1.2s]
✓ TC-A002: Create school                    [3.5s]
✓ TC-A003: Create contract                  [2.8s]
✓ TC-A004: Create therapist                 [2.1s]
✓ TC-A005: Create student                   [2.3s]
✓ TC-A006: Create SSA                       [3.2s]
✓ TC-A007: Assign therapist                 [1.5s]
✓ TC-T001: Therapist login                  [1.1s]
✓ TC-T002: View SSAs                        [1.8s]
✓ TC-T003: Create schedule                  [2.5s]
✓ TC-T004: Log session                      [3.1s]
✓ TC-T005: Submit session                   [1.9s]
✓ TC-A008: Review submissions               [2.2s]
✓ TC-A009: Approve session & increment      [2.8s]  ← CRITICAL TEST
✓ TC-A010: Generate invoice                 [4.1s]
✓ TC-A011: Generate therapist bill          [3.9s]

====================================
12 passed, 0 failed
Total time: 45.9 seconds
====================================
```

### **Option B: Run Specific Test File**

```bash
# Run only admin tests
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin/

# Run only therapist tests
docker compose exec -T app php artisan dusk tests/BrowserQA/Therapist/

# Run only one test
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin/CreateSchoolTest.php
```

### **Option C: Run with Verbose Output**

```bash
docker compose exec -T app php artisan dusk tests/BrowserQA/ --verbose
```

Shows detailed output of what each step does.

### **Option D: Run and Take Screenshots**

```bash
docker compose exec -T app php artisan dusk tests/BrowserQA/ --screenshots=tests/BrowserQA/screenshots
```

Saves screenshots of each step to `tests/BrowserQA/screenshots/`

---

## 📊 Step 4: Generate & View Report

### **Automatic Report Generation**

After running tests, reports are automatically generated:

```bash
qa/reports/
├─ dusk-2026-06-10-153045.log          (Test log)
├─ dusk-2026-06-10-153045.json         (Test data)
└─ dusk-2026-06-10-153045.html         (HTML report)
```

### **View the HTML Report**

```bash
# Open in browser (adjust path to latest report)
open qa/reports/dusk-2026-06-10-153045.html
```

**Report Shows:**
```
Test Execution Summary
├─ Total Tests: 12
├─ Passed: 12 ✓
├─ Failed: 0
├─ Skipped: 0
├─ Duration: 45.9 seconds
└─ Status: PASSED ✓

Test Details
├─ TC-A001: Admin logs in [PASS]
├─ TC-A002: Create school [PASS]
├─ TC-A003: Create contract [PASS]
... (etc)

Coverage by Role
├─ Admin: 8 tests (8 passed)
├─ Therapist: 4 tests (4 passed)
└─ E2E: 1 test (1 passed)

Performance
├─ Fastest: TC-A001 (1.1s)
├─ Slowest: TC-A010 (4.1s)
└─ Average: 3.8s per test
```

---

## 🎯 Complete Step-by-Step Execution

### **Scenario: Test Your Complete Flow**

```bash
# Step 1: Ensure you have Excel file ready
# (See qa/docs/TEST_CASES_CORE_FLOW.md for template)

# Step 2: Enter test cases in qa/LD-Expert-QA.xlsx
# (12 test cases total)

# Step 3: Generate test files
/qa-generate-tests

# Step 4: Ensure database is clean
docker compose exec -T app php artisan migrate:fresh

# Step 5: Run all tests
docker compose exec -T app php artisan dusk tests/BrowserQA/

# Step 6: View results
open qa/reports/dusk-[timestamp].html
```

**Total time:** ~60 seconds

---

## ✅ What Each Test Verifies

| Test ID | Verifies | Critical? |
|---------|----------|-----------|
| TC-A001 | Admin authentication | ✓ Yes |
| TC-A002 | School creation | ✓ Yes |
| TC-A003 | Service contracts | ✓ Yes |
| TC-A004 | Therapist user creation | ✓ Yes |
| TC-A005 | Student user + school link | ✓ Yes |
| TC-A006 | SSA creation with all fields | ✓ Yes |
| TC-A007 | Therapist assignment to SSA | ✓ Yes |
| TC-T001 | Therapist authentication | ✓ Yes |
| TC-T002 | Therapist sees assigned SSAs | ✓ Yes |
| TC-T003 | Schedule creation | ⚠️ Important |
| TC-T004 | Session logging | ✓ Yes |
| TC-T005 | Session submission | ✓ Yes |
| TC-A008 | Admin review submissions | ⚠️ Important |
| **TC-A009** | **Approval + Hours Increment** | **✓ CRITICAL** |
| TC-A010 | Invoice generation | ✓ Yes |
| TC-A011 | Therapist billing | ✓ Yes |

---

## 🔍 Understanding Test Results

### **All Pass (12/12)** ✅
```
Excellent!
- Complete flow works end-to-end
- All features implemented correctly
- Ready for production
- No bugs found
```

### **Some Fail** ❌
```
Example: TC-A009 fails (hours don't increment)

What to do:
1. Check test output for exact error
2. Investigate what's missing
3. Fix the code
4. Re-run tests

Example error message:
  "AssertionError: Expected served_minutes to be 30, got 0"
  → Hours are not auto-incrementing when session approved
```

---

## 🛠️ Troubleshooting

### **Issue: Tests can't find Chrome**
```bash
Error: ChromeDriver not found

Solution:
docker compose exec -T app which chromedriver
# Should show: /usr/bin/chromedriver
```

### **Issue: Database locked during test**
```bash
Error: Database is locked

Solution:
docker compose exec -T app php artisan migrate:fresh
# Ensures clean database before tests
```

### **Issue: Test times out**
```bash
Error: Element not found after 30 seconds

Solution:
- Check if the form field exists in the view
- Check if the button has the right selector
- Increase timeout in dusk config
```

### **Issue: No report generated**
```bash
Report file missing

Solution:
# Check if tests actually ran
docker compose exec -T app php artisan dusk tests/BrowserQA/ --verbose
# Look for error messages
```

---

## 📚 Files Reference

```
qa/docs/
├─ TEST_CASES_CORE_FLOW.md
│  └─ All 12 test cases ready to copy
│
├─ QA_WORKFLOW_GUIDE.md (this file)
│  └─ Detailed step-by-step guide
│
└─ QUICK_START_QA.md
   └─ Quick start guide

qa/
├─ LD-Expert-QA.xlsx (you create/edit this)
│  └─ Excel file with test cases for /qa-generate-tests
│
└─ reports/
   ├─ dusk-YYYY-MM-DD-HHMMSS.html (HTML report)
   ├─ dusk-YYYY-MM-DD-HHMMSS.json (Test data)
   └─ dusk-YYYY-MM-DD-HHMMSS.log (Test log)

tests/BrowserQA/
├─ Admin/
│  ├─ AuthenticationTest.php (generated)
│  ├─ SchoolManagementTest.php (generated)
│  └─ ... (others generated)
│
├─ Therapist/
│  ├─ AuthenticationTest.php (generated)
│  ├─ SessionLoggingTest.php (generated)
│  └─ ... (others generated)
│
└─ E2E/
   └─ CompleteFlowTest.php (generated)
```

---

## 🚀 Quick Reference Commands

```bash
# Generate test files from Excel
/qa-generate-tests

# Run all tests
docker compose exec -T app php artisan dusk tests/BrowserQA/

# Run only admin tests
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin/

# Run only therapist tests
docker compose exec -T app php artisan dusk tests/BrowserQA/Therapist/

# Run with verbose output
docker compose exec -T app php artisan dusk tests/BrowserQA/ --verbose

# Run and save screenshots
docker compose exec -T app php artisan dusk tests/BrowserQA/ --screenshots=tests/BrowserQA/screenshots

# Run specific test
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin/CreateSchoolTest.php

# Clean database before running
docker compose exec -T app php artisan migrate:fresh

# View latest report
open qa/reports/dusk-*.html  # On Mac
# Or in browser: /qa/reports/
```

---

## ✨ Summary

**This is the QA Automation Framework Workflow:**

```
1. Write test cases in Excel (qa/LD-Expert-QA.xlsx)
2. Run /qa-generate-tests (generates PHP test files)
3. Run php artisan dusk tests/BrowserQA/ (executes tests)
4. View report in qa/reports/ (see results)

That's it!
```

**For your 12 test cases:**
- Copy from `qa/docs/TEST_CASES_CORE_FLOW.md`
- Enter into Excel
- Run `/qa-generate-tests`
- Run `php artisan dusk`
- Get report with pass/fail status
