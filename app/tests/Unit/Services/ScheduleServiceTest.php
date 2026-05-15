<?php

declare(strict_types=1);

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Repositories\ServiceRepositoryInterface;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Therapist\Services\ScheduleService;
use App\Domain\Time\UserTimezoneService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\DTOs\CreateScheduleDTO;
use App\DTOs\OverlapCheckDTO;
use App\DTOs\OverlapExclusionsDTO;
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Exceptions\CannotDeleteBilledScheduleException;
use App\Exceptions\ScheduleOverlapException;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

/** @return array{repository: MockInterface, timezoneService: MockInterface, userRepository: MockInterface, serviceRepository: MockInterface, studentRepository: MockInterface, schoolRepository: MockInterface} */
function makeScheduleMocks(): array
{
    return [
        'repository' => Mockery::mock(ScheduleRepositoryInterface::class),
        'timezoneService' => Mockery::mock(UserTimezoneService::class),
        'userRepository' => Mockery::mock(UserRepositoryInterface::class),
        'serviceRepository' => Mockery::mock(ServiceRepositoryInterface::class),
        'studentRepository' => Mockery::mock(StudentRepositoryInterface::class),
        'schoolRepository' => Mockery::mock(SchoolRepositoryInterface::class),
    ];
}

/** @param array{repository: MockInterface, timezoneService: MockInterface, userRepository: MockInterface, serviceRepository: MockInterface, studentRepository: MockInterface, schoolRepository: MockInterface} $mocks */
function makeScheduleService(array $mocks): ScheduleService
{
    return new ScheduleService(
        $mocks['repository'],
        $mocks['timezoneService'],
        $mocks['userRepository'],
        $mocks['serviceRepository'],
        $mocks['studentRepository'],
        $mocks['schoolRepository'],
    );
}

/**
 * Stub the school repository lookup that `isSchoolBillable()` triggers on update.
 *
 * @param array{repository: MockInterface, timezoneService: MockInterface, userRepository: MockInterface, serviceRepository: MockInterface, studentRepository: MockInterface, schoolRepository: MockInterface} $mocks
 */
function stubSchoolLookupForBillableResolution(array $mocks, Schedule $schedule): void
{
    if ($schedule->school_id === null) {
        return;
    }

    $schoolId = (int) $schedule->school_id;
    $school = School::query()->find($schoolId);
    $mocks['schoolRepository']->shouldReceive('find')
        ->once()
        ->with($schoolId)
        ->andReturn($school);
}

beforeEach(function (): void {
    Event::fake();
});

// ---------------------------------------------------------------------------
// createSchedule
// ---------------------------------------------------------------------------

it('creates a single non-recurring schedule record', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $studentUser = User::factory()->create();
    StudentProfile::factory()->create(['user_id' => $studentUser->id]);
    $service = Service::factory()->create(['is_group_service' => false]);

    $mocks['repository']->shouldReceive('validateTherapistAccessToSSA')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateTherapistAccessToStudents')
        ->once()->with($therapist, [$studentUser->id])->andReturnTrue();
    $mocks['repository']->shouldReceive('validateStudentsShareService')
        ->once()->with($therapist, [$studentUser->id], $service->id)->andReturnTrue();
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->once()->andReturnUsing(fn ($dt) => Carbon::parse($dt));
    $mocks['repository']->shouldReceive('hasOverlap')->times(2)->andReturnFalse();
    $mocks['userRepository']->shouldReceive('findByIds')
        ->once()->with([$studentUser->id])->andReturn(collect([$studentUser]));
    $mocks['serviceRepository']->shouldReceive('findOrFail')
        ->once()->with($service->id)->andReturn($service);
    $mocks['studentRepository']->shouldReceive('getSchoolIdByUserId')
        ->once()->with($studentUser->id)->andReturn(null);
    $mocks['repository']->shouldReceive('create')->once()->andReturnUsing(function (array $data) {
        $schedule = new Schedule($data);
        $schedule->id = 1;

        return $schedule;
    });

    $schedule = makeScheduleService($mocks)->createSchedule($therapist, new CreateScheduleDTO(
        therapistId: $therapist->id,
        ssaId: null,
        serviceId: $service->id,
        studentIds: [$studentUser->id],
        scheduleDate: '2025-01-01',
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::NONE,
        recurrenceEndDate: null,
        isGroup: false,
        occurrenceCount: null,
        occurrenceDates: null,
        notes: null,
        locationDetails: null,
        durationMinutes: 60,
    ));

    expect($schedule)->toBeInstanceOf(Schedule::class)
        ->and($schedule->therapist_id)->toBe($therapist->id)
        ->and($schedule->student_id)->toBe($studentUser->id)
        ->and($schedule->service_id)->toBe($service->id)
        ->and($schedule->status)->toBe(ScheduleStatus::SCHEDULED)
        ->and($schedule->billing_status)->toBe(BillingStatus::PENDING);
});

