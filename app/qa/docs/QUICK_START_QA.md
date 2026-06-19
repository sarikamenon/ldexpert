# QA Automation Quick Start - Complete Flow Testing

**Goal:** Test the complete LD Expert business flow and generate report

**Time:** ~90 minutes total

---

## 🎯 Your 12-Step Flow (To Be Tested)

```
Step 1: Admin Login
Step 2: Create School
Step 3: Create Contract (services)
Step 4: Create Therapist
Step 5: Create Student
Step 6: Create SSA
Step 7: Assign Therapist to SSA
Step 8: Therapist Login
Step 9: Create Schedule
Step 10: Log Session
Step 11: Submit Session
Step 12: Approve Session & Verify Hours Increment
Step 13: Generate Invoice
Step 14: Generate Therapist Bill
```

---

## ⚡ Quick Start (5 Steps)

### **Step 1: Get the Test Cases (5 min)**

**File:** `app/qa/docs/TEST_CASES_CORE_FLOW.md`

Contains all 12 test cases in the correct format.

### **Step 2: Enter Test Cases into Excel (15 min)**

**File:** `app/qa/LD-Expert-QA.xlsx`

Copy-paste format:
```
| TC-A001 | Admin logs in | Authentication | 1. Go to login page, 2. Enter email, 3. Enter password, 4. Click login | Dashboard displays | Admin | P1 |
| TC-A002 | Create school | School Setup | 1. Click Schools, 2. Fill form, 3. Save | School created | Admin | P1 |
... (12 rows total)
```

**Or use the template structure from TEST_CASES_CORE_FLOW.md**

### **Step 3: Generate Test Files (2 min)**

Run the skill:
```bash
/qa-generate-tests
```

**Output:**
```
✓ Generated tests/BrowserQA/Admin/CreateSchoolTest.php
✓ Generated tests/BrowserQA/Admin/ApproveSessionTest.php
✓ Generated tests/BrowserQA/Therapist/LoginTest.php
... (12 files total)
```

### **Step 4: Run the Tests (2 min)**

```bash
docker compose exec -T app php artisan dusk tests/BrowserQA/
```

**Output:**
```
✓ TC-A001: Admin logs in [PASS]
✓ TC-A002: Create school [PASS]
✓ TC-A003: Create contract [PASS]
... (all 12 tests show pass/fail)

====================================
Results: 12 passed, 0 failed
====================================
```

### **Step 5: View Report (1 min)**

**Location:** `app/qa/reports/dusk-2026-06-10-HHMMSS.html`

Open in browser to see detailed report.

---

## 📊 What You'll Know After Running Tests

### **If All Tests Pass (12/12)** ✅
```
✓ Complete flow works end-to-end
✓ Admin can create school, student, therapist
✓ Therapist can log and submit sessions
✓ Admin can approve sessions
✓ Hours auto-increment correctly
✓ Invoices generate correctly
✓ Bills generate correctly
→ System is production-ready
```

### **If Some Tests Fail** ❌
```
Example: TC-A009 fails

Error: "Expected served_minutes = 30, got 0"
→ Hours are NOT auto-incrementing on approval

What to do:
1. Note which test failed
2. That's your bug to fix
3. Fix the code
4. Re-run tests to verify fix
```

---

## 🔄 Complete Workflow Diagram

```
Your Flow (12 steps)
    ↓
TEST_CASES_CORE_FLOW.md (read this)
    ↓
Enter test cases in app/qa/LD-Expert-QA.xlsx
    ↓
/qa-generate-tests (generates PHP test files)
    ↓
tests/BrowserQA/ (12 Dusk test files created)
    ↓
php artisan dusk tests/BrowserQA/ (runs tests)
    ↓
app/qa/reports/dusk-[timestamp].html (view results)
    ↓
REPORT SHOWS: What works ✓ and what's broken ❌
```

---

## 📁 Files You'll Work With

### **Read First:**
```
app/qa/docs/TEST_CASES_CORE_FLOW.md
└─ All 12 test cases - copy from here
```

### **Edit:**
```
app/qa/LD-Expert-QA.xlsx
└─ Paste test cases here
```

### **Generated (don't edit):**
```
tests/BrowserQA/
├─ Admin/
├─ Therapist/
└─ E2E/
```

### **Results:**
```
app/qa/reports/
└─ dusk-[date-time].html
```

### **Reference:**
```
app/qa/docs/QA_WORKFLOW_GUIDE.md
└─ Detailed step-by-step guide
```

---

## ✅ Your Complete Test Suite

### **Admin Tests (8 tests)**
- [x] TC-A001: Admin login
- [x] TC-A002: Create school
- [x] TC-A003: Create contract
- [x] TC-A004: Create therapist
- [x] TC-A005: Create student
- [x] TC-A006: Create SSA
- [x] TC-A007: Assign therapist to SSA
- [x] TC-A008: Review submissions

### **Approval & Billing (3 tests)**
- [x] TC-A009: **Approve session + Hours increment** ← CRITICAL
- [x] TC-A010: Generate invoice
- [x] TC-A011: Generate therapist bill

### **Therapist Tests (4 tests)**
- [x] TC-T001: Therapist login
- [x] TC-T002: View assigned SSAs
- [x] TC-T003: Create schedule
- [x] TC-T004: Log session
- [x] TC-T005: Submit session

**Total: 15 test cases covering complete flow**

---

## 🎯 Success Criteria

**All tests should PASS:**
```
✓ All 15 tests: PASS
✓ Report shows: 15 passed, 0 failed
✓ Execution time: ~60 seconds
✓ Coverage: 100% of core flow
```

**If test fails, you found a bug:**
```
Example: TC-A009 fails
→ Hours don't auto-increment
→ Fix this in code
→ Re-run tests
→ Confirm fix works
```

---

## 🚀 Ready to Start?

### **Timeline**
```
Phase 1: Prepare (20 min)
├─ Read TEST_CASES_CORE_FLOW.md
└─ Enter into Excel

Phase 2: Generate (2 min)
└─ Run /qa-generate-tests

Phase 3: Execute (5 min)
└─ Run php artisan dusk

Phase 4: Review (5 min)
└─ Open report

Total: ~30 minutes
```

### **Next Steps**
1. **Read:** `app/qa/docs/TEST_CASES_CORE_FLOW.md`
2. **Create:** `app/qa/LD-Expert-QA.xlsx` with test cases
3. **Generate:** `/qa-generate-tests`
4. **Run:** `php artisan dusk tests/BrowserQA/`
5. **Review:** `app/qa/reports/[latest].html`

---

## 💡 Key Points

1. **One test per flow step** - 12 critical steps = 12+ tests
2. **Automated testing** - `/qa-generate-tests` generates the test code
3. **Browser simulation** - Tests click buttons like a real user would
4. **Automatic reporting** - Report generated after each run
5. **Quick feedback** - Know immediately what works and what doesn't

---

## 📞 Reference

**For detailed guide:** `app/qa/docs/QA_WORKFLOW_GUIDE.md`  
**For test cases:** `app/qa/docs/TEST_CASES_CORE_FLOW.md`  
**For master plan:** `app/qa/docs/MASTER_PLAN.md`

---

**You're all set! Start with reading `app/qa/docs/TEST_CASES_CORE_FLOW.md` now.** 🚀
