# LD Expert System - Master Implementation Plan

**Date:** 2026-06-10  
**Status:** Ready to Implement  
**Approach:** Plan First → Then Implement

---

## 🎯 Core Flow (Complete End-to-End)

```
ADMIN SETUP
├─ Create School
├─ Create Contract (services)
├─ Create Therapist
├─ Create Student
├─ Create SSA (Service Support Agreement)
└─ Assign Therapist to SSA

↓

THERAPIST WORK
├─ Therapist Login
├─ Create Schedule
├─ Create Session Log
└─ Submit Session

↓

ADMIN APPROVAL & BILLING
├─ Admin Login
├─ Approve Session
├─ Generate Invoice
└─ Generate Therapist Bill
```

---

## ✅ What's Confirmed WORKING (Don't Change)

From screenshots and testing:

```
ADMIN SETUP:
✅ Create School - WORKS
✅ Create Contract (services) - WORKS
✅ Create Therapist - WORKS
✅ Create Student - WORKS
✅ Create SSA - WORKS
✅ Assign Therapist to SSA - WORKS

THERAPIST WORK:
✅ Therapist Login - WORKS
✅ Create Schedule - WORKS
✅ Create Session Log - WORKS (form has all fields)
✅ Submit Session - WORKS (Draft → Submitted status)
✅ Session Details - WORKS (all data captured)
✅ Session Submission Confirmation - WORKS (modal confirmation shown)

ADMIN APPROVAL & BILLING:
🔍 NEEDS VERIFICATION: "Approve Session" step
🔍 NEEDS VERIFICATION: "Generate Invoice" step
🔍 NEEDS VERIFICATION: "Generate Therapist Bill" step
❓ NEEDS CLARIFICATION: Does "Submitted" = "Approved"?
```

---

## 🔴 What's MISSING (Need to Implement)

### **Feature 1: School Column in SSA Table** (QUICK WIN)
```
Status: READY TO IMPLEMENT
Time: 30 minutes
Impact: Therapist can see which school each SSA belongs to

Files to Change:
├─ resources/views/therapist/service-support-agreements/index.blade.php
│  └─ Add <th>School</th> header
├─ app/DataTables/Transformers/SSARowTransformer.php
│  └─ Add school name to response
└─ resources/js/pages/therapist/... (if exists)
   └─ Update column indices
```

### **Feature 2: Filter SSAs by School** (QUICK WIN)
```
Status: READY TO IMPLEMENT
Time: 45 minutes
Impact: Therapist can filter SSAs by school

Files to Change:
├─ resources/views/therapist/service-support-agreements/index.blade.php
│  └─ Add school filter dropdown
├─ app/Http/Controllers/Therapist/ServiceSupportAgreementController.php
│  └─ Pass schools to view
│  └─ Filter by school_id in data endpoint
├─ app/Http/Requests/SSADataRequest.php
│  └─ Add validation for filter_school_id
└─ resources/js/pages/therapist/...
   └─ Wire filter form to table reload
```

---

## ❓ Critical Gaps (Need Clarification)

### **Gap 1: Therapist Reassignment**
```
Question: Can a therapist be REASSIGNED to an existing SSA?

Why it matters:
- If therapist leaves/unavailable, can we assign a new one?
- Does served_minutes reset or carry over?
- Can we see which therapist was assigned when?

Risk: If broken, therapist change breaks continuity
Expected: YES - reassignment must work, hours must carry over

Status: NEEDS CLARIFICATION FROM USER
```

### **Gap 2: Served Hours Auto-Increment**
```
Question: Does "Served" hours auto-increment when session is submitted?

Current situation:
- SSA shows "Served: 0.00"
- Therapist submits 30-minute session
- Does it change to "Served: 0.50"?

Why it matters:
- If manual: Therapist has to enter it (error-prone)
- If automatic: System calculates it (correct)

Risk: If broken, hours tracking is unreliable
Expected: AUTO-INCREMENT on submit

Status: NEEDS VERIFICATION
```

### **Gap 3: Approval Workflow**
```
Question: How does session approval work?

Current: Therapist submits session → Status = "Submitted"

Unclear:
- Who approves? (Therapist self-approve? Admin approve?)
- When does served_minutes increment? (On submit? On approve?)
- Is "Submitted" = "Approved"?

Risk: If broken, hours don't get tracked
Expected: Clear workflow

Status: NEEDS CLARIFICATION
```