it('marks schedule as non-billable when school non_billable_scheduling flag is enabled', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $studentUser = User::factory()->create();
    StudentProfile::factory()->create(['user_id' => $studentUser->id]);
    $service = Service::factory()->create(['is_group_service' => false]);
    $school = School::factory()->create(['non_billable_scheduling' => true]);

    $mocks['repository']->shouldReceive('validateTherapistAccessToSSA')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateTherapistAccessToStudents')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateStudentsShareService')->andReturnTrue();
    $mocks['repository']->shouldReceive('hasOverlap')->andReturnFalse();
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->andReturnUsing(fn ($dt) => Carbon::parse($dt));
    $mocks['userRepository']->shouldReceive('findByIds')->andReturn(collect([$studentUser]));
    $mocks['serviceRepository']->shouldReceive('findOrFail')->andReturn($service);
    $mocks['studentRepository']->shouldReceive('getSchoolIdByUserId')
        ->with($studentUser->id)->andReturn($school->id);
    $mocks['schoolRepository']->shouldReceive('find')
        ->once()->with($school->id)->andReturn($school);

    /** @var array<string, mixed> $capturedData */
    $capturedData = [];
    $mocks['repository']->shouldReceive('create')->once()->andReturnUsing(function (array $data) use (&$capturedData) {
        $capturedData = $data;
        $schedule = new Schedule($data);
        $schedule->id = 1;

        return $schedule;
    });

    makeScheduleService($mocks)->createSchedule($therapist, new CreateScheduleDTO(
        therapistId: $therapist->id,
        ssaId: null,
        serviceId: $service->id,
        studentIds: [$studentUser->id],
        scheduleDate: '2025-01-01',
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::NONE,
        recurrenceEndDate: null,
        isGroup: false,
        occurrenceCount: null,
        occurrenceDates: null,
        notes: null,
        locationDetails: null,
        durationMinutes: 60,
    ));

    expect($capturedData)->toHaveKey('is_billable')
        ->and($capturedData['is_billable'])->toBeFalse();
});

it('marks schedule as billable when school non_billable_scheduling flag is disabled', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $studentUser = User::factory()->create();
    StudentProfile::factory()->create(['user_id' => $studentUser->id]);
    $service = Service::factory()->create(['is_group_service' => false]);
    $school = School::factory()->create(['non_billable_scheduling' => false]);

    $mocks['repository']->shouldReceive('validateTherapistAccessToSSA')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateTherapistAccessToStudents')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateStudentsShareService')->andReturnTrue();
    $mocks['repository']->shouldReceive('hasOverlap')->andReturnFalse();
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->andReturnUsing(fn ($dt) => Carbon::parse($dt));
    $mocks['userRepository']->shouldReceive('findByIds')->andReturn(collect([$studentUser]));
    $mocks['serviceRepository']->shouldReceive('findOrFail')->andReturn($service);
    $mocks['studentRepository']->shouldReceive('getSchoolIdByUserId')
        ->with($studentUser->id)->andReturn($school->id);
    $mocks['schoolRepository']->shouldReceive('find')
        ->once()->with($school->id)->andReturn($school);

    /** @var array<string, mixed> $capturedData */
    $capturedData = [];
    $mocks['repository']->shouldReceive('create')->once()->andReturnUsing(function (array $data) use (&$capturedData) {
        $capturedData = $data;
        $schedule = new Schedule($data);
        $schedule->id = 1;

        return $schedule;
    });

    makeScheduleService($mocks)->createSchedule($therapist, new CreateScheduleDTO(
        therapistId: $therapist->id,
        ssaId: null,
        serviceId: $service->id,
        studentIds: [$studentUser->id],
        scheduleDate: '2025-01-01',
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::NONE,
        recurrenceEndDate: null,
        isGroup: false,
        occurrenceCount: null,
        occurrenceDates: null,
        notes: null,
        locationDetails: null,
        durationMinutes: 60,
    ));

    expect($capturedData)->toHaveKey('is_billable')
        ->and($capturedData['is_billable'])->toBeTrue();
});

