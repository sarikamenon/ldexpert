<?php

declare(strict_types=1);

use App\Enums\ContractStatus;
use App\Enums\SchoolStatus;
use App\Enums\ServiceStatus;
use App\Enums\UserStatus;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: base DataTables POST payload.
 *
 * @param  array<string, mixed>  $filters
 * @return array<string, mixed>
 */
function dtPayload(array $filters = []): array
{
    return array_merge([
        '_token' => csrf_token(),
        'draw' => 1,
        'start' => 0,
        'length' => 25,
        'search' => ['value' => '', 'regex' => 'false'],
    ], $filters);
}

// ─── Services ────────────────────────────────────────────────────────

it('services data returns only active services by default', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();

    $active = Service::factory()->create(['status' => ServiceStatus::ACTIVE->value]);
    $inactive = Service::factory()->create(['status' => ServiceStatus::INACTIVE->value]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.services.data'), dtPayload([
            'filter_status' => 'active',
        ]));

    $response->assertOk();
    $html = implode(' ', array_map('implode', $response->json('data')));
    expect($html)->toContain($active->name);
    expect($html)->not->toContain($inactive->name);
});

it('services data returns all services when status filter is empty', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();

    $active = Service::factory()->create(['status' => ServiceStatus::ACTIVE->value]);
    $inactive = Service::factory()->create(['status' => ServiceStatus::INACTIVE->value]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.services.data'), dtPayload([
            'filter_status' => '',
        ]));

    $response->assertOk();
    $html = implode(' ', array_map('implode', $response->json('data')));
    expect($html)->toContain($active->name);
    expect($html)->toContain($inactive->name);
});

it('services data returns only inactive services when filtered', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();

    $active = Service::factory()->create(['status' => ServiceStatus::ACTIVE->value]);
    $inactive = Service::factory()->create(['status' => ServiceStatus::INACTIVE->value]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.services.data'), dtPayload([
            'filter_status' => 'inactive',
        ]));

    $response->assertOk();
    $html = implode(' ', array_map('implode', $response->json('data')));
    expect($html)->not->toContain($active->name);
    expect($html)->toContain($inactive->name);
});

// ─── Schools ─────────────────────────────────────────────────────────

it('schools data returns only active schools by default', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();

    $active = School::factory()->create(['status' => SchoolStatus::ACTIVE->value]);
    $inactive = School::factory()->create(['status' => SchoolStatus::INACTIVE->value]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.schools.data'), dtPayload([
            'filter_status' => 'active',
        ]));

    $response->assertOk();
    $html = implode(' ', array_map('implode', $response->json('data')));
    expect($html)->toContain($active->display_name);
    expect($html)->not->toContain($inactive->display_name);
});

it('schools data returns all schools when status filter is empty', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();

    $active = School::factory()->create(['status' => SchoolStatus::ACTIVE->value]);
    $inactive = School::factory()->create(['status' => SchoolStatus::INACTIVE->value]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.schools.data'), dtPayload([
            'filter_status' => '',
        ]));

    $response->assertOk();
    $html = implode(' ', array_map('implode', $response->json('data')));
    expect($html)->toContain($active->display_name);
    expect($html)->toContain($inactive->display_name);
});

// ─── Therapists ──────────────────────────────────────────────────────

it('therapists data returns only active therapists by default', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();

    $activeTherapist = User::factory()->therapist()->create([
        'name' => 'ActiveTherapistUnique',
        'status' => UserStatus::ACTIVE->value,
    ]);
    TherapistProfile::factory()->create(['user_id' => $activeTherapist->id]);

    $inactiveTherapist = User::factory()->therapist()->create([
        'name' => 'InactiveTherapistUnique',
        'status' => UserStatus::INACTIVE->value,
    ]);
    TherapistProfile::factory()->create(['user_id' => $inactiveTherapist->id]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.therapists.data'), dtPayload([
            'filter_status' => 'active',
            'filter_search' => '',
            'filter_position_id' => '',
            'filter_school_id' => '',
            'filter_student_id' => '',
        ]));

    $response->assertOk();
    $html = implode(' ', array_map('implode', $response->json('data')));
    expect($html)->toContain('ActiveTherapistUnique');
    expect($html)->not->toContain('InactiveTherapistUnique');
});