### **Gap 4: Assignment Audit Trail**
```
Question: Can we see which therapist was assigned when?

Current: SSA.assigned_therapist_id shows current therapist

Unclear:
- Is there a history table?
- Can we see "therapist 104 Jan-Feb, therapist 105 Feb-present"?
- Can we verify hours preserved when reassigned?

Risk: If broken, can't track service continuity
Expected: Full audit trail

Status: NEEDS CLARIFICATION
```

---

## 🎯 Implementation Plan (Phase 1)

### **Priority 1: Quick Wins (Today/Tomorrow)**

```
TASK 1.1: Add School Column to SSA Table
├─ Files: 3-4 files
├─ Time: 30 minutes
├─ Effort: Low
├─ Risk: Very Low
├─ Blocker: None
└─ After: Therapist can see school for each SSA

TASK 1.2: Add School Filter to SSA View
├─ Files: 4-5 files
├─ Time: 45 minutes
├─ Effort: Low
├─ Risk: Very Low
├─ Blocker: None
└─ After: Therapist can filter by school

TASK 1.3: Test Both Features
├─ Time: 30 minutes
├─ Manual testing (browser)
├─ Run make qa (code quality)
└─ After: Code clean, features work

TOTAL TIME: 1.5-2 hours
```

### **Priority 2: Clarifications (Parallel, Not Blocking)**

```
TASK 2.1: Verify Gap 1 - Therapist Reassignment
├─ Check code: Can we update assigned_therapist_id?
├─ Check database: Does served_minutes reset?
├─ Answer: Yes/No/Partial
└─ Time: 30 minutes

TASK 2.2: Verify Gap 2 - Hours Auto-Increment
├─ Check code: Is there an observer/trigger?
├─ Check database: How is served_minutes updated?
├─ Answer: Auto/Manual/Broken
└─ Time: 20 minutes

TASK 2.3: Clarify Gap 3 - Approval Workflow
├─ Check: Who can approve sessions?
├─ Check: When does served_minutes increment?
├─ Answer: Therapist self-approve or Admin?
└─ Time: 15 minutes

TASK 2.4: Clarify Gap 4 - Audit Trail
├─ Check: Is there therapist_ssa_assignments table?
├─ Check: Can we see assignment history?
├─ Answer: Yes/No/Partial
└─ Time: 10 minutes

TOTAL TIME: 1.5 hours (can do while implementing Priority 1)
```

### **Priority 3: Implementation (If Gaps Require It)**

```
Based on Gap answers:
├─ If reassignment broken: Fix it (1-2 hours)
├─ If hours not auto-increment: Implement observer (30 min)
├─ If approval unclear: Create UI (1-2 hours)
└─ If no audit trail: Create table + relationships (2-3 hours)

Only do these if clarifications show they're needed.
```

---

## 📊 What We Know vs. What We Need to Verify

| Feature | Status | Verified? | Risk |
|---------|--------|-----------|------|
| Create School | ✅ Works | Yes | None |
| Create Contract | ✅ Works | Yes | None |
| Create Student | ✅ Works | Yes | None |
| Create SSA | ✅ Works | Yes | None |
| Assign Therapist | ✅ Works | Yes | None |
| Therapist Login | ✅ Works | Yes | None |
| Create Schedule | ✅ Works | Yes | None |
| Log Session | ✅ Works | Yes | None |
| Submit Session | ✅ Works | Yes | None |
| **Therapist Reassignment** | ❓ Unknown | No | **HIGH** |
| **Hours Auto-Increment** | ❓ Unknown | No | **HIGH** |
| **Session Approval** | ❓ Unknown | No | **HIGH** |
| **Audit Trail** | ❓ Unknown | No | **MEDIUM** |
| School Column (SSA Table) | 🔴 Missing | No | **LOW** |
| School Filter (SSA View) | 🔴 Missing | No | **LOW** |

---

## 🚀 Step-by-Step Implementation Order

### **STEP 1: Implement Quick Wins (1.5-2 hours)**

```bash
# This is self-contained, doesn't depend on anything else

1. Add School column to SSA table (30 min)
2. Add School filter to SSA view (45 min)
3. Test thoroughly (30 min)
4. Commit to git (5 min)

Result: Therapist can now see and filter by school ✅
```

**Code locations to modify:**
- `resources/views/therapist/service-support-agreements/index.blade.php`
- `app/DataTables/Transformers/SSARowTransformer.php`
- `app/Http/Controllers/Therapist/ServiceSupportAgreementController.php`
- `resources/js/pages/therapist/...` (if exists)