it('validates shared service for group schedules', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $studentUser1 = User::factory()->create();
    $studentUser2 = User::factory()->create();
    StudentProfile::factory()->create(['user_id' => $studentUser1->id]);
    StudentProfile::factory()->create(['user_id' => $studentUser2->id]);
    $service = Service::factory()->create(['is_group_service' => true, 'is_direct_service' => true]);

    $mocks['repository']->shouldReceive('validateTherapistAccessToSSA')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateTherapistAccessToStudents')
        ->once()->with($therapist, [$studentUser1->id, $studentUser2->id])->andReturnTrue();
    $mocks['repository']->shouldReceive('validateStudentsShareService')
        ->once()->with($therapist, [$studentUser1->id, $studentUser2->id], $service->id)->andReturnTrue();
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->once()->andReturnUsing(fn ($dt) => Carbon::parse($dt));
    $mocks['repository']->shouldReceive('hasOverlap')->times(3)->andReturnFalse();
    $mocks['repository']->shouldReceive('generateBatchNumber')
        ->once()->with('group')->andReturn('GRP-123');
    $mocks['userRepository']->shouldReceive('findByIds')
        ->once()->with([$studentUser1->id, $studentUser2->id])
        ->andReturn(collect([$studentUser1, $studentUser2]));
    $mocks['serviceRepository']->shouldReceive('findOrFail')
        ->once()->with($service->id)->andReturn($service);
    $mocks['studentRepository']->shouldReceive('getSchoolIdByUserId')->twice()->andReturn(null);
    $mocks['repository']->shouldReceive('create')->twice()->andReturnUsing(function (array $data) {
        $schedule = new Schedule($data);
        $schedule->id = $schedule->student_id;

        return $schedule;
    });

    $schedule = makeScheduleService($mocks)->createSchedule($therapist, new CreateScheduleDTO(
        therapistId: $therapist->id,
        ssaId: null,
        serviceId: $service->id,
        studentIds: [$studentUser1->id, $studentUser2->id],
        scheduleDate: '2025-01-01',
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::NONE,
        recurrenceEndDate: null,
        isGroup: true,
        occurrenceCount: null,
        occurrenceDates: null,
        notes: null,
        locationDetails: null,
        durationMinutes: 60,
    ));

    expect($schedule)->toBeInstanceOf(Schedule::class)
        ->and($schedule->student_id)->toBe($studentUser1->id)
        ->and($schedule->group_batch_number)->toBe('GRP-123');
});

it('delegates batch number generation to the repository', function (): void {
    $mocks = makeScheduleMocks();
    $mocks['repository']->shouldReceive('getSchedulesForTherapist')->andReturn(collect());
    $mocks['repository']->shouldReceive('getPendingCount')->andReturn(0);
    $mocks['repository']->shouldReceive('getSchoolsForTherapist')->andReturn(collect());
    $mocks['repository']->shouldReceive('getStudentsForTherapist')->andReturn(collect());
    $mocks['repository']->shouldReceive('generateBatchNumber')
        ->once()->with('recurring')->andReturn('REC-123');

    expect(makeScheduleService($mocks)->generateBatchNumber('recurring'))->toBe('REC-123');
});

// ---------------------------------------------------------------------------
// updateSchedule
// ---------------------------------------------------------------------------

