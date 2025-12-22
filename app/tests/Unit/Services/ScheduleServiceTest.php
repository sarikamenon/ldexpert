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
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
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
            'schedule_date' => '2025-01-02',
        ]);

        $this->repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $this->timezoneService->shouldReceive('parseUserLocalToUtc')
            ->once()
            ->andReturnUsing(function ($dateTimeStr) {
                return Carbon::parse($dateTimeStr);
            });

        $this->repository->shouldReceive('hasOverlap')
            ->times(2) // Therapist + Student (if resolved)
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

    public function test_delete_schedule_removes_series_if_recurring(): void
    {
        $therapist = User::factory()->create();
        $batchId = 'REC-123';

        $parent = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::WEEKLY,
            'recurring_batch_number' => $batchId,
            'parent_schedule_id' => null,
        ]);

        $occurrence = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::WEEKLY,
            'recurring_batch_number' => $batchId,
            'parent_schedule_id' => $parent->id,
        ]);

        $this->repository->shouldReceive('findForTherapist')
            ->with($therapist, $parent->id)
            ->andReturn($parent);

        $this->repository->shouldReceive('getRecurringOccurrencesByBatch')
            ->once()
            ->with($batchId)
            ->andReturn(collect([$occurrence]));

        $this->repository->shouldReceive('delete')
            ->once()
            ->with($occurrence);

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

        // Simulate overlap for therapist
        $this->repository->shouldReceive('hasOverlap')
            ->once()
            ->with($therapist, '2025-01-01', '09:00:00', '10:00:00', null)
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
            scheduleDate: '2025-01-01',
            startTime: '09:00',
            endTime: '10:00',
            recurrenceType: RecurrenceType::NONE,
            recurrenceEndDate: null,
            isGroup: false,
            occurrenceCount: null,
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

        // No overlap for therapist
        $this->repository->shouldReceive('hasOverlap')
            ->once()
            ->with($therapist, '2025-01-01', '09:00:00', '10:00:00', null)
            ->andReturnFalse();

        $this->userRepository->shouldReceive('findByIds')
            ->once()
            ->with([$studentUser->id])
            ->andReturn(collect([$studentUser]));

        // Overlap for student
        $this->repository->shouldReceive('hasOverlap')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $studentUser->id), '2025-01-01', '09:00:00', '10:00:00', null)
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
            scheduleDate: '2025-01-01',
            startTime: '09:00',
            endTime: '10:00',
            recurrenceType: RecurrenceType::NONE,
            recurrenceEndDate: null,
            isGroup: false,
            occurrenceCount: null,
            notes: null,
            locationDetails: null,
            durationMinutes: 60,
        );

        $this->expectException(ScheduleOverlapException::class);
        $serviceLayer->createSchedule($therapist, $dto);
    }
}
