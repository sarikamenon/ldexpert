<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Domain\Therapist\Services\ScheduleService;
use App\DTOs\CreateScheduleDTO;
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

final class ScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    public function test_create_single_non_recurring_schedule_creates_one_record(): void
    {
        $therapist = User::factory()->create();
        $studentUser = User::factory()->create();
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);
        $service = Service::factory()->create([
            'is_group_service' => false,
        ]);

        $repository = Mockery::mock(ScheduleRepositoryInterface::class);

        $repository->shouldReceive('validateTherapistAccessToSSA')
            ->andReturnTrue();

        $repository->shouldReceive('validateTherapistAccessToStudents')
            ->once()
            ->with($therapist, [$studentUser->id])
            ->andReturnTrue();

        $repository->shouldReceive('validateStudentsShareService')
            ->once()
            ->with($therapist, [$studentUser->id], $service->id)
            ->andReturnTrue();

        $repository->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $data) {
                $schedule = new Schedule($data);
                $schedule->id = 1;

                return $schedule;
            });

        $serviceLayer = new ScheduleService($repository);

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

        $repository = Mockery::mock(ScheduleRepositoryInterface::class);

        $repository->shouldReceive('validateTherapistAccessToSSA')
            ->andReturnTrue();

        $repository->shouldReceive('validateTherapistAccessToStudents')
            ->once()
            ->with($therapist, [$studentUser1->id, $studentUser2->id])
            ->andReturnTrue();

        $repository->shouldReceive('validateStudentsShareService')
            ->once()
            ->with($therapist, [$studentUser1->id, $studentUser2->id], $service->id)
            ->andReturnTrue();

        $repository->shouldReceive('generateBatchNumber')
            ->once()
            ->with('group')
            ->andReturn('GRP-123');

        $repository->shouldReceive('create')
            ->twice()
            ->andReturnUsing(function (array $data) {
                $schedule = new Schedule($data);
                $schedule->id = $schedule->student_id;

                return $schedule;
            });

        $serviceLayer = new ScheduleService($repository);

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
        );

        $schedule = $serviceLayer->createSchedule($therapist, $dto);

        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertSame($studentUser1->id, $schedule->student_id);
        $this->assertSame('GRP-123', $schedule->group_batch_number);
    }

    public function test_generate_batch_number_delegates_to_repository(): void
    {
        $repository = Mockery::mock(ScheduleRepositoryInterface::class);

        $repository->shouldReceive('getSchedulesForTherapist')->andReturn(collect());
        $repository->shouldReceive('getPendingCount')->andReturn(0);
        $repository->shouldReceive('getSchoolsForTherapist')->andReturn(collect());
        $repository->shouldReceive('getStudentsForTherapist')->andReturn(collect());

        $repository->shouldReceive('generateBatchNumber')
            ->once()
            ->with('recurring')
            ->andReturn('REC-123');

        $serviceLayer = new ScheduleService($repository);

        $batch = $serviceLayer->generateBatchNumber('recurring');

        $this->assertSame('REC-123', $batch);
    }

    public function test_update_schedule_updates_single_record(): void
    {
        $therapist = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::NONE,
        ]);

        $repository = Mockery::mock(ScheduleRepositoryInterface::class);

        $repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $repository->shouldReceive('update')
            ->once()
            ->andReturn($schedule);

        $serviceLayer = new ScheduleService($repository);

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
        );

        $updatedSchedule = $serviceLayer->updateSchedule($therapist, $schedule->id, $dto);

        $this->assertSame($schedule->id, $updatedSchedule->id);
    }

    public function test_update_schedule_regenerates_occurrences_when_recurrence_type_changes(): void
    {
        $therapist = User::factory()->create();
        $studentUser = User::factory()->create();
        StudentProfile::factory()->create(['user_id' => $studentUser->id]);

        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'student_id' => $studentUser->id,
            'recurrence_type' => RecurrenceType::NONE,
            'schedule_date' => '2025-01-01',
        ]);

        $repository = Mockery::mock(ScheduleRepositoryInterface::class);

        $repository->shouldReceive('findForTherapist')
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        // Should generate new batch number
        $repository->shouldReceive('generateBatchNumber')
            ->once()
            ->with('recurring')
            ->andReturn('REC-NEW');

        // Update the schedule
        $repository->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($schedule, $data) {
                $schedule->fill($data);
                return $schedule;
            });

        // Create 2 new occurrences (weekly for 3 weeks total)
        $repository->shouldReceive('create')
            ->twice()
            ->andReturn(new Schedule());

        $serviceLayer = new ScheduleService($repository);

        $dto = new UpdateScheduleDTO(
            ssaId: null,
            serviceId: null,
            studentIds: null,
            scheduleDate: '2025-01-01',
            startTime: '09:00',
            endTime: '10:00',
            recurrenceType: RecurrenceType::WEEKLY,
            recurrenceEndDate: '2025-01-15', // 3 weeks: Jan 1, Jan 8, Jan 15
            isGroup: null,
            locationDetails: null,
            notes: null,
            billingStatus: null,
        );

        $updatedSchedule = $serviceLayer->updateSchedule($therapist, $schedule->id, $dto);

        $this->assertSame(RecurrenceType::WEEKLY, $updatedSchedule->recurrence_type);
        $this->assertSame('REC-NEW', $updatedSchedule->recurring_batch_number);
    }

    public function test_delete_schedule_removes_single_record(): void
    {
        $therapist = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'therapist_id' => $therapist->id,
            'recurrence_type' => RecurrenceType::NONE,
        ]);

        $repository = Mockery::mock(ScheduleRepositoryInterface::class);

        $repository->shouldReceive('findForTherapist')
            ->once()
            ->with($therapist, $schedule->id)
            ->andReturn($schedule);

        $repository->shouldReceive('delete')
            ->once()
            ->with($schedule);

        $serviceLayer = new ScheduleService($repository);

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

        $repository = Mockery::mock(ScheduleRepositoryInterface::class);

        $repository->shouldReceive('findForTherapist')
            ->with($therapist, $parent->id)
            ->andReturn($parent);

        $repository->shouldReceive('getRecurringOccurrencesByBatch')
            ->once()
            ->with($batchId)
            ->andReturn(collect([$occurrence]));

        $repository->shouldReceive('delete')
            ->once()
            ->with($occurrence);

        $repository->shouldReceive('delete')
            ->once()
            ->with($parent);

        $serviceLayer = new ScheduleService($repository);

        $serviceLayer->deleteSchedule($therapist, $parent->id);
    }
}
