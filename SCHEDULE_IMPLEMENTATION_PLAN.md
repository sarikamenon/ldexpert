# Schedule Table and CRUD Implementation with Batch Numbers

## Overview

Implement complete schedule management functionality allowing therapists to create, update, and delete schedules with support for:

- Schedules with or without SSA linkage
- Single or multiple students (based on service `is_group_service` setting)
- Single day or recurring schedules (daily, weekly, bi-weekly, monthly)
- Hybrid recurrence storage (parent schedule + individual occurrence records)
- **Two batch number systems:**
  - `recurring_batch_number`: Links all occurrences of a recurring schedule
  - `group_batch_number`: Links all schedules in the same group session
- **Individual schedule records per student** (no pivot table) - allows removing individual students from specific occurrences
- **Billing status tracking** for each schedule record

## Database Schema

### 1. Create `schedules` table migration

**File:** `database/migrations/YYYY_MM_DD_HHMMSS_create_schedules_table.php`

**Fields:**

- `id` (primary key)
- `therapist_id` (foreign key to users) - required
- `student_id` (foreign key to users) - required (one student per schedule record)
- `ssa_id` (foreign key to service_support_agreements) - nullable
- `service_id` (foreign key to services) - required
- `school_id` (foreign key to schools) - nullable (derived from student)
- `parent_schedule_id` (foreign key to schedules) - nullable (for recurrence)
- `schedule_date` (date) - required (single date field)
- `start_time` (time) - required
- `end_time` (time) - required
- `recurrence_type` (enum: none, daily, weekly, bi_weekly, monthly) - default 'none'
- `recurrence_end_date` (date) - nullable (when recurrence_type != 'none')
- `is_group` (boolean) - default false (identifies if this is a group schedule)
- `recurring_batch_number` (string, 32) - nullable (links all occurrences of a recurring schedule)
- `group_batch_number` (string, 32) - nullable (links all schedules in the same group session)
- `status` (enum: scheduled, completed, cancelled) - default 'scheduled'
- `billing_status` (enum: pending, billed, not_billable, waived) - default 'pending'
- `notes` (text) - nullable
- `created_at`, `updated_at`, `deleted_at` (soft deletes)

**Indexes:**

- `therapist_id`, `schedule_date`, `start_time`
- `student_id`
- `ssa_id`
- `parent_schedule_id`
- `recurring_batch_number`
- `group_batch_number`
- `is_group`
- `status`
- `billing_status`

**Note:** Each student in a group session gets their own schedule record. This allows for removing individual students from specific occurrences without affecting others in the group.

## Models and Enums

### 2. Create `ScheduleStatus` enum

**File:** `app/Enums/ScheduleStatus.php`

- Cases: `SCHEDULED`, `COMPLETED`, `CANCELLED`
- Include `label()` and `options()` methods

### 3. Create `BillingStatus` enum

**File:** `app/Enums/BillingStatus.php`

- Cases: `PENDING`, `BILLED`, `NOT_BILLABLE`, `WAIVED`
- Include `label()` and `options()` methods

### 4. Create `RecurrenceType` enum

**File:** `app/Enums/RecurrenceType.php`

- Cases: `NONE`, `DAILY`, `WEEKLY`, `BI_WEEKLY`, `MONTHLY`
- Include `label()` and `options()` methods
- Reuse similar pattern to `ServiceFrequency`

### 5. Create `Schedule` model

**File:** `app/Models/Schedule.php`

**Relationships:**

- `therapist()` - BelongsTo User
- `student()` - BelongsTo User (one student per schedule record)
- `ssa()` - BelongsTo ServiceSupportAgreement (nullable)
- `service()` - BelongsTo Service
- `school()` - BelongsTo School (nullable)
- `parentSchedule()` - BelongsTo Schedule (nullable, self-referential)
- `occurrences()` - HasMany Schedule (where parent_schedule_id = this.id)

**Scopes:**

- `scheduled()`, `completed()`, `cancelled()`
- `pendingBilling()`, `billed()`, `notBillable()`, `waived()`
- `recurring()` (where recurrence_type != 'none')
- `single()` (where recurrence_type = 'none' and parent_schedule_id is null)
- `group()` (where is_group = true)
- `forTherapist(User $therapist)`
- `forStudent(User $student)`
- `forSSA(ServiceSupportAgreement $ssa)`
- `byRecurringBatch(string $batchNumber)`
- `byGroupBatch(string $batchNumber)`

**Methods:**

- `isRecurring(): bool`
- `isOccurrence(): bool`
- `isGroup(): bool`
- `durationMinutes(): int`
- `getRecurringOccurrences(): Collection` (get all with same recurring_batch_number)
- `getGroupSchedules(): Collection` (get all with same group_batch_number)