it('updates a single schedule record', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::NONE,
        'recurring_batch_number' => null,
        'schedule_date' => '2025-01-02',
    ]);

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, $schedule->id)->andReturn($schedule);
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->once()->andReturnUsing(fn ($dt) => Carbon::parse($dt));
    $mocks['repository']->shouldReceive('hasOverlap')->twice()->andReturnFalse();
    $mocks['userRepository']->shouldReceive('findById')
        ->once()->with($schedule->student_id)->andReturn(User::find($schedule->student_id));
    stubSchoolLookupForBillableResolution($mocks, $schedule);
    $mocks['repository']->shouldReceive('update')->once()->andReturn($schedule);

    $updatedSchedule = makeScheduleService($mocks)->updateSchedule($therapist, $schedule->id, new UpdateScheduleDTO(
        ssaId: null,
        serviceId: null,
        studentIds: null,
        scheduleDate: '2025-01-02',
        startTime: '10:00',
        endTime: '11:00',
        recurrenceType: RecurrenceType::NONE,
        recurrenceEndDate: null,
        isGroup: null,
        locationDetails: null,
        notes: 'Updated notes',
        billingStatus: null,
        durationMinutes: 60,
    ));

    expect($updatedSchedule->id)->toBe($schedule->id);
});

it('excludes own batch number from overlap check on update', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $batchNumber = 'REC-TEST-001';

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::WEEKLY,
        'recurring_batch_number' => $batchNumber,
        'parent_schedule_id' => null,
        'schedule_date' => '2025-01-06',
    ]);

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, $schedule->id)->andReturn($schedule);
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->once()->andReturnUsing(fn ($dt) => Carbon::parse($dt));

    $batchPassedToOverlap = [];
    $mocks['repository']->shouldReceive('hasOverlap')->twice()
        ->andReturnUsing(function (User $user, OverlapCheckDTO $check, OverlapExclusionsDTO $exclusions) use (&$batchPassedToOverlap): bool {
            $batchPassedToOverlap[] = $exclusions->batchNumber;

            return false;
        });

    $mocks['userRepository']->shouldReceive('findById')
        ->once()->with($schedule->student_id)->andReturn(User::find($schedule->student_id));
    stubSchoolLookupForBillableResolution($mocks, $schedule);
    $mocks['repository']->shouldNotReceive('getUnbilledFutureRecurringOccurrencesByBatch');
    $mocks['repository']->shouldNotReceive('generateRecurringOccurrences');
    $mocks['repository']->shouldReceive('update')->once()->andReturn($schedule);

    $updatedSchedule = makeScheduleService($mocks)->updateSchedule($therapist, $schedule->id, new UpdateScheduleDTO(
        ssaId: null,
        serviceId: null,
        studentIds: null,
        scheduleDate: '2025-01-06',
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::WEEKLY,
        recurrenceEndDate: null,
        isGroup: null,
        locationDetails: 'Updated location',
        notes: null,
        billingStatus: null,
        durationMinutes: 60,
    ));

    expect($updatedSchedule->id)->toBe($schedule->id)
        ->and($batchPassedToOverlap)->toHaveCount(2, 'hasOverlap must be called twice')
        ->and($batchPassedToOverlap[0])->toBe($batchNumber, 'Therapist overlap check must exclude the batch')
        ->and($batchPassedToOverlap[1])->toBe($batchNumber, 'Student overlap check must exclude the batch');
});

