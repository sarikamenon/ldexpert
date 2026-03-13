# TutorBird Student Import - Implementation Plan

## Overview

Add TutorBird as a new student import type, following the existing NOVA/RSM import architecture. TutorBird CSV files have a significantly different structure from NOVA/RSM, with different column names, missing fields that need defaults, and up to 4 parent contacts.

## Decisions (Confirmed)

| Decision | Answer |
|----------|--------|
| School | Hardcode default `school_name` = `"NR School 01"` in config defaults. The school must exist with this `external_emr_name`. |
| Student Email | Use `Parent Contact 1 Email` as student email (via `field_sources`) |
| Parent Contacts | Import only **Parent Contact 1**. Combine First + Last Name (like RSM `combine` transformation) |
| Billing/Tutor/SSA | **Not now** — student import only |
| Missing fields (timezone, gender, grade) | Use config defaults: `timezone = "America/Chicago"`, `gender = "Male"`, `grade_level = "1"` |
| Duplicate emails | **No special handling** — if siblings share the same parent email, the 2nd import will show a validation error (existing behavior). This is acceptable for now. |
| Address parsing | **Not now** — defer custom address parser to a later task |

## CSV Column Mapping

### TutorBird CSV Headers → Internal Fields

| TutorBird CSV Column | Internal Field | Notes |
|----------------------|---------------|-------|
| `First Name` | `first_name` | Required |
| `Last Name` | `last_name` | Required |
| `TutorBird Student ID` | `id_number` | Required (e.g., `sdt_qsTnJ6`) |
| `TutorBird Family ID` | _(unmapped)_ | Not used for now |
| `Adult Student` | _(unmapped)_ | Not used |
| `Status` | _(unmapped)_ | Could filter Active-only later; not used in student creation |
| `Email` | `email` | Often empty; `field_sources` pulls from parent email |
| `Mobile Phone` | _(unmapped)_ | Student mobile, rarely populated |
| `Birthday` | `date_of_birth` | Optional |
| `Address` | `address` | Multi-line freeform; no parsing for now |
| `Gender` | `gender` | Rarely populated; default = `"Male"` |
| `Note` | _(unmapped)_ | Consultation notes; not imported |
| `Parent Contact 1 Last Name` | `parent_guardian_last_name` | Combined into `parent_guardian_name` |
| `Parent Contact 1 First Name` | `parent_guardian_first_name` | Combined into `parent_guardian_name` |
| `Parent Contact 1 Email` | `parent_guardian_email` | Also used as student email fallback |
| `Parent Contact 1 Mobile Phone` | `parent_guardian_phone` | Primary phone |
| `Parent Contact 1 Address` | _(unmapped)_ | Not used for now |
| `Parent Contact 1 Home Phone` | _(unmapped)_ | Not used |
| `Parent Contact 1 Work Phone` | _(unmapped)_ | Not used |
| `Parent Contact 1 Note` | _(unmapped)_ | Not used |

**Unmapped columns (intentionally skipped):**
- `Texting Allowed`, `Parent` (display name), `Date Started`, `Inactive Date`
- `Subject`, `Level`, `School`, `Base Duration`, `Base Billing Mode`, `Rate`, `Make-up Credits`
- `Tutor`, `Group Tags`, `Referrer`, `Last Lesson`, `Next Lesson`
- `Parent Contact 2/3/4 *` (all columns)

### Config Template

```php
'TUTORBIRD' => [
    'required_columns' => [
        'First Name',
        'Last Name',
        'TutorBird Student ID',
    ],
    'optional_columns' => [
        'Email',
        'Birthday',
        'Address',
        'Gender',
        'Mobile Phone',
        'Parent Contact 1 Last Name',
        'Parent Contact 1 First Name',
        'Parent Contact 1 Email',
        'Parent Contact 1 Mobile Phone',
    ],
    'column_mapping' => [
        'First Name' => 'first_name',
        'Last Name' => 'last_name',
        'TutorBird Student ID' => 'id_number',
        'Email' => 'email',
        'Birthday' => 'date_of_birth',
        'Address' => 'address',
        'Gender' => 'gender',
        'Mobile Phone' => 'student_mobile_phone',
        'Parent Contact 1 Last Name' => 'parent_guardian_last_name',
        'Parent Contact 1 First Name' => 'parent_guardian_first_name',
        'Parent Contact 1 Email' => 'parent_guardian_email',
        'Parent Contact 1 Mobile Phone' => 'parent_guardian_phone',
    ],
    'field_sources' => [
        'email' => 'parent_guardian_email',
    ],
    'transformations' => [
        [
            'type' => 'combine',
            'target' => 'parent_guardian_name',
            'sources' => ['parent_guardian_first_name', 'parent_guardian_last_name'],
            'separator' => ' ',
        ],
    ],
    'defaults' => [
        'school_name' => 'NR School 01',
        'timezone' => 'America/Chicago',
        'gender' => 'Male',
        'grade_level' => '1',
    ],
    'context_sources' => [
        'state' => 'school.state_code',
        'city' => 'school.city',
        'zip_code' => 'school.zip_code',
    ],
],
```

**Key behaviors:**
- `field_sources.email = parent_guardian_email` — if student `email` is empty, use parent email
- `defaults.school_name = "NR School 01"` — all TutorBird students assigned to this school
- `defaults.timezone = "America/Chicago"`, `gender = "Male"`, `grade_level = "1"` — fill in when CSV value is empty
- `context_sources` — pull `state`, `city`, `zip_code` from the school record (since CSV has no structured address)

## Implementation Steps