## DTOs

### 6. Create `CreateScheduleDTO`

**File:** `app/DTOs/CreateScheduleDTO.php`

**Properties:**

- `therapistId: int`
- `ssaId: ?int`
- `serviceId: int`
- `studentIds: array<int>` (at least 1, multiple if service.is_group_service)
- `scheduleDate: string` (date) - single date field
- `startTime: string` (time)
- `endTime: string` (time)
- `recurrenceType: RecurrenceType`
- `recurrenceEndDate: ?string` (date)
- `isGroup: bool` (determined by service.is_group_service and student count)
- `notes: ?string`

**Methods:**

- `fromRequest(array $data): self`
- `toArray(): array`

### 7. Create `UpdateScheduleDTO`

**File:** `app/DTOs/UpdateScheduleDTO.php`

- Similar to CreateScheduleDTO but all fields optional except IDs
- Add `billingStatus?: BillingStatus` for updating billing status

## Form Requests

### 8. Create `StoreScheduleRequest`

**File:** `app/Http/Requests/Therapist/StoreScheduleRequest.php`

**Validation Rules:**

- `ssa_id`: nullable, exists:service_support_agreements,id
- `service_id`: required, exists:services,id
- `student_ids`: required, array, min:1
- `student_ids.*`: exists:users,id,role:student
- `schedule_date`: required, date, after_or_equal:today
- `start_time`: required, date_format:H:i
- `end_time`: required, date_format:H:i, after:start_time
- `recurrence_type`: required, in:RecurrenceType values
- `recurrence_end_date`: required_if:recurrence_type,!=,none, date, after:schedule_date
- `notes`: nullable, string, max:1000

**Custom Validation:**

- If `ssa_id` provided, validate therapist has access to that SSA
- If `ssa_id` provided, validate students belong to that SSA
- Validate service `is_group_service` allows multiple students
- If multiple students, automatically set `is_group` to true
- If single student and service is group, allow but warn
- Validate students are assigned to therapist (via SSA or direct assignment)

### 9. Create `UpdateScheduleRequest`

**File:** `app/Http/Requests/Therapist/UpdateScheduleRequest.php`

- Similar validation but all fields optional
- Validate schedule belongs to therapist
- Add `billing_status`: nullable, in:BillingStatus values

## Service Layer

### 10. Update `ScheduleService`

**File:** `app/Domain/Therapist/Services/ScheduleService.php`

**Add Methods:**

- `createSchedule(User $therapist, CreateScheduleDTO $dto): Schedule`
  - Validate therapist access to students/SSA
  - Determine if group schedule (multiple students or service.is_group_service)
  - Generate `recurring_batch_number` (UUID or timestamp-based) if recurring
  - Generate `group_batch_number` (UUID or timestamp-based) for group schedules
  - If recurring: create parent schedule + generate occurrences with same recurring_batch_number
  - If group: create one schedule record per student, all with same group_batch_number
  - If recurring + group: each occurrence gets same recurring_batch_number, each week's group gets unique group_batch_number, and each student in each occurrence gets their own schedule record
  - **Create separate schedule records for each student** (no pivot table - each student gets their own record)
  - Return first created schedule (or parent schedule if recurring)

- `updateSchedule(User $therapist, int $scheduleId, UpdateScheduleDTO $dto): Schedule`
  - Validate ownership
  - Update schedule
  - If recurrence changed, regenerate occurrences with new recurring_batch_number
  - If student changed, update student_id (only affects this specific schedule record)
  - Update group_batch_number if group membership changes
  - Update billing_status if provided

- `deleteSchedule(User $therapist, int $scheduleId): void`
  - Validate ownership
  - If parent schedule, delete all occurrences (by recurring_batch_number)
  - If group schedule, optionally delete all in group (by group_batch_number) or just this one
  - **Since each student has their own record, deleting one student's schedule doesn't affect others in the group**
  - Soft delete schedule(s)

- `removeStudentFromOccurrence(User $therapist, int $scheduleId): void`
  - Validate ownership
  - Soft delete the specific schedule record for this student
  - This allows removing a student from a specific occurrence without affecting other students or occurrences

- `generateRecurringOccurrences(Schedule $parentSchedule, array $studentIds): Collection`
  - Calculate dates based on recurrence_type from schedule_date to recurrence_end_date
  - **Create separate Schedule records for each student for each occurrence** with same recurring_batch_number
  - If group: each occurrence gets unique group_batch_number (one per week/session)
  - Link to parent via parent_schedule_id
  - **Each student gets their own schedule record per occurrence**
  - Set schedule_date for each occurrence

