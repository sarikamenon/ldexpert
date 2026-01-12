# Plan to Fix Pre-Existing Test Failures

## Overview
This plan addresses 37 pre-existing test failures that are unrelated to the recurrence feature implementation. The failures are grouped by category for systematic resolution.

## Issue Categories

### 1. SessionLogStatus Enum Conversion Issues (High Priority)
**Affected Tests:**
- `Tests\Unit\Services\SessionLogServiceTest > submit updates status`
- `Tests\Unit\Services\SessionLogServiceTest > approve update status`
- `Tests\Unit\Services\SessionLogServiceTest > approve throws exception`
- `Tests\Feature\Policies\SessionLogPolicyTest > therapist can update draft`
- `Tests\Feature\Policies\SessionLogPolicyTest > therapist can cancel draft`
- `Tests\Feature\Policies\SessionLogPolicyTest > admin can update any status`
- `Tests\Feature\Policies\SessionLogPolicyTest > admin cannot cancel approved`
- `Tests\Feature\Policies\SessionLogPolicyTest > admin can approve submitted`
- `Tests\Feature\Policies\SessionLogPolicyTest > admin cannot approve non-submitted`
- `Tests\Feature\Policies\SessionLogPolicyTest > therapist can cancel submitted`

**Root Cause:**
- `SessionLog` model has `getStatusAttribute()` accessor that converts string to enum
- Missing `setStatusAttribute()` mutator to convert enum back to string
- `status` field is not in `$casts` array, so Laravel doesn't know how to handle enum conversion

**Solution:**
1. Add `status` to `$casts` array as `SessionLogStatus::class`
2. Add `setStatusAttribute()` mutator to handle enum to string conversion
3. Update `getStatusAttribute()` to handle null values properly
4. Ensure all places that set status use the enum value

**Files to Modify:**
- `app/app/Models/SessionLog.php`

---

### 2. Service Form Validation Issues (High Priority)
**Affected Tests:**
- `Tests\Feature\Admin\ServiceManagementTest > it creates a service`
- `Tests\Feature\Admin\ServiceManagementTest > it updates a service`

**Root Cause:**
- Form has hidden input `<input type="hidden" name="is_group_service" value="0">`
- When checkbox is unchecked, the hidden field should be sent, but validation might be failing
- `CreateServiceDTO::fromArray()` hardcodes `isGroupService` to `false` instead of reading from data

**Solution:**
1. Fix `CreateServiceDTO::fromArray()` to read `is_group_service` from data
2. Fix `UpdateServiceDTO::fromArray()` similarly
3. Ensure form properly sends the hidden field value when checkbox is unchecked
4. Verify validation rules accept boolean values properly

**Files to Modify:**
- `app/app/DTOs/CreateServiceDTO.php`
- `app/app/DTOs/UpdateServiceDTO.php`
- `app/resources/views/admin/services/_form.blade.php` (if needed)

---

### 3. Invoice Management Test Issues (Medium Priority)
**Affected Tests:**
- `Tests\Feature\Admin\InvoiceManagementTest > it verifies snapshot`

**Root Cause:**
- Test expects `$invoice->refresh()` but invoice might be null after creation
- Need to check if invoice is properly created and retrieved

**Solution:**
1. Review test to ensure invoice is properly created
2. Check if invoice creation returns the model instance
3. Verify snapshot logic is working correctly

**Files to Modify:**
- `app/tests/Feature/Admin/InvoiceManagementTest.php`

---

### 4. Session Log Admin View Issues (Medium Priority)
**Affected Tests:**
- `Tests\Feature\Admin\SessionLogAdminTest > admin can view session logs`

**Root Cause:**
- View is missing "Duration" text/column
- Test expects to see "Duration" but it's not in the rendered HTML

**Solution:**
1. Check `app/resources/views/admin/session-logs/index.blade.php`
2. Ensure "Duration" column/header is present
3. Update view to match test expectations or update test if view changed

**Files to Modify:**
- `app/resources/views/admin/session-logs/index.blade.php`
- `app/tests/Feature/Admin/SessionLogAdminTest.php` (if view changed intentionally)

---

### 5. Student Management Test Issues (Medium Priority)
**Affected Tests:**
- `Tests\Feature\Admin\StudentManagementTest > student show page filters therapists by name`

**Root Cause:**
- Test uses `$therapists->contains('name', 'Dr. John')` which might not work with collection
- Need to check how therapists are passed to view and their structure

**Solution:**
1. Review how therapists are passed to the view
2. Fix collection assertion to properly check therapist names
3. Verify therapist data structure matches expectations

**Files to Modify:**
- `app/tests/Feature/Admin/StudentManagementTest.php`

