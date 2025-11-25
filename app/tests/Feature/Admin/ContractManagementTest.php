<?php

declare(strict_types=1);

use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Enums\SchoolStatus;
use App\Enums\ServiceStatus;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\TherapistContract;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::factory()->admin()->create();
}

function activeSchool(): School
{
    return School::factory()->create([
        'status' => SchoolStatus::ACTIVE->value,
    ]);
}

function activeService(): Service
{
    return Service::factory()->create([
        'status' => ServiceStatus::ACTIVE->value,
    ]);
}

function therapistProfile(): TherapistProfile
{
    return TherapistProfile::factory()->create();
}

it('allows admin to view school contracts index', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->get(route('admin.contracts.schools.index'))
        ->assertOk()
        ->assertSee('School Contracts');
});

it('creates a school contract with services', function () {
    $admin = adminUser();
    $school = activeSchool();
    $services = Service::factory()->count(2)->create([
        'status' => ServiceStatus::ACTIVE->value,
    ]);

    $payload = [
        'school_id' => $school->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'notes' => 'Quarterly agreement',
        'services' => [
            [
                'service_id' => $services[0]->id,
                'rate' => '125.00',
                'rate_type' => RateType::HOURLY->value,
            ],
            [
                'service_id' => $services[1]->id,
                'rate' => '400.00',
                'rate_type' => RateType::FLAT->value,
            ],
        ],
    ];

    $this->actingAs($admin)
        ->post(route('admin.contracts.schools.store'), $payload)
        ->assertRedirect(route('admin.contracts.schools.index'))
        ->assertSessionHas('status', 'School contract created successfully.');

    $this->assertDatabaseHas('school_contracts', [
        'school_id' => $school->id,
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $this->assertDatabaseHas('school_contract_services', [
        'rate_type' => RateType::HOURLY->value,
        'rate' => '125.00',
    ]);
});

it('prevents overlapping active school contracts', function () {
    $admin = adminUser();
    $school = activeSchool();
    $service = activeService();

    $existing = SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);
    $existing->services()->create([
        'service_id' => $service->id,
        'rate' => '100.00',
        'rate_type' => RateType::HOURLY->value,
    ]);

    $payload = [
        'school_id' => $school->id,
        'start_date' => now()->addWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'services' => [
            [
                'service_id' => $service->id,
                'rate' => '150.00',
                'rate_type' => RateType::HOURLY->value,
            ],
        ],
    ];

    $this->actingAs($admin)
        ->from(route('admin.contracts.schools.create'))
        ->post(route('admin.contracts.schools.store'), $payload)
        ->assertRedirect(route('admin.contracts.schools.create'))
        ->assertSessionHasErrors('start_date');
});

it('creates a therapist contract with services', function () {
    $admin = adminUser();
    $therapist = therapistProfile();
    $service = activeService();

    $payload = [
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeeks(6)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
        'services' => [
            [
                'service_id' => $service->id,
                'rate' => '90.00',
                'rate_type' => RateType::HOURLY->value,
            ],
        ],
    ];

    $this->actingAs($admin)
        ->post(route('admin.contracts.therapists.store'), $payload)
        ->assertRedirect(route('admin.contracts.therapists.index'))
        ->assertSessionHas('status', 'Therapist contract created successfully.');

    $this->assertDatabaseHas('therapist_contracts', [
        'therapist_id' => $therapist->id,
        'status' => ContractStatus::ACTIVE->value,
    ]);
});

it('blocks overlapping therapist contracts when activating', function () {
    $admin = adminUser();
    $therapist = therapistProfile();
    $service = activeService();

    $activeContract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);
    $activeContract->services()->create([
        'service_id' => $service->id,
        'rate' => '85.00',
        'rate_type' => RateType::HOURLY->value,
    ]);

    $inactiveContract = TherapistContract::create([
        'therapist_id' => $therapist->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'status' => ContractStatus::INACTIVE->value,
    ]);
    $inactiveContract->services()->create([
        'service_id' => $service->id,
        'rate' => '95.00',
        'rate_type' => RateType::HOURLY->value,
    ]);

    $this->actingAs($admin)
        ->patchJson(route('admin.contracts.therapists.status', $inactiveContract), [
            'status' => ContractStatus::ACTIVE->value,
        ])
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
        ]);
});
