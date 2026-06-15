# How to Add New Test Cases (For Developers)

**TL;DR:** Edit Excel file → Run skill → Done. No wiki updates needed.

---

## ⚡ Quick Process (2 minutes)

### **Step 1: Add Test Case to Excel**

**File:** `qa/LD-Expert-QA.xlsx`

Open the spreadsheet and add a new row:

| TC-ID | Title | Module | Steps | Expected Result | Role | Priority |
|-------|-------|--------|-------|-----------------|------|----------|
| TC-A013 | Verify new feature works | New Feature | 1. Do X, 2. Do Y, 3. Click Z | Feature appears on screen | Admin | P1 |

**That's it.** Just add a row to Excel.

---

### **Step 2: Generate Test Files (1 minute)**

```bash
/qa-generate-tests
```

**Output:**
```
✓ Generated: tests/BrowserQA/Admin/NewFeatureTest.php
✓ Total test files: 13
```

---

### **Step 3: Run Tests**

```bash
docker compose exec -T app php artisan dusk tests/BrowserQA/
```

**Done!** Tests run automatically. No manual code writing.

---

## 📝 How to Write a Good Test Case

Use this template for the Excel row:

### **Required Fields:**

| Field | Example | Rules |
|-------|---------|-------|
| **TC-ID** | TC-A013 | Unique ID: TC-[Role letter]-[number] |
| **Title** | Admin can create report | Clear, describes what's tested |
| **Module** | Reporting | Group related tests together |
| **Steps** | 1. Click Reports<br/>2. Click Create<br/>3. Fill form<br/>4. Click Save | Numbered list, clear instructions |
| **Expected Result** | Report created and appears in list | What should happen after steps |
| **Role** | Admin<br/>Therapist<br/>E2E | Who performs this action |
| **Priority** | P1<br/>P2 | P1=Critical, P2=Important |

---

## 📚 Examples

### **Example 1: Admin Feature**
```
TC-ID: TC-A013
Title: Admin can create new report
Module: Reporting
Steps:
  1. Login as admin
  2. Click "Reports" in menu
  3. Click "Create Report"
  4. Fill "Report Name": "Monthly Summary"
  5. Click "Generate"
Expected Result:
  Report created
  Appears in reports list
  Shows: "Monthly Summary, June 2026"
Role: Admin
Priority: P1
```

### **Example 2: Therapist Feature**
```
TC-ID: TC-T006
Title: Therapist can export session data
Module: Data Export
Steps:
  1. Login as therapist
  2. Click "Sessions"
  3. Click "Export"
  4. Select format: "CSV"
  5. Click "Download"
Expected Result:
  CSV file downloaded
  Contains all session data
  File named: "sessions-[date].csv"
Role: Therapist
Priority: P2
```

### **Example 3: End-to-End Feature**
```
TC-ID: TC-E002
Title: Complete reporting workflow
Module: E2E
Steps:
  1. Admin creates report (Steps from TC-A013)
  2. Therapist verifies data (Steps from TC-T006)
  3. Admin exports results
Expected Result:
  All steps successful
  Data consistent across system
Role: E2E
Priority: P1
```

---

## 🎯 When to Add Tests

**Add test cases for:**
- ✅ New features
- ✅ New workflows
- ✅ Bug fixes (to prevent regression)
- ✅ Changes to existing features

**You don't need to:**
- ❌ Update wikis
- ❌ Update markdown files
- ❌ Write Python code
- ❌ Write PHP code (auto-generated)
- ❌ Update documentation (Excel IS the docs)

---

## 🔄 Full Workflow (One Time)

```
Feature Development
    ↓
1. Developer adds row to Excel
2. Run /qa-generate-tests
3. Run php artisan dusk
4. Tests pass ✓ or fail ✗
5. If fail: Fix code, re-run tests
6. If pass: Feature ready ✓

Next time someone adds a feature:
Repeat steps 1-6 only
(Excel file stays updated automatically)
```

---

## ❓ FAQ

**Q: Do I need to update wiki.md?**  
A: No. The Excel file IS your test documentation.

**Q: Do I need to write PHP test code?**  
A: No. `/qa-generate-tests` writes it automatically.

**Q: What if I add a test case to Excel but forget to run /qa-generate-tests?**  
A: The test won't be generated. Always run the skill after editing Excel.

**Q: Can multiple people edit the Excel file?**  
A: Yes, but make sure you don't have conflicts. One person at a time is safest.

**Q: What if /qa-generate-tests fails?**  
A: Check the Excel format. All required columns must be filled.

**Q: How do I know if my test case is good?**  
A: Run `/qa-generate-tests` and then `php artisan dusk`. If the generated code makes sense and tests work, you're good.

---

## 🚀 Checklist for Adding New Tests

Before you add a test case:
- [ ] Feature is developed and working
- [ ] You know what steps users take
- [ ] You know what the expected result is
- [ ] You know which role performs this action

When adding to Excel:
- [ ] TC-ID is unique (not used before)
- [ ] Title clearly describes the test
- [ ] Steps are numbered and clear
- [ ] Expected Result is specific
- [ ] Role is correct (Admin/Therapist/E2E)
- [ ] Priority is set (P1 or P2)

After saving Excel:
- [ ] Run `/qa-generate-tests`
- [ ] Verify new test file created
- [ ] Run `php artisan dusk tests/BrowserQA/`
- [ ] Verify test passes

---

## 📁 Files You Touch

**Only this file:**
```
qa/LD-Expert-QA.xlsx
└─ Add test case rows here
```

**You DON'T touch:**
```
tests/BrowserQA/     ← Auto-generated, don't edit
qa/docs/*.md         ← Reference only, don't update
wiki.md              ← Don't update
README.md            ← Don't update
```

---

## 💡 Key Insight

**Excel file = Single Source of Truth**

```
NOT THIS:
├─ Wiki (outdated)
├─ README (outdated)
├─ Test code (manual)
└─ Documentation (scattered)

BUT THIS:
└─ Excel file (always current, auto-generates tests)
```

When you add a test to Excel:
- The Excel file documents it ✅
- `/qa-generate-tests` writes the PHP code ✅
- `php artisan dusk` runs the test ✅
- No manual documentation updates needed ✅

---

## 🎯 Bottom Line

```
Old way (problematic):
Add feature → Update wiki → Write test code → Run tests

New way (automated):
Add feature → Add row to Excel → Run /qa-generate-tests → Run tests

Developers don't maintain wikis.
Developers just update Excel.
System automates the rest.
```

---

**That's all you need to know. Questions? See qa/docs/README.md**