it('therapists data returns all therapists when status filter is empty', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();

    $activeTherapist = User::factory()->therapist()->create([
        'name' => 'ActiveTherapistAll',
        'status' => UserStatus::ACTIVE->value,
    ]);
    TherapistProfile::factory()->create(['user_id' => $activeTherapist->id]);

    $inactiveTherapist = User::factory()->therapist()->create([
        'name' => 'InactiveTherapistAll',
        'status' => UserStatus::INACTIVE->value,
    ]);
    TherapistProfile::factory()->create(['user_id' => $inactiveTherapist->id]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.therapists.data'), dtPayload([
            'filter_status' => '',
            'filter_search' => '',
            'filter_position_id' => '',
            'filter_school_id' => '',
            'filter_student_id' => '',
        ]));

    $response->assertOk();
    $html = implode(' ', array_map('implode', $response->json('data')));
    expect($html)->toContain('ActiveTherapistAll');
    expect($html)->toContain('InactiveTherapistAll');
});

// ─── Students ────────────────────────────────────────────────────────

it('students data returns only active students by default', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();

    User::factory()->student()->create([
        'name' => 'ActiveStudentUnique',
        'status' => UserStatus::ACTIVE->value,
    ]);
    User::factory()->student()->create([
        'name' => 'InactiveStudentUnique',
        'status' => UserStatus::INACTIVE->value,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.students.data'), dtPayload([
            'filter_status' => 'active',
            'filter_search' => '',
            'filter_school_id' => '',
            'filter_therapist_id' => '',
        ]));

    $response->assertOk();
    $html = implode(' ', array_map('implode', $response->json('data')));
    expect($html)->toContain('ActiveStudentUnique');
    expect($html)->not->toContain('InactiveStudentUnique');
});

it('students data returns all students when status filter is empty', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();

    User::factory()->student()->create([
        'name' => 'ActiveStudentAll',
        'status' => UserStatus::ACTIVE->value,
    ]);
    User::factory()->student()->create([
        'name' => 'InactiveStudentAll',
        'status' => UserStatus::INACTIVE->value,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.students.data'), dtPayload([
            'filter_status' => '',
            'filter_search' => '',
            'filter_school_id' => '',
            'filter_therapist_id' => '',
        ]));

    $response->assertOk();
    $html = implode(' ', array_map('implode', $response->json('data')));
    expect($html)->toContain('ActiveStudentAll');
    expect($html)->toContain('InactiveStudentAll');
});

// ─── School Contracts ────────────────────────────────────────────────

it('school contracts data returns only active contracts by default', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create();

    $activeContract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $inactiveContract = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'status' => ContractStatus::INACTIVE->value,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.contracts.schools.data'), dtPayload([
            'filter_status' => 'active',
            'filter_school_id' => '',
        ]));

    $response->assertOk();
    expect($response->json('recordsFiltered'))->toBe(1);
});

it('school contracts data returns all contracts when status filter is empty', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create();

    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'status' => ContractStatus::INACTIVE->value,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.contracts.schools.data'), dtPayload([
            'filter_status' => '',
            'filter_school_id' => '',
        ]));

    $response->assertOk();
    expect($response->json('recordsFiltered'))->toBe(2);
});

// ─── Therapist Contracts ─────────────────────────────────────────────

it('therapist contracts data returns only active contracts by default', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();
    $profile = TherapistProfile::factory()->create();

    TherapistContract::create([
        'therapist_id' => $profile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    TherapistContract::create([
        'therapist_id' => $profile->id,
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'status' => ContractStatus::INACTIVE->value,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.contracts.therapists.data'), dtPayload([
            'filter_status' => 'active',
            'filter_search' => '',
            'filter_therapist_id' => '',
        ]));

    $response->assertOk();
    expect($response->json('recordsFiltered'))->toBe(1);
});

it('therapist contracts data returns all contracts when status filter is empty', function () {
    /** @var \Tests\TestCase $this */
    $admin = User::factory()->admin()->create();
    $profile = TherapistProfile::factory()->create();

    TherapistContract::create([
        'therapist_id' => $profile->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    TherapistContract::create([
        'therapist_id' => $profile->id,
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
        'status' => ContractStatus::INACTIVE->value,
    ]);

    $response = $this->actingAs($admin)
        ->postJson(route('admin.contracts.therapists.data'), dtPayload([
            'filter_status' => '',
            'filter_search' => '',
            'filter_therapist_id' => '',
        ]));

    $response->assertOk();
    expect($response->json('recordsFiltered'))->toBe(2);
});
