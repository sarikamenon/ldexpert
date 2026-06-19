---
name: qa-add-test-cases
description: Add test cases directly to app/qa/LD-Expert-QA.xlsx in the correct format without requiring wiki PRDs. Use when test case specifications are provided and you need to populate Excel before running qa-generate-tests. Triggers on "add test cases to excel", "write test cases to sheet", "populate excel with tests".
---

# QA Test Case Writer — Direct to Excel

Add test cases to `app/qa/LD-Expert-QA.xlsx` in the official format when wiki PRDs are not available. This enables the **Option B workflow** (direct Excel without wiki).

---

## When to Use

Use this skill when:
- ✅ User provides user stories and asks to generate test cases
- ✅ Test case specifications are provided (in any format)
- ✅ Wiki PRD update is NOT happening
- ✅ Need to populate Excel quickly before running `/qa-generate-tests`
- ✅ Want to follow the official Excel format strictly
- ✅ Adding test cases to an existing Excel file (continuing numbering)

Do NOT use when:
- ❌ Wiki PRD should be created first (use `/qa-create-scenarios` instead)
- ❌ Already have properly formatted Excel rows ready

---

## Input Format

Test cases can be provided in any of these formats:

### Format 1: Markdown List (Recommended)

```markdown
## TC-Dashboard-001
**Feature:** Dashboard
**Condition:** Valid
**Test Name:** Admin can view all overview cards on page load
**Priority:** P1
**Preconditions:** Admin logged in, database has seed data
**Steps:**
1. Navigate to /admin/dashboard
2. Verify all 4 cards load with correct counts
3. Verify Active/Inactive/New This Month breakdowns display

**Expected Result:** All 4 cards visible with correct data

---

## TC-Dashboard-002
**Feature:** Dashboard
**Condition:** Valid
**Test Name:** Overview cards display accurate breakdown
**Priority:** P1
**Preconditions:** Admin logged in
**Steps:**
1. View each card breakdown (Active, Inactive, New This Month)
2. Verify math: 27+1=28, 38+13=51, 120+12=132

**Expected Result:** All breakdowns accurate and add up to totals
```

### Format 2: Structured List

```
TC ID: TC-Dashboard-003
Feature: Dashboard - Navigation
Condition: Valid
Test Name: Admin can click Schools/Families card and navigate to schools list
Priority: P1
Preconditions: Admin on dashboard
Steps: Click Schools/Families card | Verify page navigates to /admin/schools
Expected Result: Navigate to schools list, data consistency verified

---

TC ID: TC-Dashboard-004
Feature: Dashboard - Navigation
Condition: Valid
Test Name: Admin can click Therapist Capacity card
Priority: P1
Preconditions: Admin on dashboard
Steps: Click Therapist Capacity card | Verify page navigates to /admin/therapists
Expected Result: Navigate to therapist list
```

### Format 3: Copy-Paste from Specification

Just paste raw test specifications and the skill will parse them.

---

## Excel File Structure

### Sheet Name
`Test Cases` (or role-specific: `Admin`, `Therapist`, `Student`, `E2E`)

### Column Definitions (in exact order)

| Column | Header | Description |
|--------|--------|-------------|
| A | TC ID | Unique test case identifier. Format: TC-[Prefix]###  (e.g., TC-A001, TC-T042, TC-S015) |
| B | Feature | The feature or module being tested (e.g., Dashboard, Login, Reports) |
| C | Condition | The specific condition or scenario being tested (e.g., Valid credentials, Empty field, Unauthorized access) |
| D | Test Name | A short, descriptive name for the test case |
| E | Priority | One of: P1 (Critical), P2 (Important), P3 (Secondary), P4 (Optional) |
| F | Preconditions | Any setup or state required before running the test |
| G | Step 1 | First test step |
| H | Step 2 | Second test step (leave blank if not needed) |
| I | Step 3 | Third test step (leave blank if not needed) |
| J | Step 4 | Fourth test step (leave blank if not needed) |
| K | Step 5 | Fifth test step (leave blank if not needed) |
| L | Expected Result | What the system should do if the test passes |
| M | Status | One of: Pass, Fail, Blocked, Not Run. **Default: Not Run** |
| N | Actual Result | Leave blank by default — filled during test execution |
| O | Notes | Any additional context, edge cases, or observations |
| P | Dusk Test File | Reference to the automated test file if applicable (leave blank if none) |

