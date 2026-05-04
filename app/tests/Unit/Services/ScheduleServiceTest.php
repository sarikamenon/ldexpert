<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

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
use App\Models\Service;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $repository;

    private MockInterface $timezoneService;

    private MockInterface $userRepository;

    private MockInterface $serviceRepository;

    private MockInterface $studentRepository;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->repository = Mockery::mock(ScheduleRepositoryInterface::class);
        $this->timezoneService = Mockery::mock(UserTimezoneService::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->serviceRepository = Mockery::mock(ServiceRepositoryInterface::class);
        $this->studentRepository = Mockery::mock(StudentRepositoryInterface::class);
    }

    public function test_create_single_non_recurring_schedule_creates_one_record(): void
    {
        $therapist = User::factory()->create();
        $studentUser = User::factory()->create();
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);
        $service = Service::factory()->create([
            'is_group_service' => false,
        ]);

        $this->repository->shouldReceive('validateTherapistAccessToSSA')
            ->andReturnTrue();

        $this->repository->shouldReceive('validateTherapistAccessToStudents')
            ->once()
            ->with($therapist, [$studentUser->id])
            ->andReturnTrue();

        $this->repository->shouldReceive('validateStudentsShareService')
            ->once()
            ->with($therapist, [$studentUser->id], $service->id)
            ->andReturnTrue();

        $this->timezoneService->shouldReceive('parseUserLocalToUtc')
            ->once()
            ->andReturnUsing(function ($dateTimeStr) {
                return Carbon::parse($dateTimeStr);
            });

        $this->repository->shouldReceive('hasOverlap')
            ->times(2) // Therapist + 1 Student
            ->andReturnFalse();

        $this->userRepository->shouldReceive('findByIds')
            ->once()
            ->with([$studentUser->id])
            ->andReturn(collect([$studentUser]));

        $this->serviceRepository->shouldReceive('findOrFail')
            ->once()
            ->with($service->id)
            ->andReturn($service);

        $this->studentRepository->shouldReceive('getSchoolIdByUserId')
            ->once()
            ->with($studentUser->id)
            ->andReturn(null);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $data) {
                $schedule = new Schedule($data);
                $schedule->id = 1;

                return $schedule;
            });

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $dto = new CreateScheduleDTO(
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
        );

        $schedule = $serviceLayer->createSchedule($therapist, $dto);

        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertSame($therapist->id, $schedule->therapist_id);
        $this->assertSame($studentUser->id, $schedule->student_id);
        $this->assertSame($service->id, $schedule->service_id);
        $this->assertSame(ScheduleStatus::SCHEDULED, $schedule->status);
        $this->assertSame(BillingStatus::PENDING, $schedule->billing_status);
    }

    public function test_create_group_schedule_validates_shared_service(): void
    {
        $therapist = User::factory()->create();
        $studentUser1 = User::factory()->create();
        $studentUser2 = User::factory()->create();
        StudentProfile::factory()->create(['user_id' => $studentUser1->id]);
        StudentProfile::factory()->create(['user_id' => $studentUser2->id]);
        $service = Service::factory()->create([
            'is_group_service' => true,
            'is_direct_service' => true,
        ]);

        $this->repository->shouldReceive('validateTherapistAccessToSSA')
            ->andReturnTrue();

        $this->repository->shouldReceive('validateTherapistAccessToStudents')
            ->once()
            ->with($therapist, [$studentUser1->id, $studentUser2->id])
            ->andReturnTrue();

        $this->repository->shouldReceive('validateStudentsShareService')
            ->once()
            ->with($therapist, [$studentUser1->id, $studentUser2->id], $service->id)
            ->andReturnTrue();

        $this->timezoneService->shouldReceive('parseUserLocalToUtc')
            ->once()
            ->andReturnUsing(function ($dateTimeStr) {
                return Carbon::parse($dateTimeStr);
            });

        $this->repository->shouldReceive('hasOverlap')
            ->times(3) // Therapist + 2 Students
            ->andReturnFalse();

        $this->repository->shouldReceive('generateBatchNumber')
            ->once()
            ->with('group')
            ->andReturn('GRP-123');

        $this->userRepository->shouldReceive('findByIds')
            ->once()
            ->with([$studentUser1->id, $studentUser2->id])
            ->andReturn(collect([$studentUser1, $studentUser2]));

        $this->serviceRepository->shouldReceive('findOrFail')
            ->once()
            ->with($service->id)
            ->andReturn($service);

        $this->studentRepository->shouldReceive('getSchoolIdByUserId')
            ->twice()
            ->andReturn(null);

        $this->repository->shouldReceive('create')
            ->twice()
            ->andReturnUsing(function (array $data) {
                $schedule = new Schedule($data);
                $schedule->id = $schedule->student_id;

                return $schedule;
            });

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $dto = new CreateScheduleDTO(
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
        );

        $schedule = $serviceLayer->createSchedule($therapist, $dto);

        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertSame($studentUser1->id, $schedule->student_id);
        $this->assertSame('GRP-123', $schedule->group_batch_number);
    }

    public function test_generate_batch_number_delegates_to_repository(): void
    {
        $this->repository->shouldReceive('getSchedulesForTherapist')->andReturn(collect());
        $this->repository->shouldReceive('getPendingCount')->andReturn(0);
        $this->repository->shouldReceive('getSchoolsForTherapist')->andReturn(collect());
        $this->repository->shouldReceive('getStudentsForTherapist')->andReturn(collect());

        $this->repository->shouldReceive('generateBatchNumber')
            ->once()
            ->with('recurring')
            ->andReturn('REC-123');

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $batch = $serviceLayer->generateBatchNumber('recurring');

        $this->assertSame('REC-123', $batch);
    }

    public function test_update_schedule_updates_single_record(): void
    {
        $therapist = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::NONE,
            'recurring_batch_number' => null,
            'schedule_date' => '2025-01-02',
        ]);

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $this->timezoneService->shouldReceive('parseUserLocalToUtc')
            ->once()
            ->andReturnUsing(fn ($dt) => Carbon::parse($dt));

        // Batch number is null for non-recurring schedule so exclusion is null
        $this->repository->shouldReceive('hasOverlap')
            ->twice()
            ->andReturnFalse();

        $this->userRepository->shouldReceive('findById')
            ->once()
            ->with($schedule->student_id)
            ->andReturn(User::find($schedule->student_id));

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($schedule);

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $dto = new UpdateScheduleDTO(
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
        );

        $updatedSchedule = $serviceLayer->updateSchedule($therapist, $schedule->id, $dto);

        $this->assertSame($schedule->id, $updatedSchedule->id);
    }

    public function test_update_schedule_excludes_own_batch_from_overlap_check(): void
    {
        $therapist = User::factory()->create();
        $batchNumber = 'REC-TEST-001';

        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::WEEKLY,
            'recurring_batch_number' => $batchNumber,
            'parent_schedule_id' => null,
            'schedule_date' => '2025-01-06',
        ]);

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $this->timezoneService->shouldReceive('parseUserLocalToUtc')
            ->once()
            ->andReturnUsing(fn ($dt) => Carbon::parse($dt));

        // Both overlap checks must receive the batch number on the exclusions DTO.
        $batchPassedToOverlap = [];
        $this->repository->shouldReceive('hasOverlap')
            ->twice()
            ->andReturnUsing(function (User $user, OverlapCheckDTO $check, OverlapExclusionsDTO $exclusions) use (&$batchPassedToOverlap): bool {
                $batchPassedToOverlap[] = $exclusions->batchNumber;

                return false;
            });

        $this->userRepository->shouldReceive('findById')
            ->once()
            ->with($schedule->student_id)
            ->andReturn(User::find($schedule->student_id));

        // Recurrence type unchanged — no siblings deleted, no regeneration
        $this->repository->shouldNotReceive('getUnbilledFutureRecurringOccurrencesByBatch');
        $this->repository->shouldNotReceive('generateRecurringOccurrences');

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($schedule);

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $dto = new UpdateScheduleDTO(
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
        );

        $updatedSchedule = $serviceLayer->updateSchedule($therapist, $schedule->id, $dto);

        $this->assertSame($schedule->id, $updatedSchedule->id);
        $this->assertCount(2, $batchPassedToOverlap, 'hasOverlap must be called twice');
        $this->assertSame($batchNumber, $batchPassedToOverlap[0], 'Therapist overlap check must exclude the batch');
        $this->assertSame($batchNumber, $batchPassedToOverlap[1], 'Student overlap check must exclude the batch');
    }

    public function test_update_schedule_deletes_future_siblings_and_regenerates_when_recurrence_changes(): void
    {
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

        $updatedSchedule = new Schedule([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::WEEKLY,
            'recurring_batch_number' => 'REC-NEW-003',
            'parent_schedule_id' => null,
            'schedule_date' => '2025-01-06',
            'recurrence_end_date' => '2025-03-31',
            'is_group' => false,
        ]);
        $updatedSchedule->id = $schedule->id;

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $this->timezoneService->shouldReceive('parseUserLocalToUtc')
            ->once()
            ->andReturnUsing(fn ($dt) => Carbon::parse($dt));

        $this->repository->shouldReceive('hasOverlap')
            ->twice()
            ->andReturnFalse();

        $this->userRepository->shouldReceive('findById')
            ->once()
            ->andReturn(User::find($schedule->student_id));

        // Future siblings must be deleted before regeneration
        $this->repository->shouldReceive('getUnbilledFutureRecurringOccurrencesByBatch')
            ->once()
            ->with($batchNumber, $schedule->schedule_date->format('Y-m-d'))
            ->andReturn(collect([$sibling]));

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($sibling);

        $this->repository->shouldReceive('generateBatchNumber')
            ->once()
            ->with('recurring')
            ->andReturn('REC-NEW-003');

        // Return a fully persisted Schedule so Carbon casts work during regeneration
        $this->repository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function () use ($schedule): Schedule {
                $schedule->recurrence_type = RecurrenceType::WEEKLY;
                $schedule->recurring_batch_number = 'REC-NEW-003';
                $schedule->recurrence_end_date = \Carbon\Carbon::parse('2025-03-31');
                $schedule->parent_schedule_id = null;

                return $schedule;
            });

        // Regeneration: stub everything generateRecurringOccurrences touches.
        $this->timezoneService->shouldReceive('toUserTimezone')->andReturnUsing(fn ($dt) => $dt);
        $this->timezoneService->shouldReceive('parseUserLocalToUtc')->andReturnUsing(fn ($dt) => Carbon::parse($dt));
        $this->userRepository->shouldReceive('findById')->andReturn($therapist);
        $this->userRepository->shouldReceive('findByIds')->andReturn(collect([$therapist]));
        $this->studentRepository->shouldReceive('getSchoolIdByUserId')->andReturn(null);
        $this->repository->shouldReceive('hasOverlap')->andReturnFalse();
        $this->repository->shouldReceive('create')->andReturnUsing(function (array $data) {
            $s = new Schedule($data);
            $s->id = rand(100, 999);

            return $s;
        });

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $dto = new UpdateScheduleDTO(
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
        );

        $result = $serviceLayer->updateSchedule($therapist, $schedule->id, $dto);

        $this->assertSame($schedule->id, $result->id);
        $this->assertSame('REC-NEW-003', $result->recurring_batch_number);
        $this->assertSame(RecurrenceType::WEEKLY, $result->recurrence_type);
    }

    public function test_update_schedule_to_none_clears_recurring_fields_and_deletes_future_siblings(): void
    {
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

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $this->timezoneService->shouldReceive('parseUserLocalToUtc')
            ->once()
            ->andReturnUsing(fn ($dt) => Carbon::parse($dt));

        $this->repository->shouldReceive('hasOverlap')
            ->twice()
            ->andReturnFalse();

        $this->userRepository->shouldReceive('findById')
            ->once()
            ->andReturn(User::find($schedule->student_id));

        $this->repository->shouldReceive('getUnbilledFutureRecurringOccurrencesByBatch')
            ->once()
            ->with($batchNumber, $schedule->schedule_date->format('Y-m-d'))
            ->andReturn(collect([$sibling]));

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($sibling);

        // No new batch generated when switching to NONE
        $this->repository->shouldNotReceive('generateBatchNumber');

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($clearedSchedule);

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $dto = new UpdateScheduleDTO(
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
        );

        $result = $serviceLayer->updateSchedule($therapist, $schedule->id, $dto);

        $this->assertSame($schedule->id, $result->id);
        $this->assertNull($result->recurring_batch_number);
        $this->assertNull($result->recurrence_end_date);
    }

    public function test_delete_schedule_removes_single_record(): void
    {
        $therapist = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::NONE,
        ]);

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($schedule);

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $serviceLayer->deleteSchedule($therapist, $schedule->id);
    }

    public function test_delete_schedule_only_removes_single_record_even_if_recurring(): void
    {
        $therapist = User::factory()->create();
        $batchId = 'REC-123';

        $parent = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::WEEKLY,
            'recurring_batch_number' => $batchId,
            'parent_schedule_id' => null,
        ]);

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $parent->id)
            ->andReturn($parent);

        $this->repository->shouldNotReceive('getRecurringOccurrencesByBatch');

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($parent);

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $serviceLayer->deleteSchedule($therapist, $parent->id);
    }

    public function test_delete_schedule_throws_when_billed(): void
    {
        $therapist = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::NONE,
            'billing_status' => BillingStatus::BILLED,
        ]);

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $this->repository->shouldNotReceive('delete');

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $this->expectException(CannotDeleteBilledScheduleException::class);
        $this->expectExceptionMessage('Cannot delete a schedule that has already been billed.');

        $serviceLayer->deleteSchedule($therapist, $schedule->id);
    }

    public function test_delete_future_recurring_schedules_deletes_current_and_future_but_not_past(): void
    {
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

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $currentSchedule->id)
            ->andReturn($currentSchedule);

        // Repository only returns current + future (unbilled), not past
        $this->repository->shouldReceive('getUnbilledFutureRecurringOccurrencesByBatch')
            ->once()
            ->with($batchId, $today)
            ->andReturn(collect([$currentSchedule, $futureSchedule]));

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($currentSchedule);

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($futureSchedule);

        // Past schedule must NOT be deleted
        $this->repository->shouldNotReceive('delete')
            ->with($pastSchedule);

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $count = $serviceLayer->deleteFutureRecurringSchedules($therapist, $currentSchedule->id);

        $this->assertEquals(2, $count);
    }

    public function test_delete_future_recurring_schedules_returns_zero_when_no_batch(): void
    {
        $therapist = User::factory()->create();

        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::NONE,
            'recurring_batch_number' => null,
        ]);

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $this->repository->shouldNotReceive('getUnbilledFutureRecurringOccurrencesByBatch');
        $this->repository->shouldNotReceive('delete');

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $count = $serviceLayer->deleteFutureRecurringSchedules($therapist, $schedule->id);

        $this->assertEquals(0, $count);
    }

    public function test_delete_future_recurring_schedules_returns_zero_when_not_found(): void
    {
        $therapist = User::factory()->create();

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, 999)
            ->andReturnNull();

        $this->repository->shouldNotReceive('getUnbilledFutureRecurringOccurrencesByBatch');
        $this->repository->shouldNotReceive('delete');

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $count = $serviceLayer->deleteFutureRecurringSchedules($therapist, 999);

        $this->assertEquals(0, $count);
    }

    public function test_create_schedule_throws_exception_on_therapist_overlap(): void
    {
        $therapist = User::factory()->create();
        $studentUser = User::factory()->create();
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);
        $service = Service::factory()->create(['is_group_service' => false]);

        $this->repository->shouldReceive('validateTherapistAccessToSSA')->andReturnTrue();
        $this->repository->shouldReceive('validateTherapistAccessToStudents')->andReturnTrue();
        $this->repository->shouldReceive('validateStudentsShareService')->andReturnTrue();

        $this->timezoneService->shouldReceive('parseUserLocalToUtc')
            ->once()
            ->andReturnUsing(fn ($dt) => Carbon::parse($dt));

        $scheduleDate = now()->addWeek()->format('Y-m-d');

        // Simulate overlap for therapist — DTOs carry date/time/exclusions
        $this->repository->shouldReceive('hasOverlap')
            ->once()
            ->with(
                $therapist,
                Mockery::on(fn (OverlapCheckDTO $c) => $c->date === $scheduleDate && $c->startTime === '09:00:00' && $c->endTime === '10:00:00'),
                Mockery::on(fn (OverlapExclusionsDTO $e) => $e->scheduleId === null && $e->batchNumber === null),
            )
            ->andReturnTrue();

        $this->userRepository->shouldReceive('findByIds')
            ->once()
            ->with([$studentUser->id])
            ->andReturn(collect([$studentUser]));

        $this->serviceRepository->shouldReceive('findOrFail')
            ->once()
            ->with($service->id)
            ->andReturn($service);

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $dto = new CreateScheduleDTO(
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
        );

        $this->expectException(ScheduleOverlapException::class);
        $serviceLayer->createSchedule($therapist, $dto);
    }

    public function test_create_schedule_throws_exception_on_student_overlap(): void
    {
        $therapist = User::factory()->create();
        $studentUser = User::factory()->create();
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);
        $service = Service::factory()->create(['is_group_service' => false]);

        $this->repository->shouldReceive('validateTherapistAccessToSSA')->andReturnTrue();
        $this->repository->shouldReceive('validateTherapistAccessToStudents')->andReturnTrue();
        $this->repository->shouldReceive('validateStudentsShareService')->andReturnTrue();

        $this->timezoneService->shouldReceive('parseUserLocalToUtc')
            ->once()
            ->andReturnUsing(fn ($dt) => Carbon::parse($dt));

        $scheduleDate = now()->addWeek()->format('Y-m-d');
        $checkMatcher = Mockery::on(fn (OverlapCheckDTO $c) => $c->date === $scheduleDate && $c->startTime === '09:00:00' && $c->endTime === '10:00:00');
        $noExclusionsMatcher = Mockery::on(fn (OverlapExclusionsDTO $e) => $e->scheduleId === null && $e->batchNumber === null);

        // No overlap for therapist
        $this->repository->shouldReceive('hasOverlap')
            ->once()
            ->with($therapist, $checkMatcher, $noExclusionsMatcher)
            ->andReturnFalse();

        $this->userRepository->shouldReceive('findByIds')
            ->once()
            ->with([$studentUser->id])
            ->andReturn(collect([$studentUser]));

        // Overlap for student
        $this->repository->shouldReceive('hasOverlap')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $studentUser->id), $checkMatcher, $noExclusionsMatcher)
            ->andReturnTrue();

        $this->serviceRepository->shouldReceive('findOrFail')
            ->once()
            ->with($service->id)
            ->andReturn($service);

        $serviceLayer = new ScheduleService(
            $this->repository,
            $this->timezoneService,
            $this->userRepository,
            $this->serviceRepository,
            $this->studentRepository
        );

        $dto = new CreateScheduleDTO(
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
        );

        $this->expectException(ScheduleOverlapException::class);
        $serviceLayer->createSchedule($therapist, $dto);
    }
}