### **STEP 2: Verify Critical Gaps (1-2 hours, parallel)**

While testing Step 1, research:

```
1. Check database: Can therapist_id be reassigned?
2. Check models: Is there an observer on SessionLog?
3. Check controllers: Who approves sessions?
4. Check migrations: Is there a therapist_ssa_assignments table?
```

### **STEP 3: Fix Gaps (If Needed)**

Only implement based on what Step 2 finds:

```
If reassignment is broken: Fix it
If hours don't auto-increment: Implement observer
If approval is unclear: Clarify workflow
If no audit trail: Consider if needed
```

---

## 📋 Testing Plan (After Implementation)

### **Manual Testing**
```
1. Login as therapist
2. Navigate to SSAs
3. Verify School column visible
4. Verify school names display
5. Click School filter dropdown
6. Filter by one school
7. Verify table shows only that school's SSAs
8. Filter by another school
9. Verify table updates
10. Clear filter
11. Verify all SSAs show again
```

### **Code Quality**
```bash
docker compose exec -T app make qa
# Should pass: Pint (formatting) + PHPStan (types) + Pest (tests)
```

### **Database Check**
```bash
docker compose exec -T app php artisan tinker
> $ssa = ServiceSupportAgreement::find(1)
> $ssa->school?->name  // Should show school name
> exit
```

---

## ❌ What NOT to Do

```
DON'T:
├─ Don't refactor existing code
├─ Don't change database schema
├─ Don't modify session logging
├─ Don't change approval workflow
├─ Don't create new tables
└─ Don't change how hours are tracked

DO:
├─ Do add school column to display
├─ Do add school filter to form
├─ Do test thoroughly
├─ Do verify gaps exist
└─ Do fix gaps ONLY if they're broken
```

---

## 🎯 Success Criteria

When done:

- ✅ Therapist can see which school each SSA is for
- ✅ Therapist can filter SSAs by school
- ✅ All existing functionality still works
- ✅ Code quality checks pass
- ✅ Manual testing passes
- ✅ All 4 gaps identified and documented

---

## 🗂️ Files You Actually Need

**That's it. One file.**

```
app/qa/docs/MASTER_PLAN.md (this file)
├─ Everything you need in one place
├─ Clear priorities
├─ Step-by-step implementation
└─ Success criteria
```

**Optional (reference only):**
```
app/qa/docs/TEST_CASES_CORE_FLOW.md
└─ If you want to define QA test cases
```

---

## 💡 Why Minimal Documentation?

### **The Problem with Too Many Docs:**
```
❌ Hard to maintain (update one, break three others)
❌ Easy to get lost (which file has what?)
❌ Overwhelming (11 files to read)
❌ Outdated quickly (no one maintains all of them)
❌ Conflicting info (one says X, another says Y)
```

### **The Solution: One Master Plan**
```
✅ Single source of truth
✅ Everything in one place
✅ Easy to update (one file)
✅ Easy to follow (from top to bottom)
✅ Consistent information
```

---

## 📅 Timeline

```
Day 1 (2 hours):
├─ 30 min: Implement school column
├─ 45 min: Implement school filter
├─ 30 min: Test and verify
└─ 15 min: Commit to git

Day 2 (1-2 hours):
├─ 30 min: Verify Gap 1 (reassignment)
├─ 20 min: Verify Gap 2 (hours auto-increment)
├─ 15 min: Verify Gap 3 (approval workflow)
├─ 10 min: Verify Gap 4 (audit trail)
└─ 30 min: Document findings

Day 3+ (if needed):
├─ Fix any broken gaps identified in Day 2
├─ Test fixes
└─ Commit and merge
```

---

## 🎓 What You'll Know After This Plan

After implementing this plan, you'll know:

1. ✅ What's working (confirmed via testing)
2. ✅ What's missing (school column + filter implemented)
3. ✅ What's broken (4 gaps identified and either fixed or documented)
4. ✅ What's the current status (ready for production or needs work)
5. ✅ What's next (either merge or fix more gaps)

---

## 🚀 Ready to Start?

**Next Step:**
1. Review this plan (10 min)
2. Confirm 4 gaps by checking code (1.5 hours)
3. Implement school column + filter (1.5 hours)
4. Test everything (30 min)
5. Commit (5 min)

**Total: ~5-6 hours**

**After that:** You'll know exactly what's left to do.

---

**This is your complete implementation plan.**
**Everything else is optional reference material.**
