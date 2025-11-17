# Remove Old Student CRUD Implementation Plan

## Context

The original implementation allowed therapists to create students directly. The new workflow involves:

1. Students are created via SSA (Student Service Agreement) process
2. Students are then assigned to therapists
3. Therapists process students for scheduling and session notes

This plan removes all therapist-facing student creation/management features while preserving:

- Student models and database structure (needed for SSA workflow)
- User relationships (therapist-student assignments)
- Student authentication (for future student portal)

## What to Remove

### 1. HTTP Layer - Controllers & Routes

**Files to Delete:**

- `app/app/Http/Controllers/Therapist/StudentController.php` - Complete CRUD controller

**Routes to Remove:**
From `app/routes/therapist.php`:

```php
Route::get('students', [StudentController::class, 'index'])->name('students.index');
Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
Route::post('students', [StudentController::class, 'store'])->name('students.store');
Route::get('students/{user}', [StudentController::class, 'show'])->name('students.show');
Route::get('students/{user}/edit', [StudentController::class, 'edit'])->name('students.edit');
Route::patch('students/{user}', [StudentController::class, 'update'])->name('students.update');
Route::patch('students/{user}/status/activate', [StudentController::class, 'activate'])->name('students.activate');
Route::patch('students/{user}/status/deactivate', [StudentController::class, 'deactivate'])->name('students.deactivate');
```

### 2. HTTP Layer - Request Validation

**Files to Delete:**

- `app/app/Http/Requests/Therapist/StoreStudentRequest.php`
- `app/app/Http/Requests/Therapist/UpdateStudentRequest.php`

### 3. Policy Layer

**Files to Delete:**

- `app/app/Policies/StudentProfilePolicy.php` - Authorization rules for therapist student management

**Provider Updates:**
From `app/app/Providers/AppServiceProvider.php`, remove policy binding:

```php
Gate::policy(StudentProfile::class, StudentProfilePolicy::class);
```

### 4. DTOs (Data Transfer Objects)

**Files to Delete:**

- `app/app/DTOs/CreateStudentProfileDTO.php`
- `app/app/DTOs/UpdateStudentProfileDTO.php`
- `app/app/Domain/Therapist/DTOs/CreateStudentDTO.php` (if exists)

**Note:** Keep any admin-facing student DTOs if they exist for the future admin student management.

### 5. View Layer - Blade Templates

**Directory to Delete:**

- `app/resources/views/therapist/students/` (entire directory)
  - `create.blade.php`
  - `edit.blade.php`
  - `index.blade.php`
  - `show.blade.php`

### 6. JavaScript Assets

**Files to Delete:**

- `app/resources/js/pages/students-index.js` (DataTables configuration for therapist student list)

**Vite Config Update:**
Remove from `app/vite.config.js` input array:

```javascript
"resources/js/pages/students-index.js";
```

### 7. Tests

**Feature Tests to Delete:**

- `app/tests/Feature/TherapistCreateStudentTest.php`
- `app/tests/Feature/TherapistStudentsIndexTest.php`
- `app/tests/Feature/TherapistStudentsShowEditUpdateTest.php`
- `app/tests/Feature/TherapistStudentsStoreAssignTest.php`
- `app/tests/Feature/StudentProfileFieldsTest.php`

**Browser Tests to Delete:**

- `app/tests/Browser/TherapistStudentsBrowserTest.php`

**Unit Tests to Delete:**

- `app/tests/Unit/StudentProfileDTOTest.php`

### 8. Navigation & Menu

**Files to Update:**

`app/config/navigation.php` - Remove therapist student management menu items:

```php
[
    'label' => 'Students',
    'route' => 'therapist.students.index',
    'icon' => 'user-group',
    'roles' => ['therapist'],
]
```

### 9. Documentation

**Files to Update:**

`app/wiki/therapist/menu.md` - Remove student management references
`app/wiki/therapist/workspace.md` - Update to reflect new workflow (SSA-based)

**Optional Cleanup:**

- Consider archiving `app/wiki/admin/students.md` if admin student management isn't implemented yet
- Update `app/wiki/README.md` to reflect current architecture

### 10. Factory Updates

**Files to Review (DO NOT DELETE):**

- `app/database/factories/StudentProfileFactory.php` - Keep for SSA testing
- `app/database/factories/UserFactory.php` - Keep `student()` state for testing

## What to Preserve

### Models & Database

**KEEP THESE FILES:**