---

## TC ID Format & Numbering Rules

### ID Prefixes by Role/Type

| Role / Type | Prefix | Example | Note |
|-------------|--------|---------|------|
| Admin (core + dashboard) | TC-A | TC-A001 | Includes Dashboard, Schools, Students, Therapists |
| Therapist (core + dashboard) | TC-T | TC-T001 | Includes Dashboard, Schedule, Session Logs |
| Student (core + dashboard) | TC-S | TC-S001 | Includes Dashboard, Schedule, Messages |
| Finance | TC-F | TC-F001 | Invoices, Payments, Bills, Ledger |
| End-to-End | TC-E | TC-E001 | Cross-role workflows |

### Numbering Rules
- IDs are zero-padded to 3 digits: `001`, `002`, ... `099`, `100`
- Each prefix has its **own independent sequence** (TC-A and TC-T numbering do not affect each other)
- Dashboard is part of each role's functionality — use role-specific prefix (TC-A for Admin Dashboard, TC-T for Therapist Dashboard, etc.)
- **When adding test cases to an existing file**, always read the last TC ID for each prefix in the file and continue from the next number
  - Example: if the last Admin ID in the file is `TC-A007`, the next one must be `TC-A008`
- Never reuse or skip IDs
- If a user story covers multiple roles, generate separate test cases under the appropriate prefix for each role

---

## Header Row Formatting
- **Row 1** is the header row
- **Font**: Arial, size 11, bold
- **Background**: Dark blue (`#1F3864`)
- **Text color**: White
- **Alignment**: Center, vertical center
- **Freeze panes** at row 2 (header row stays visible when scrolling)
- **Auto-fit** column widths after data is written

---

## Data Row Formatting
- **Alternate row shading**: White (`#FFFFFF`) and light blue-gray (`#EEF2F7`) for readability
- **Font**: Arial, size 10
- **Alignment**: Vertical center, wrap text enabled
- **Row height**: 30 pixels minimum

### Status Column (M) — Color Coding
| Status | Background | Text Color |
|--------|------------|------------|
| Pass | Light green (`#C6EFCE`) | Dark green (`#276221`) |
| Fail | Light red (`#FFC7CE`) | Dark red (`#9C0006`) |
| Blocked | Light orange (`#FFEB9C`) | Dark orange (`#9C6500`) |
| Not Run | Light gray (`#F2F2F2`) | Gray (`#595959`) |

### Priority Column (E) — Color Coding
| Priority | Background | Text Color |
|----------|------------|------------|
| P1 (Critical) | Light red (`#FFC7CE`) | Dark red (`#9C0006`) |
| P2 (Important) | Light orange (`#FFEB9C`) | Dark orange (`#9C6500`) |
| P3 (Secondary) | Light blue (`#DDEBF7`) | Dark blue (`#1F3864`) |
| P4 (Optional) | Light gray (`#F2F2F2`) | Gray (`#595959`) |

---

## Output Format

The skill will write to the correct 16-column Excel structure following the formatting rules above:

| Col | Header | Auto-filled | User provides |
|-----|--------|-------------|---|
| A | TC ID | ✅ (Next sequential) | From input |
| B | Feature | ❌ | From input |
| C | Condition | ❌ | From input (Valid/Invalid/Edge) |
| D | Test Name | ❌ | From input |
| E | Priority | ❌ | From input (P1/P2/P3/P4) |
| F | Preconditions | ❌ | From input |
| G-K | Step 1-5 | ❌ | From input (split if multiple steps) |
| L | Expected Result | ❌ | From input |
| M | Status | ✅ | "Not Run" (with gray background) |
| N | Actual Result | ✅ | (blank) |
| O | Notes | ✅ | (blank) |
| P | Dusk Test File | ✅ | (blank — filled by qa-generate-tests) |

---

## Validation Rules

The skill will validate:

1. **TC ID Format**
   - Must match pattern: `TC-[A-Z]-\d{3}`
   - Examples: `TC-A-001`, `TC-Dashboard-001` (for new tests)
   - Auto-generates if not provided