it('deletes future siblings and regenerates occurrences when recurrence type changes', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $batchNumber = 'REC-TEST-002';

    $sibling = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::DAILY,
        'recurring_batch_number' => $batchNumber,
        'schedule_date' => '2025-01-07',
    ]);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::DAILY,
        'recurring_batch_number' => $batchNumber,
        'parent_schedule_id' => null,
        'schedule_date' => '2025-01-06',
        'recurrence_end_date' => '2025-01-31',
    ]);

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, $schedule->id)->andReturn($schedule);
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->once()->andReturnUsing(fn ($dt) => Carbon::parse($dt));
    $mocks['repository']->shouldReceive('hasOverlap')->twice()->andReturnFalse();
    $mocks['userRepository']->shouldReceive('findById')
        ->once()->andReturn(User::find($schedule->student_id));
    stubSchoolLookupForBillableResolution($mocks, $schedule);
    $mocks['repository']->shouldReceive('getUnbilledFutureRecurringOccurrencesByBatch')
        ->once()->with($batchNumber, $schedule->schedule_date->format('Y-m-d'))
        ->andReturn(collect([$sibling]));
    $mocks['repository']->shouldReceive('delete')->once()->with($sibling);
    $mocks['repository']->shouldReceive('generateBatchNumber')
        ->once()->with('recurring')->andReturn('REC-NEW-003');
    $mocks['repository']->shouldReceive('update')->once()->andReturnUsing(function () use ($schedule): Schedule {
        $schedule->recurrence_type = RecurrenceType::WEEKLY;
        $schedule->recurring_batch_number = 'REC-NEW-003';
        $schedule->recurrence_end_date = Carbon::parse('2025-03-31');
        $schedule->parent_schedule_id = null;

        return $schedule;
    });

    // Regeneration stubs
    $mocks['timezoneService']->shouldReceive('toUserTimezone')->andReturnUsing(fn ($dt) => $dt);
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')->andReturnUsing(fn ($dt) => Carbon::parse($dt));
    $mocks['userRepository']->shouldReceive('findById')->andReturn($therapist);
    $mocks['userRepository']->shouldReceive('findByIds')->andReturn(collect([$therapist]));
    $mocks['studentRepository']->shouldReceive('getSchoolIdByUserId')->andReturn(null);
    $mocks['repository']->shouldReceive('hasOverlap')->andReturnFalse();
    $mocks['repository']->shouldReceive('create')->andReturnUsing(function (array $data) {
        $s = new Schedule($data);
        $s->id = rand(100, 999);

        return $s;
    });

    $result = makeScheduleService($mocks)->updateSchedule($therapist, $schedule->id, new UpdateScheduleDTO(
        ssaId: null,
        serviceId: null,
        studentIds: null,
        scheduleDate: '2025-01-06',
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::WEEKLY,
        recurrenceEndDate: '2025-03-31',
        isGroup: null,
        locationDetails: null,
        notes: null,
        billingStatus: null,
        durationMinutes: 60,
    ));

    expect($result->id)->toBe($schedule->id)
        ->and($result->recurring_batch_number)->toBe('REC-NEW-003')
        ->and($result->recurrence_type)->toBe(RecurrenceType::WEEKLY);
});

it('clears recurring fields and deletes future siblings when switching recurrence to NONE', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $batchNumber = 'REC-TEST-004';

    $sibling = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::WEEKLY,
        'recurring_batch_number' => $batchNumber,
        'schedule_date' => '2025-01-13',
    ]);

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::WEEKLY,
        'recurring_batch_number' => $batchNumber,
        'parent_schedule_id' => null,
        'schedule_date' => '2025-01-06',
        'recurrence_end_date' => '2025-03-31',
    ]);

    $clearedSchedule = new Schedule([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::NONE,
        'recurring_batch_number' => null,
        'parent_schedule_id' => null,
        'schedule_date' => '2025-01-06',
        'recurrence_end_date' => null,
        'is_group' => false,
    ]);
    $clearedSchedule->id = $schedule->id;

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, $schedule->id)->andReturn($schedule);
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->once()->andReturnUsing(fn ($dt) => Carbon::parse($dt));
    $mocks['repository']->shouldReceive('hasOverlap')->twice()->andReturnFalse();
    $mocks['userRepository']->shouldReceive('findById')
        ->once()->andReturn(User::find($schedule->student_id));
    stubSchoolLookupForBillableResolution($mocks, $schedule);
    $mocks['repository']->shouldReceive('getUnbilledFutureRecurringOccurrencesByBatch')
        ->once()->with($batchNumber, $schedule->schedule_date->format('Y-m-d'))
        ->andReturn(collect([$sibling]));
    $mocks['repository']->shouldReceive('delete')->once()->with($sibling);
    $mocks['repository']->shouldNotReceive('generateBatchNumber');
    $mocks['repository']->shouldReceive('update')->once()->andReturn($clearedSchedule);

    $result = makeScheduleService($mocks)->updateSchedule($therapist, $schedule->id, new UpdateScheduleDTO(
        ssaId: null,
        serviceId: null,
        studentIds: null,
        scheduleDate: '2025-01-06',
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::NONE,
        recurrenceEndDate: null,
        isGroup: null,
        locationDetails: null,
        notes: null,
        billingStatus: null,
        durationMinutes: 60,
    ));

    expect($result->id)->toBe($schedule->id)
        ->and($result->recurring_batch_number)->toBeNull()
        ->and($result->recurrence_end_date)->toBeNull();
});