- `app/app/Models/StudentProfile.php` - Core model
- `app/app/Models/User.php` - User model with student relationships
- `app/app/Models/TherapistStudent.php` - Pivot model for assignments
- All student-related migrations:
  - `2025_11_05_062704_create_student_profiles_table.php`
  - `2025_11_05_062711_create_therapist_student_table.php`
  - `2025_11_05_071500_add_soft_deletes_to_core_tables.php`
  - `2025_11_10_094203_add_detailed_fields_to_student_profiles_table.php`
  - `2025_11_10_102455_remove_phone_and_emergency_contact_from_student_profiles_table.php`
  - `2025_11_10_120000_remove_full_name_from_student_profiles_table.php`

### User Model Relationships

**KEEP THESE METHODS in `app/app/Models/User.php`:**

```php
public function studentProfile(): HasOne
public function students(): BelongsToMany  // For therapist -> students
public function therapists(): BelongsToMany  // For student -> therapists
public function children(): HasMany  // For parent -> students
```

### Authentication & Authorization

**KEEP:**

- Student role in `app/app/Enums/Role.php`
- Student status handling in `app/app/Enums/UserStatus.php`
- Login functionality for students (for future portal)

## Implementation Steps

### Phase 1: Remove Files

1. Delete controller: `StudentController.php`
2. Delete requests: `StoreStudentRequest.php`, `UpdateStudentRequest.php`
3. Delete policy: `StudentProfilePolicy.php`
4. Delete DTOs: `CreateStudentProfileDTO.php`, `UpdateStudentProfileDTO.php`
5. Delete views directory: `therapist/students/`
6. Delete JavaScript: `students-index.js`
7. Delete tests: All therapist student tests

### Phase 2: Update Configurations

1. Remove routes from `routes/therapist.php`
2. Remove policy binding from `AppServiceProvider.php`
3. Remove JavaScript entry from `vite.config.js`
4. Update `config/navigation.php` to remove student menu items

### Phase 3: Update Documentation

1. Update `wiki/therapist/workspace.md` to describe new SSA workflow
2. Update `wiki/therapist/menu.md` to remove student management
3. Add note in `wiki/README.md` about SSA-based student workflow
4. Create/update SSA workflow documentation

### Phase 4: Testing & Verification

1. Run `php artisan route:list` - verify no therapist.students.\* routes
2. Run `npm run build` - verify Vite build succeeds
3. Run existing tests - verify no broken dependencies
4. Manual testing:
   - Login as therapist - verify no student management menu
   - Verify therapist dashboard loads without errors
   - Verify student assignments still work (if implemented)

### Phase 5: Cleanup Services (Optional)

Review and potentially simplify:

- `app/app/Domain/User/Services/UserService.php` - Remove student-specific creation logic if only used by old flow
- `app/app/Infrastructure/Repositories/EloquentUserRepository.php` - Review student query methods

## Post-Removal Verification Checklist

- [ ] No `therapist.students.*` routes exist
- [ ] No orphaned Blade components referencing student forms
- [ ] Navigation menu shows no student management links for therapists
- [ ] All tests pass (PHPUnit)
- [ ] Vite builds successfully without errors
- [ ] No JavaScript errors in browser console on therapist pages
- [ ] Student model and database remain intact for SSA workflow
- [ ] User relationships (therapist-student) are preserved
- [ ] No references to deleted files in codebase (use grep to verify)

## Future SSA Integration Points

After removal, the following will need to be implemented for the new SSA workflow:

1. **Admin SSA Management Module:**

   - Create SSA records
   - Link SSA to schools
   - Assign students to therapists via SSA

2. **Therapist Student View (Read-Only):**

   - List assigned students (via SSA)
   - View student profiles (no edit)
   - Access for scheduling and session notes

3. **Student Portal:**
   - Student authentication
   - View schedule
   - View session notes
   - Progress tracking

## Breaking Changes Notice

**For Development Team:**

- All therapist student management routes are removed
- Therapists can no longer create/edit students directly
- Any bookmarks or direct links to `/therapist/students/*` will 404
- Tests using `TherapistStudentsBrowserTest` or `TherapistCreateStudentTest` must be updated/removed
- Any frontend JavaScript expecting student management endpoints will fail

## Rollback Plan (If Needed)

If this removal needs to be reversed:

1. Checkout from git: `git checkout HEAD~1 -- app/app/Http/Controllers/Therapist/StudentController.php` (and other files)
2. Restore routes from git history
3. Re-run `npm run build`
4. Clear Laravel caches: `php artisan route:clear && php artisan view:clear`

## Notes

- This is a **one-way migration** - the old student CRUD will not be restored
- The SSA workflow is the new source of truth for student management
- Keep all database migrations - they're needed regardless of creation method
- Parent profiles remain intact as they're independent of this change
- The `therapist_student` pivot table is critical for assignments - do not touch