2. **Condition Values** (Case-insensitive, auto-corrected)
   - `Valid` (also accepts: Positive, Valid, Happy Path)
   - `Invalid` (also accepts: Negative, Invalid, Bad Input)
   - `Edge` (also accepts: Edge, Edge Case, Boundary)

3. **Priority Values** (Case-insensitive, auto-corrected)
   - `P1` (Critical)
   - `P2` (Normal)
   - `P3` (Minor)

4. **Step Count**
   - Accepts 1-5 steps
   - Auto-splits if provided as comma-separated or numbered list
   - Distributes across Step 1-5 columns

5. **Required Fields**
   - Feature: Required
   - Condition: Required
   - Test Name: Required
   - Priority: Required
   - Expected Result: Required
   - At least one Step: Required

---

## Excel Sheet Selection

The skill will determine the correct sheet based on TC ID prefix:

| Prefix | Sheet | Example |
|--------|-------|---------|
| TC-A | Admin | TC-A001 |
| TC-T | Therapist | TC-T001 |
| TC-S | Student | TC-S001 |
| TC-F | Finance | TC-F001 |
| TC-E | E2E | TC-E001 |
| TC-[Other] | Specify sheet | Must specify target sheet in input |

---

## Usage Example

### Input to the Skill

```markdown
Add these test cases to Admin sheet:

## TC-Dashboard-001
Feature: Dashboard
Condition: Valid
Test Name: Admin can view all overview cards on page load
Priority: P1
Preconditions: Admin logged in, database has seed data
Steps:
1. Navigate to /admin/dashboard
2. Verify all 4 cards load
3. Check counts display correctly
Expected Result: All 4 cards visible with correct data

## TC-Dashboard-002
Feature: Dashboard
Condition: Valid
Test Name: Overview cards display accurate breakdown
Priority: P1
Preconditions: Admin logged in
Steps:
1. View each card breakdown
2. Verify math adds up
Expected Result: All breakdowns accurate
```

### Output

✅ **Added 2 test cases to app/qa/LD-Expert-QA.xlsx (Admin sheet)**
- TC-Dashboard-001: Admin can view all overview cards on page load
- TC-Dashboard-002: Overview cards display accurate breakdown

**Next steps:**
1. Run `/qa-generate-tests` to generate PHP Dusk test files
2. Run `/qa-admin` to execute the tests

---

## Validation Output

If validation errors occur, the skill will report:

```
❌ Validation Errors Found:

1. TC-Dashboard-001: Invalid Condition "Positive" → Changed to "Valid"
2. TC-Dashboard-002: Step count 2 exceeds max 5 → Will split as provided
3. TC-Dashboard-003: Missing Expected Result → Cannot add to Excel

✅ Added 2 test cases (1 skipped due to missing fields)
```

---

## Integration with qa-generate-tests

After using this skill:

```bash
# 1. Add test cases to Excel (this skill)
/qa-add-test-cases
# Input: Test case specifications

# 2. Generate PHP Dusk test files (next step)
/qa-generate-tests
# Reads: app/qa/LD-Expert-QA.xlsx
# Outputs: app/tests/BrowserQA/Admin/QaAdminDashboardBrowserTest.php

# 3. Run tests (final step)
/qa-admin
# Executes: php artisan dusk tests/BrowserQA/Admin/
```

---

## Notes

- **Backward compatible**: Works with existing test cases in Excel
- **Auto-incrementing TC IDs**: Finds last TC ID and increments correctly
- **Format validation**: Ensures all test cases follow official format
- **No wiki required**: Pure Option B workflow
- **Repeatable**: Can be run multiple times to add more test cases
- **Safe**: Creates backup of Excel before writing (if using xlsx skill)

---

## See Also

- [`qa-generate-tests`](../qa-generate-tests/SKILL.md) — Generate PHP Dusk test files from Excel
- [`qa-create-scenarios`](../qa-create-scenarios/SKILL.md) — Generate test cases from wiki PRDs
- [`app/qa/SETUP.md`](../../../app/qa/SETUP.md) — Excel access setup
- [`app/qa/BROWSER_QA_FLOW.md`](../../../app/qa/BROWSER_QA_FLOW.md) — Complete QA workflow