### Step 1: Add TUTORBIRD enum case

**Files to modify:**
- `app/Enums/StudentImportType.php` — add `case TUTORBIRD = 'TUTORBIRD';`

No change to `SSAImportType` for now (SSA import not in scope).

### Step 2: Add TutorBird template to config

**Files to modify:**
- `config/student-import.php` — add `'TUTORBIRD' => [...]` template (as shown above)

### Step 3: Ensure school "NR School 01" exists

**Action:** Verify or create a school with `external_emr_name = 'NR School 01'`. This could be:
- A seeder entry, OR
- Manual creation via the admin UI, OR
- A migration that inserts the record

**Recommendation:** Add a note/check in the import service that gives a clear error if the school doesn't exist, which already happens via `lookupSchoolByExternalEmrName()`.

### Step 4: Handle validation relaxation for TutorBird

The current `validateRow()` method requires: `email`, `gender`, `grade_level`, `timezone`, `city`, `state`, `zip_code`. With defaults and context_sources, these will be populated before validation runs. **No validation changes needed** — the existing defaults/context_sources pipeline fills them in.

However, verify the processing order in `processRow()`:
1. `mapColumns()` — maps CSV columns
2. `lookupSchoolByExternalEmrName()` — finds school (uses `defaults.school_name`)
3. `applyTemplateTransformations()` — applies field_sources, transformations, defaults, context_sources
4. `normalizePhone()` / `resolveTimezone()` — normalizations
5. `validateRow()` — validates (all defaults should be in place by now)

**Potential issue:** The `school_name` lookup happens BEFORE `applyTemplateTransformations()`. Currently, `school_name` comes from `mappedData['school_name']` which won't exist for TutorBird (no CSV column maps to it). The `defaults.school_name` is applied in step 3, but the lookup is in step 2.

**Fix needed:** Modify `processRow()` to apply defaults for `school_name` before the school lookup. Options:
- Apply defaults early (before school lookup) — simplest
- Or check for a template-level `default_school_name` config before the lookup

**Recommended approach:** In `processRow()`, after `mapColumns()`, check if `school_name` is empty and if the template has a `defaults.school_name`, apply it before the school lookup. This is a small change in `StudentImportService::processRow()`.

### Step 5: Handle phone normalization for TutorBird format

TutorBird phones come in various formats:
- `407-538-2859` (already normalized)
- `5123008985` (10 digits, no formatting)
- `(801) 702-4233` (parenthesized area code)
- `210-845-4903` (already normalized)

The existing `normalizePhone()` strips non-digits and reformats 10-digit numbers as `XXX-XXX-XXXX`. **No changes needed** — it already handles all these formats.

### Step 6: Update UI

**Files to modify:**
- `resources/views/admin/students/import.blade.php` — no changes needed (it already loops `$importTypes` from the enum)
- `resources/js/pages/admin-students-import.js` — verify it handles the new type in the dropdown (it reads from `#templatesData` JSON which is auto-generated from config)

The UI should work automatically since it iterates over `StudentImportType::cases()`.

### Step 7: Template download

The controller's `downloadTemplate()` method generates a CSV template from the config's `required_columns` + `optional_columns`. This will automatically work for TUTORBIRD.

### Step 8: Tests

**New tests needed:**

1. **Unit: Config validation** — verify TUTORBIRD template has all required keys
2. **Unit: Column mapping** — test that TutorBird CSV columns map correctly
3. **Unit: Field sources** — test email fallback from parent email
4. **Unit: Transformations** — test parent name combine
5. **Unit: Defaults** — test school_name, timezone, gender, grade_level defaults
6. **Feature: Import flow** — test full import with TutorBird CSV
7. **Feature: School lookup with defaults** — test that default school_name is used
8. **Feature: Duplicate handling** — test duplicate detection with TutorBird IDs
9. **Feature: Missing parent email** — test what happens when no parent email exists (validation error expected)

## File Change Summary

| File | Change |
|------|--------|
| `app/Enums/StudentImportType.php` | Add `case TUTORBIRD = 'TUTORBIRD'` |
| `config/student-import.php` | Add TUTORBIRD template with column_mapping, field_sources, transformations, defaults, context_sources |
| `app/Domain/Student/Services/StudentImportService.php` | Apply `defaults.school_name` before school lookup in `processRow()` |
| Tests (new) | Unit + Feature tests for TutorBird import flow |

## Out of Scope (Future Work)

- Custom address parser to extract city/state/zip from freeform address fields
- Import of Parent Contact 2/3/4
- SSA import from TutorBird billing data (Rate, Duration, Tutor)
- Filtering by `Status` column (Active/Inactive) during import
- Importing `Note` field (consultation notes)
- Making `school_name` truly optional (instead of hardcoded default)
- TutorBird Family ID grouping (linking siblings)

## Risks & Edge Cases

1. **No parent email + no student email** — row will fail validation (email required). This is expected.
2. **Duplicate parent emails across siblings** — multiple students may share the same parent email. The current duplicate check on email (system-wide) will flag the 2nd student as duplicate and show a validation error. **This is accepted behavior for now** — no special handling needed.
3. **Multi-line addresses** — the CSV contains newlines within quoted fields (e.g., `"13134 Voelcker Ranch Dr\nSan Antonio, TX 78231"`). The CSV parser handles this correctly since it respects quoted fields.
4. **Semicolon-delimited multi-values** — fields like `Rate` and `Tutor` contain `; ` separated values. Since we're not importing these fields, this is not a concern now.
5. **School "NR School 01" must exist** — if it doesn't, every row will fail with "School not found". Admin must create it first.
