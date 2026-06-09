<?php

use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Enums\SchoolStatus;
use App\Infrastructure\Repositories\EloquentSchoolRepository;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(EloquentSchoolRepository::class);
});

test('repository creates school', function () {
    $manager = User::factory()->admin()->create();
    $dto = CreateSchoolDTO::fromArray([
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

    $school = $this->repository->create($dto);

    expect($school->id)->not()->toBeNull()
        ->and($school->display_name)->toBe('Display School');
});

test('repository updates school', function () {
    $school = School::factory()->create();
    $dto = UpdateSchoolDTO::fromArray([
        'full_name' => 'Updated Name',
        'display_name' => 'Updated Display',
        'address' => '456 Update',
        'state' => 'NY',
        'timezone' => 'America/New_York',
        'manager_id' => $school->manager_id,
        'contact_first_name' => 'New',
        'contact_last_name' => 'Person',
        'contact_phone' => '555-111-2222',
        'contact_email' => 'new@example.com',
        'invoice_email' => 'inv@example.com',
        'school_type' => 'Blended',
        'is_private_student' => false,
        'non_billable_scheduling' => true,
        'external_emr_name' => null,
    ]);

    $updated = $this->repository->update($school, $dto);

    expect($updated->display_name)->toBe('Updated Display')
        ->and($updated->non_billable_scheduling)->toBeTrue();
});

test('repository changes status', function () {
    $school = School::factory()->create(['status' => SchoolStatus::ACTIVE]);
    $dto = ChangeSchoolStatusDTO::fromArray([
        'status' => 'inactive',
        'reason' => 'Testing',
    ]);

    $updated = $this->repository->changeStatus($school, $dto);

    expect($updated->status)->toBe(SchoolStatus::INACTIVE)
        ->and($updated->status_reason)->toBe('Testing');
});

test('repository filters by status and metrics', function () {
    School::factory()->count(2)->create(['status' => SchoolStatus::ACTIVE]);
    School::factory()->count(1)->create(['status' => SchoolStatus::INACTIVE]);

    $filters = SchoolFilterDTO::fromArray(['status' => 'inactive']);
    $results = $this->repository->paginate($filters);

    expect($results->total())->toBe(1);

    $metrics = $this->repository->metrics();
    expect($metrics)->toMatchArray([
        'total' => 3,
        'active' => 2,
        'inactive' => 1,
    ]);
});

test('repository lists school timezones as an id => timezone map', function () {
    $a = School::factory()->create(['timezone' => 'America/Los_Angeles']);
    $b = School::factory()->create(['timezone' => 'America/Chicago']);

    $map = $this->repository->listSchoolTimezones();

    expect($map[$a->id])->toBe('America/Los_Angeles')
        ->and($map[$b->id])->toBe('America/Chicago');
});