- `generateBatchNumber(string $type = 'recurring'): string`
  - Generate unique batch number (UUID or timestamp-based)
  - Type can be 'recurring' or 'group'

- `updateBillingStatus(User $therapist, int $scheduleId, BillingStatus $status): Schedule`
  - Validate ownership
  - Update billing_status for the schedule
  - Can be used for bulk updates by batch number

- `bulkUpdateBillingStatus(User $therapist, array $scheduleIds, BillingStatus $status): int`
  - Validate ownership of all schedules
  - Update billing_status for multiple schedules
  - Return count of updated records

**Update Existing:**

- `getSchedules()` - query actual Schedule model
- `getPendingCount()` - count scheduled status
- `getPendingBillingCount()` - count pending billing status

## Repository Layer

### 11. Update `ScheduleRepositoryInterface`

**File:** `app/Domain/Therapist/Repositories/ScheduleRepositoryInterface.php`

**Add Methods:**

- `create(array $data): Schedule`
- `update(Schedule $schedule, array $data): Schedule`
- `delete(Schedule $schedule): void`
- `findForTherapist(User $therapist, int $scheduleId): ?Schedule`
- `getRecurringOccurrences(Schedule $parentSchedule): Collection`
- `getRecurringOccurrencesByBatch(string $recurringBatchNumber): Collection`
- `getGroupSchedulesByBatch(string $groupBatchNumber): Collection`
- `getSchedulesForStudent(User $student, array $filters = []): Collection`
- `validateTherapistAccessToSSA(User $therapist, int $ssaId): bool`
- `validateTherapistAccessToStudents(User $therapist, array $studentIds): bool`
- `generateBatchNumber(string $type = 'recurring'): string`
- `updateBillingStatus(Schedule $schedule, BillingStatus $status): Schedule`
- `bulkUpdateBillingStatus(array $scheduleIds, BillingStatus $status): int`

### 12. Update `EloquentScheduleRepository`

**File:** `app/Infrastructure/Repositories/EloquentScheduleRepository.php`

**Implement:**

- All new interface methods
- Update `getSchedulesForTherapist()` to query Schedule model with filters
- Update `getPendingCount()` to count scheduled status
- Add eager loading for relationships (student, service, ssa, school)
- Add filtering by billing_status

## Controller

### 13. Update `ScheduleController`

**File:** `app/Http/Controllers/Therapist/ScheduleController.php`

**Add Methods:**

- `store(StoreScheduleRequest $request): JsonResponse`
  - Create schedule via service
  - Return created schedule with relationships

- `update(UpdateScheduleRequest $request, int $id): JsonResponse`
  - Update schedule via service
  - Return updated schedule

- `destroy(int $id): JsonResponse`
  - Delete schedule via service
  - Return success response

- `removeStudent(int $id): JsonResponse`
  - Remove student from specific occurrence
  - Soft delete the schedule record for this student
  - Return success response

- `updateBillingStatus(Request $request, int $id): JsonResponse`
  - Update billing status for a schedule
  - Validate billing_status in request
  - Return updated schedule

- `bulkUpdateBillingStatus(Request $request): JsonResponse`
  - Update billing status for multiple schedules
  - Validate schedule_ids array and billing_status
  - Return count of updated records

**Update Existing:**

- `getSchedules()` - return proper Schedule model data
- `calendar()` - ensure schedules are properly loaded

## Routes

### 14. Update therapist routes

**File:** `routes/therapist.php`

**Add:**

```php
Route::post('schedule', [ScheduleController::class, 'store'])->name('schedule.store');
Route::put('schedule/{id}', [ScheduleController::class, 'update'])->name('schedule.update');
Route::delete('schedule/{id}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
Route::post('schedule/{id}/remove-student', [ScheduleController::class, 'removeStudent'])->name('schedule.remove-student');
Route::put('schedule/{id}/billing-status', [ScheduleController::class, 'updateBillingStatus'])->name('schedule.update-billing-status');
Route::post('schedule/bulk-billing-status', [ScheduleController::class, 'bulkUpdateBillingStatus'])->name('schedule.bulk-billing-status');
```

## Policies

### 15. Create `SchedulePolicy`

**File:** `app/Policies/SchedulePolicy.php`

**Methods:**

- `viewAny(User $user): bool` - therapist role
- `view(User $user, Schedule $schedule): bool` - owns schedule
- `create(User $user): bool` - therapist role
- `update(User $user, Schedule $schedule): bool` - owns schedule
- `delete(User $user, Schedule $schedule): bool` - owns schedule
- `updateBillingStatus(User $user, Schedule $schedule): bool` - owns schedule or admin role

