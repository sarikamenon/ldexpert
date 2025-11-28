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
use Mockery;
use Tests\TestCase;

final class ScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

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
            notes: null,
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
            notes: null,
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
}