// ---------------------------------------------------------------------------
// deleteSchedule
// ---------------------------------------------------------------------------

it('deletes a single schedule record', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::NONE,
    ]);

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, $schedule->id)->andReturn($schedule);
    $mocks['repository']->shouldReceive('delete')->once()->with($schedule);

    makeScheduleService($mocks)->deleteSchedule($therapist, $schedule->id);
});

it('deletes only the single record even when the schedule is recurring', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $batchId = 'REC-123';

    $parent = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::WEEKLY,
        'recurring_batch_number' => $batchId,
        'parent_schedule_id' => null,
    ]);

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, $parent->id)->andReturn($parent);
    $mocks['repository']->shouldNotReceive('getRecurringOccurrencesByBatch');
    $mocks['repository']->shouldReceive('delete')->once()->with($parent);

    makeScheduleService($mocks)->deleteSchedule($therapist, $parent->id);
});

it('throws when attempting to delete a billed schedule', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::NONE,
        'billing_status' => BillingStatus::BILLED,
    ]);

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, $schedule->id)->andReturn($schedule);
    $mocks['repository']->shouldNotReceive('delete');

    expect(fn () => makeScheduleService($mocks)->deleteSchedule($therapist, $schedule->id))
        ->toThrow(CannotDeleteBilledScheduleException::class, 'Cannot delete a schedule that has already been billed.');
});

// ---------------------------------------------------------------------------
// deleteFutureRecurringSchedules
// ---------------------------------------------------------------------------

it('deletes current and future occurrences but not past ones', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $batchId = 'REC-456';
    $lastWeek = now()->subWeek()->format('Y-m-d');
    $today = now()->format('Y-m-d');
    $nextWeek = now()->addWeek()->format('Y-m-d');

    $pastSchedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::WEEKLY,
        'recurring_batch_number' => $batchId,
        'schedule_date' => $lastWeek,
    ]);

    $currentSchedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::WEEKLY,
        'recurring_batch_number' => $batchId,
        'schedule_date' => $today,
    ]);

    $futureSchedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::WEEKLY,
        'recurring_batch_number' => $batchId,
        'schedule_date' => $nextWeek,
    ]);

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, $currentSchedule->id)->andReturn($currentSchedule);
    $mocks['repository']->shouldReceive('getUnbilledFutureRecurringOccurrencesByBatch')
        ->once()->with($batchId, $today)
        ->andReturn(collect([$currentSchedule, $futureSchedule]));
    $mocks['repository']->shouldReceive('delete')->once()->with($currentSchedule);
    $mocks['repository']->shouldReceive('delete')->once()->with($futureSchedule);
    $mocks['repository']->shouldNotReceive('delete')->with($pastSchedule);

    expect(makeScheduleService($mocks)->deleteFutureRecurringSchedules($therapist, $currentSchedule->id))->toBe(2);
});

it('returns zero when schedule has no recurring batch', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'recurrence_type' => RecurrenceType::NONE,
        'recurring_batch_number' => null,
    ]);

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, $schedule->id)->andReturn($schedule);
    $mocks['repository']->shouldNotReceive('getUnbilledFutureRecurringOccurrencesByBatch');
    $mocks['repository']->shouldNotReceive('delete');

    expect(makeScheduleService($mocks)->deleteFutureRecurringSchedules($therapist, $schedule->id))->toBe(0);
});

it('returns zero when schedule is not found', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();

    $mocks['repository']->shouldReceive('findForTherapist')
        ->once()->with($therapist, 999)->andReturnNull();
    $mocks['repository']->shouldNotReceive('getUnbilledFutureRecurringOccurrencesByBatch');
    $mocks['repository']->shouldNotReceive('delete');

    expect(makeScheduleService($mocks)->deleteFutureRecurringSchedules($therapist, 999))->toBe(0);
});

// ---------------------------------------------------------------------------
// Overlap exceptions
// ---------------------------------------------------------------------------