**Register in AppServiceProvider:**

- `Gate::policy(Schedule::class, SchedulePolicy::class);`

## Testing

### 16. Update `ScheduleTest`

**File:** `tests/Feature/Therapist/ScheduleTest.php`

**Add Tests:**

- Test schedule creation (single, with SSA, without SSA)
- Test schedule creation with multiple students (group service) - each gets own record
- Test recurring schedule creation and occurrence generation - each student gets own record per occurrence
- Test batch number generation (recurring and group)
- Test group batch number linking (same session schedules)
- Test recurring batch number linking (all occurrences)
- Test schedule update
- Test schedule deletion (single and recurring)
- Test removing individual student from occurrence (doesn't affect others)
- Test billing status update (single and bulk)
- Test therapist access validation
- Test student assignment validation
- Test service group validation

## Implementation Notes

1. **Individual Schedule Records Per Student:**
   - **No pivot table** - each student gets their own schedule record
   - Allows removing individual students from specific occurrences
   - Example: 3 students in a group, remove 1 from week 5 = only that student's week 5 record is deleted
   - Group schedules are identified by `group_batch_number` linking multiple records
   - Each record has its own `student_id` field

2. **Batch Number System:**

   - **Recurring Batch Number:** Links all occurrences of a recurring schedule together
     - Example: 3 students, weekly for 10 weeks = 30 entries, all share same recurring_batch_number
     - Generated once when parent schedule is created
     - All occurrences inherit this number

   - **Group Batch Number:** Links all schedules in the same group session together
     - Example: 3 students in same session = 3 entries with same group_batch_number
     - For recurring groups: Each week gets unique group_batch_number
     - Example: 3 students, weekly for 10 weeks = 10 different group_batch_numbers (one per week)

   - **Batch Number Format:** Use UUID or timestamp-based unique identifier (e.g., `GRP-20250128-abc123` or UUID)

3. **Billing Status:**
   - `pending`: Default status, not yet billed
   - `billed`: Successfully billed
   - `not_billable`: Marked as not billable (e.g., cancelled sessions, administrative)
   - `waived`: Billing waived for this session
   - Can be updated individually or in bulk by batch number
   - Used for reporting and billing workflows

4. **Group Schedule Logic:**

   - `is_group` is true when: multiple students OR service.is_group_service is true
   - **Each student gets their own schedule record** with same `group_batch_number`
   - All schedules in same group session share `group_batch_number`
   - For recurring groups: Each occurrence (week) gets new `group_batch_number`
   - Removing a student from one occurrence only deletes that student's record for that occurrence

5. **Recurrence Logic:**

   - Parent schedule stores recurrence rules
   - Individual occurrences are separate records linked via `parent_schedule_id` and `recurring_batch_number`
   - **Each student gets their own record for each occurrence**
   - Each occurrence has its own `schedule_date`
   - When updating parent, regenerate occurrences with new `recurring_batch_number`
   - When deleting parent, cascade delete all occurrences by `recurring_batch_number`
   - Can delete individual student occurrences without affecting others

6. **Student Selection:**

   - If SSA provided, students must belong to that SSA
   - If no SSA, students must be directly assigned to therapist
   - Service `is_group_service` determines if multiple students allowed
   - Multiple students = group schedule (is_group = true)
   - **Each student gets their own schedule record** even in groups

7. **School Derivation:**

   - School is derived from student's school_id (via StudentProfile)
   - For group schedules, each student's school_id is stored in their own record
   - Can be nullable if student has no school

8. **Time Validation:**

   - Ensure end_time > start_time
   - Consider timezone (use therapist or school timezone)

9. **Example Scenario:**

   - 3 students, weekly schedule for 10 weeks
   - Result: 30 schedule entries (3 students × 10 weeks)
   - All 30 entries share same `recurring_batch_number`
   - Week 1 (3 entries): share `group_batch_number_1`
   - Week 2 (3 entries): share `group_batch_number_2`
   - ... and so on for 10 unique group batch numbers
   - Removing Student 2 from Week 5: Only Student 2's Week 5 record is deleted (2 records remain for Week 5)

10. **Billing Workflow:**

    - Schedules start with `billing_status = 'pending'`
    - After billing process, update to `billed`
    - Cancelled or administrative sessions can be marked `not_billable`
    - Special cases can be marked `waived`
    - Bulk updates possible by batch number for efficiency

11. **Future Enhancements:**

    - SSA minutes validation (served_minutes tracking)
    - Conflict detection (overlapping schedules)
    - Bulk operations (delete multiple occurrences by batch number)
    - Query schedules by batch number for reporting
    - Billing integration and export
    - Schedule templates for common patterns

