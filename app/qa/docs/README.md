# QA Documentation Index

**Location:** `app/qa/docs/`  
**Purpose:** Complete QA automation framework documentation  
**Last Updated:** 2026-06-10

---

## 🎯 Quick Navigation

### **Start Here** ⭐

1. **[QUICK_START_QA.md](QUICK_START_QA.md)** (5 min read)
   - Overview of QA workflow
   - What to do next
   - 4-step process
   - Success criteria

---

### **Core Documentation**

2. **[TEST_CASES_CORE_FLOW.md](TEST_CASES_CORE_FLOW.md)** (Reference)
   - All 12 test cases for your business flow
   - Format: Ready to copy into Excel
   - Includes: Admin setup, Therapist work, Approval & Billing
   - Copy from here → Paste into `app/qa/LD-Expert-QA.xlsx`

3. **[QA_WORKFLOW_GUIDE.md](QA_WORKFLOW_GUIDE.md)** (Detailed guide)
   - Step-by-step QA automation framework
   - How to run tests
   - How to view reports
   - Troubleshooting guide
   - Command reference

4. **[MASTER_PLAN.md](MASTER_PLAN.md)** (Implementation plan)
   - Complete system plan
   - What's working vs missing
   - 4 critical gaps to verify
   - Implementation priorities
   - Timeline

---

## 🔄 Complete Workflow

```
Step 1: Read QUICK_START_QA.md (5 min)
         ↓
Step 2: Copy test cases from TEST_CASES_CORE_FLOW.md
         ↓
Step 3: Paste into app/qa/LD-Expert-QA.xlsx
         ↓
Step 4: Run /qa-generate-tests
         ↓
Step 5: Run php artisan dusk tests/BrowserQA/
         ↓
Step 6: View report in app/qa/reports/dusk-[timestamp].html
```

---

## 📋 Test Cases Summary

**12 Core Test Cases Covering:**

### Phase 1: Admin Setup (7 tests)
- TC-A001: Admin login
- TC-A002: Create school
- TC-A003: Create contract (services)
- TC-A004: Create therapist
- TC-A005: Create student
- TC-A006: Create SSA
- TC-A007: Assign therapist to SSA

### Phase 2: Therapist Work (5 tests)
- TC-T001: Therapist login
- TC-T002: View assigned SSAs
- TC-T003: Create schedule
- TC-T004: Log session
- TC-T005: Submit session

### Phase 3: Approval & Billing (3 tests)
- TC-A008: Review submissions
- **TC-A009: Approve session & hours increment** ← CRITICAL
- TC-A010: Generate invoice
- TC-A011: Generate therapist bill

---

## 🚀 Quick Commands

```bash
# Generate test files from Excel
/qa-generate-tests

# Run all tests
docker compose exec -T app php artisan dusk tests/BrowserQA/

# View test report
open app/qa/reports/dusk-*.html

# Run specific suite
docker compose exec -T app php artisan dusk tests/BrowserQA/Admin/
docker compose exec -T app php artisan dusk tests/BrowserQA/Therapist/
```

---

## 📁 Files in This Directory

```
app/qa/docs/
├─ README.md (this file)
│  └─ Navigation and overview
│
├─ QUICK_START_QA.md ⭐
│  └─ Start here (5 min read)
│
├─ TEST_CASES_CORE_FLOW.md
│  └─ All 12 test cases - copy into Excel
│
├─ QA_WORKFLOW_GUIDE.md
│  └─ Detailed step-by-step guide
│
└─ MASTER_PLAN.md
   └─ Implementation plan & priorities
```

---

## 📊 What You'll Know After Testing

### **If All Tests Pass (12/12)** ✅
```
✓ Complete flow works end-to-end
✓ All features implemented correctly
✓ System production-ready
✓ No bugs found
```

### **If Tests Fail** ❌
```
You'll know exactly which step is broken and can fix it
- Failed test = identified bug
- Fix code → Re-run → Confirm fix works
```

---

## 🎯 Which File For What?

| Need | File | Time |
|------|------|------|
| Quick overview | QUICK_START_QA.md | 5 min |
| Test cases to copy | TEST_CASES_CORE_FLOW.md | 15 min |
| Detailed how-to | QA_WORKFLOW_GUIDE.md | 30 min |
| Complete plan | MASTER_PLAN.md | 20 min |

---

## ✨ Key Points

1. **Copy test cases** from `TEST_CASES_CORE_FLOW.md` into `app/qa/LD-Expert-QA.xlsx`
2. **Run `/qa-generate-tests`** to automatically generate PHP test files
3. **Run `php artisan dusk`** to execute the tests
4. **View report** in `app/qa/reports/` to see results
5. **Fix any failures** and re-run tests to confirm

---

## 🔗 Related Files

```
Outside app/qa/docs/:

app/qa/
├─ LD-Expert-QA.xlsx ← You edit this (test cases from TEST_CASES_CORE_FLOW.md)
├─ reports/ ← Auto-generated test reports
└─ docs/ ← This folder
   ├─ QUICK_START_QA.md
   ├─ TEST_CASES_CORE_FLOW.md
   ├─ QA_WORKFLOW_GUIDE.md
   ├─ MASTER_PLAN.md
   └─ README.md (this file)

tests/
└─ BrowserQA/ ← Auto-generated test files (don't edit)
   ├─ Admin/
   ├─ Therapist/
   └─ E2E/
```

---

## 💡 Quick Reference

**What is this?**
- QA automation framework documentation for testing the LD Expert system

**What do I do?**
1. Copy test cases from `TEST_CASES_CORE_FLOW.md`
2. Paste into `app/qa/LD-Expert-QA.xlsx`
3. Run `/qa-generate-tests`
4. Run `php artisan dusk tests/BrowserQA/`
5. Get report

**How long does it take?**
- Setup: 20 minutes
- Running tests: 2 minutes
- Viewing results: 5 minutes
- **Total: ~30 minutes**

**What if tests fail?**
- You found a bug
- Check the error message
- Fix the code
- Re-run tests

---

## 📞 Need Help?

| Question | File | Section |
|----------|------|---------|
| What's the process? | QUICK_START_QA.md | Quick Start |
| How do I write test cases? | TEST_CASES_CORE_FLOW.md | Full examples |
| How do I run tests? | QA_WORKFLOW_GUIDE.md | Step 3 |
| What's the complete plan? | MASTER_PLAN.md | Full document |
| Troubleshooting? | QA_WORKFLOW_GUIDE.md | Troubleshooting |

---

## 🎓 Learning Path

**If you have 10 minutes:**
→ Read QUICK_START_QA.md

**If you have 30 minutes:**
→ Read QUICK_START_QA.md + TEST_CASES_CORE_FLOW.md (skim)

**If you have 1 hour:**
→ Read all 4 files in order:
1. QUICK_START_QA.md
2. TEST_CASES_CORE_FLOW.md
3. QA_WORKFLOW_GUIDE.md
4. MASTER_PLAN.md

**If you're ready to implement:**
→ Start with QUICK_START_QA.md then follow the steps

---

## ✅ Checklist

Before you start:
- [ ] Have you read QUICK_START_QA.md?
- [ ] Do you understand the 4-step workflow?
- [ ] Do you have access to `app/qa/LD-Expert-QA.xlsx`?
- [ ] Have you reviewed TEST_CASES_CORE_FLOW.md?

Ready to go:
- [ ] Copy test cases into Excel
- [ ] Run `/qa-generate-tests`
- [ ] Run `php artisan dusk tests/BrowserQA/`
- [ ] View report

---

**Start with [QUICK_START_QA.md](QUICK_START_QA.md) - 5 minute read!** 🚀