it('throws ScheduleOverlapException when therapist has a conflicting schedule', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $studentUser = User::factory()->create();
    StudentProfile::factory()->create(['user_id' => $studentUser->id]);
    $service = Service::factory()->create(['is_group_service' => false]);

    $mocks['repository']->shouldReceive('validateTherapistAccessToSSA')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateTherapistAccessToStudents')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateStudentsShareService')->andReturnTrue();
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->once()->andReturnUsing(fn ($dt) => Carbon::parse($dt));

    $scheduleDate = now()->addWeek()->format('Y-m-d');
    $mocks['repository']->shouldReceive('hasOverlap')
        ->once()
        ->with(
            $therapist,
            Mockery::on(fn (OverlapCheckDTO $c) => $c->date === $scheduleDate && $c->startTime === '09:00:00' && $c->endTime === '10:00:00'),
            Mockery::on(fn (OverlapExclusionsDTO $e) => $e->scheduleId === null && $e->batchNumber === null),
        )
        ->andReturnTrue();

    $mocks['userRepository']->shouldReceive('findByIds')
        ->once()->with([$studentUser->id])->andReturn(collect([$studentUser]));
    $mocks['serviceRepository']->shouldReceive('findOrFail')
        ->once()->with($service->id)->andReturn($service);

    expect(fn () => makeScheduleService($mocks)->createSchedule($therapist, new CreateScheduleDTO(
        therapistId: $therapist->id,
        ssaId: null,
        serviceId: $service->id,
        studentIds: [$studentUser->id],
        scheduleDate: $scheduleDate,
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::NONE,
        recurrenceEndDate: null,
        isGroup: false,
        occurrenceCount: null,
        occurrenceDates: null,
        notes: null,
        locationDetails: null,
        durationMinutes: 60,
    )))->toThrow(ScheduleOverlapException::class);
});

it('throws ScheduleOverlapException when a student has a conflicting schedule', function (): void {
    $mocks = makeScheduleMocks();
    $therapist = User::factory()->create();
    $studentUser = User::factory()->create();
    StudentProfile::factory()->create(['user_id' => $studentUser->id]);
    $service = Service::factory()->create(['is_group_service' => false]);

    $mocks['repository']->shouldReceive('validateTherapistAccessToSSA')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateTherapistAccessToStudents')->andReturnTrue();
    $mocks['repository']->shouldReceive('validateStudentsShareService')->andReturnTrue();
    $mocks['timezoneService']->shouldReceive('parseUserLocalToUtc')
        ->once()->andReturnUsing(fn ($dt) => Carbon::parse($dt));

    $scheduleDate = now()->addWeek()->format('Y-m-d');
    $checkMatcher = Mockery::on(fn (OverlapCheckDTO $c) => $c->date === $scheduleDate && $c->startTime === '09:00:00' && $c->endTime === '10:00:00');
    $noExclusionsMatcher = Mockery::on(fn (OverlapExclusionsDTO $e) => $e->scheduleId === null && $e->batchNumber === null);

    $mocks['repository']->shouldReceive('hasOverlap')
        ->once()->with($therapist, $checkMatcher, $noExclusionsMatcher)->andReturnFalse();
    $mocks['userRepository']->shouldReceive('findByIds')
        ->once()->with([$studentUser->id])->andReturn(collect([$studentUser]));
    $mocks['repository']->shouldReceive('hasOverlap')
        ->once()->with(Mockery::on(fn ($arg) => $arg->id === $studentUser->id), $checkMatcher, $noExclusionsMatcher)
        ->andReturnTrue();
    $mocks['serviceRepository']->shouldReceive('findOrFail')
        ->once()->with($service->id)->andReturn($service);

    expect(fn () => makeScheduleService($mocks)->createSchedule($therapist, new CreateScheduleDTO(
        therapistId: $therapist->id,
        ssaId: null,
        serviceId: $service->id,
        studentIds: [$studentUser->id],
        scheduleDate: $scheduleDate,
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::NONE,
        recurrenceEndDate: null,
        isGroup: false,
        occurrenceCount: null,
        occurrenceDates: null,
        notes: null,
        locationDetails: null,
        durationMinutes: 60,
    )))->toThrow(ScheduleOverlapException::class);
});