---

### 6. Schedule Pending List Test Issues (Medium Priority)
**Affected Tests:**
- `Tests\Feature\Therapist\SchedulePendingListTest > pending schedule page displays correctly`
- `Tests\Feature\Therapist\SchedulePendingListTest > pending schedule page filters by student`
- `Tests\Feature\Therapist\SchedulePendingListTest > pending schedule page has filter data`

**Root Cause:**
- Test expects "Date & Time" but view might have different text
- Test expects `$response->viewData()` with no arguments but method requires a key
- Filter functionality might have changed

**Solution:**
1. Check actual view text and update test or view to match
2. Fix `viewData()` calls to pass the required key parameter
3. Verify filter data structure matches test expectations

**Files to Modify:**
- `app/tests/Feature/Therapist/SchedulePendingListTest.php`
- `app/resources/views/therapist/schedule/pending.blade.php` (if needed)

---

### 7. Session Log Access Test Issues (Low Priority)
**Affected Tests:**
- `Tests\Feature\Therapist\SessionLogAccessTest > edit page populates form with existing data`

**Root Cause:**
- Test expects `value="247"` for service_id but it's not in the rendered HTML
- Form might not be populating service_id correctly

**Solution:**
1. Check edit form to ensure service_id is properly populated
2. Verify form field name matches what test expects
3. Check if service_id is being passed to view correctly

**Files to Modify:**
- `app/resources/views/therapist/session-logs/edit.blade.php`
- `app/app/Http/Controllers/Therapist/SessionLogController.php` (if needed)

---

### 8. Session Log Index Test Issues (Low Priority)
**Affected Tests:**
- `Tests\Feature\Therapist\SessionLogIndexTest > therapist sees status column`

**Root Cause:**
- Test expects not to see "Approve" button but it's showing
- Authorization logic might be incorrect or view is showing button when it shouldn't

**Solution:**
1. Check authorization logic for approve action
2. Verify view conditionals for showing approve button
3. Ensure therapist cannot see approve button for their own logs

**Files to Modify:**
- `app/resources/views/therapist/session-logs/index.blade.php`
- `app/app/Policies/SessionLogPolicy.php` (if needed)

---

### 9. Session Log Tabs Test Issues (Low Priority)
**Affected Tests:**
- `Tests\Feature\Therapist\SessionLogTabsTest > therapist cannot view other therapist session logs`

**Root Cause:**
- Test expects count of 1 but gets 0
- Filtering logic might be excluding the therapist's own logs
- Query might be incorrect

**Solution:**
1. Review query logic for filtering session logs by therapist
2. Verify test data setup is correct
3. Check if filtering is too restrictive

**Files to Modify:**
- `app/tests/Feature/Therapist/SessionLogTabsTest.php`
- `app/app/Domain/Therapist/Repositories/SessionLogRepositoryInterface.php` (if needed)
- `app/app/Infrastructure/Repositories/EloquentSessionLogRepository.php` (if needed)

---

### 10. Session Log Index Service Test Issues (Low Priority)
**Affected Tests:**
- `Tests\Unit\Services\SessionLogIndexServiceTest > therapist index filters by date range`
- `Tests\Unit\Services\SessionLogIndexServiceTest > index handles unapproved status`

**Root Cause:**
- Date format or filtering logic might be incorrect
- Status handling for unapproved logs might be wrong

**Solution:**
1. Review date filtering logic
2. Check status handling for unapproved logs
3. Verify date format matches expectations

**Files to Modify:**
- `app/tests/Unit/Services/SessionLogIndexServiceTest.php`
- `app/app/Domain/SessionLog/Services/SessionLogIndexService.php` (if needed)

---

## Implementation Order

### Phase 1: Critical Fixes (Do First)
1. **SessionLogStatus Enum Conversion** - Blocks multiple tests
2. **Service Form Validation** - Blocks service management tests

### Phase 2: Medium Priority Fixes
3. **Invoice Management Test**
4. **Session Log Admin View**
5. **Student Management Test**
6. **Schedule Pending List Test**

### Phase 3: Low Priority Fixes
7. **Session Log Access Test**
8. **Session Log Index Test**
9. **Session Log Tabs Test**
10. **Session Log Index Service Test**

---

## Testing Strategy

After each fix:
1. Run the specific test that was failing
2. Verify it passes
3. Run related tests to ensure no regressions
4. Run full test suite for the affected area

Final verification:
```bash
make test
```

---

## Notes

- All fixes should maintain backward compatibility
- Follow existing code patterns and conventions
- Update tests if business logic has legitimately changed
- Document any intentional changes to behavior
