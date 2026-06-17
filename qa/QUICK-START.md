# QA Test Case Generation — Quick Start Guide

**This guide shows the exact commands to generate QA test cases using the `/qa-create-scenarios` skill.**

---

## Exact Commands to Run

**Copy and paste these commands exactly:**

### Admin Role
```
/qa-create-scenarios (Role: admin)
```
Generates comprehensive admin test cases

### Therapist Role
```
/qa-create-scenarios (Role: therapist)
```
Generates comprehensive therapist test cases

### Student Role
```
/qa-create-scenarios (Role: student)
```
Generates comprehensive student test cases

### Finance Role
```
/qa-create-scenarios (Role: finance)
```
Generates comprehensive finance test cases

### E2E Role
```
/qa-create-scenarios (Role: e2e)
```
Generates comprehensive cross-role test cases

---

## Alternative: Use Trigger Phrases

If preferred, you can also use these trigger phrases:

```
/create test plan for admin
/plan QA for therapist
/write test scenarios for student
/generate test cases for finance
/generate test cases for e2e
```

---

## Recommended Workflow (Step-by-Step)

### STEP 1: Understand Domain Rules

Run this FIRST to understand business logic and domain rules:
```
/ld-expert-domain
```

**Output:** Domain reference index showing where all business rules are documented in the wiki

---

### STEP 2: Generate Test Cases for Each Role

Run each command ONCE (in any order):

**Command 1:**
```
/qa-create-scenarios (Role: admin)
```
**Output:** 
- qa/admin/test-plan.md
- qa/admin/test-data.md
- Excel rows appended to Admin sheet

**Command 2:**
```
/qa-create-scenarios (Role: finance)
```
**Output:**
- qa/finance/test-plan.md
- qa/finance/test-data.md
- Excel rows appended to Finance sheet

**Command 3:**
```
/qa-create-scenarios (Role: therapist)
```
**Output:**
- qa/therapist/test-plan.md
- qa/therapist/test-data.md
- Excel rows appended to Therapist sheet

**Command 4:**
```
/qa-create-scenarios (Role: student)
```
**Output:**
- qa/student/test-plan.md
- qa/student/test-data.md
- Excel rows appended to Student sheet

**Command 5:**
```
/qa-create-scenarios (Role: e2e)
```
**Output:**
- qa/e2e/test-data.md
- qa/e2e/{workflow-name}.md files
- Excel rows appended to E2E sheet

---

### STEP 3 (Optional): Convert Test Cases to Dusk Browser Tests

After all test cases are in Excel, run:
```
/qa-generate-tests
```

**Output:** PHP Dusk test files in `tests/BrowserQA/` (ready to execute)

---

### STEP 4: Execute Tests

To RUN the tests you generated, use these separate skills:

```
/qa-admin
```
Runs all Admin QA test cases

```
/qa-therapist
```
Runs all Therapist QA test cases

```
/qa-student
```
Runs all Student QA test cases

```
/qa-finance
```
Runs all Finance QA test cases

```
/qa-e2e
```
Runs all E2E test cases

---

## Re-Running / Adding a Single New Feature (Incremental)

The skill is **incremental by default**. You do NOT need to regenerate a whole role when dev adds one feature to the wiki.

**When dev adds a new feature to the wiki:**
1. Make sure dev updated **both** `app/wiki/{role}/menu.md` **and** a PRD file for the feature (the menu is what discovery reads).
2. Re-run the same command, e.g.:
   ```
   /qa-create-scenarios (Role: admin)
   ```
3. The skill runs **Pass 0 (Existing Coverage Diff)** first — it reads the `Feature` column already in `qa/LD-Expert-QA.xlsx`, skips every feature already covered, and generates cases **only for the new feature**, continuing the TC ID sequence (e.g. `TC-A088` onward). No duplicates.

**To target just one feature explicitly (Scoped mode):**
```
/qa-create-scenarios (Role: admin) — generate cases for "School Calendar Events" only
```

**Behavior:**
- If nothing is new, the skill stops and reports *"No new features to generate."* — it writes nothing.
- `test-plan.md` / `test-data.md` get a **new section appended**; existing sections are untouched.
- The final report states the mode, features skipped, new features generated, and the exact TC ID range added.

> **Requirement:** the `Feature` name must stay **stable** across runs — it's the key the diff matches on. Don't rename a feature between runs, or it will be treated as new.

---

## Output Summary

| Role | Excel Sheet | Test Plan | Test Data |
|------|------------|-----------|-----------|
| Admin | Admin | qa/admin/test-plan.md | qa/admin/test-data.md |
| Therapist | Therapist | qa/therapist/test-plan.md | qa/therapist/test-data.md |
| Student | Student | qa/student/test-plan.md | qa/student/test-data.md |
| Finance | Finance | qa/finance/test-plan.md | qa/finance/test-data.md |
| E2E | E2E | qa/e2e/test-data.md | qa/e2e/{workflow-name}.md |

---

## Important Notes

- **One role per command** — Run the skill separately for each role
- **Run ld-expert-domain FIRST** — Understand domain rules before generating test cases
- **Excel file required** — Must have `qa/LD-Expert-QA.xlsx` with sheets: Admin, Therapist, Student, Finance, E2E
- **Wiki files required** — Must exist in `app/wiki/{role}/*.md`
- **Generated files** — test-plan.md, test-data.md, and Excel rows are appended to existing files

---

## For More Details

See `.claude/skills/qa-create-scenarios/SKILL.md` for:
- Full algorithm explanation (5-pass methodology)
- Coverage rules and test categorization
- How to write test cases (Valid/Invalid/Edge)
- Domain rules and best practices
