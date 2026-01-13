<?php

use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\School\Services\SchoolService;
use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Enums\SchoolStatus;
use App\Models\School;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery\MockInterface;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(SchoolRepositoryInterface::class);
    $this->activityLog = Mockery::mock(ActivityLogService::class);
    $this->service = new SchoolService($this->repository, $this->activityLog);
});

afterEach(function () {
    Mockery::close();
});

test('school service delegates listing and metrics', function () {
    $filters = SchoolFilterDTO::fromArray([]);
    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $this->repository->shouldReceive('paginate')->once()->with($filters, 25)->andReturn($paginator);
    $this->repository->shouldReceive('metrics')->once()->andReturn(['total' => 1, 'active' => 1, 'inactive' => 0]);
    $this->repository->shouldReceive('export')->once()->with($filters)->andReturn(Collection::make());

    expect($this->service->listSchools($filters))->toBe($paginator);
    expect($this->service->summaryMetrics())->toMatchArray(['total' => 1]);
    expect($this->service->exportSchools($filters))->toBeInstanceOf(Collection::class);
});

test('school service wraps writes via repository', function () {
    $manager = User::factory()->admin()->create();
    $school = School::factory()->make(['manager_id' => $manager->id]);
    $createDto = CreateSchoolDTO::fromArray([
        'full_name' => 'Full School',
        'display_name' => 'Display School',
        'address' => '123 Main',
        'state' => 'CA',
        'timezone' => 'America/Los_Angeles',
        'manager_id' => $manager->id,
        'contact_first_name' => 'Jane',
        'contact_last_name' => 'Doe',
        'contact_phone' => '555-555-5555',
        'contact_email' => 'jane@example.com',
        'invoice_email' => 'billing@example.com',
        'school_type' => 'Virtual',
        'is_private_student' => true,
        'non_billable_scheduling' => false,
        'external_emr_name' => 'EMR X',
    ]);

    $this->repository->shouldReceive('create')->once()->andReturn($school);
    $this->repository->shouldReceive('update')->once()->andReturn($school);
    $this->repository->shouldReceive('changeStatus')->once()->andReturn($school);
    $this->activityLog->shouldReceive('logCreated')->once()->with($school);
    $this->activityLog->shouldReceive('logUpdated')->zeroOrMoreTimes();
    $this->activityLog->shouldReceive('logStatusChanged')->once();

    $this->service->createSchool($createDto);
    $this->service->updateSchool($school, UpdateSchoolDTO::fromArray($createDto->toArray()));
    $this->service->changeStatus($school, ChangeSchoolStatusDTO::fromArray([
        'status' => SchoolStatus::INACTIVE->value,
        'reason' => 'Testing',
    ]));
});
