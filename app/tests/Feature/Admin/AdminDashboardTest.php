<?php

declare(strict_types=1);

use App\Enums\ContractStatus;
use App\Enums\SSAStatus;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes upcomingSchoolContracts and upcomingSSAs view variables', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertViewHas('upcomingSchoolContracts')
        ->assertViewHas('upcomingSSAs');
});

it('includes school contract expiring today in upcomingSchoolContracts', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create([
        'display_name' => 'Today Expiring School',
        'is_private_student' => true,
        'is_auto_extend' => true,
    ]);

    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $contracts = $response->viewData('upcomingSchoolContracts');
    $entities = collect($contracts)->pluck('entity')->all();

    expect($entities)->toContain('Today Expiring School');

    $event = collect($contracts)->firstWhere('entity', 'Today Expiring School');
    expect($event['is_private_student'])->toBeTrue()
        ->and($event['is_auto_extend'])->toBeTrue();
});

it('includes SSA expiring today in upcomingSSAs', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create();
    $student = User::factory()->student()->create();
    StudentProfile::where('user_id', $student->id)->update(['school_id' => $school->id]);

    $service = Service::factory()->create();
    ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'status' => SSAStatus::ACTIVE->value,
        'end_date' => now()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $ssas = $response->viewData('upcomingSSAs');
    expect($ssas)->not->toBeEmpty();
});

it('excludes contracts expiring beyond 30 days', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create(['display_name' => 'Far Future School']);

    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(60)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $contracts = $response->viewData('upcomingSchoolContracts');
    $entities = collect($contracts)->pluck('entity')->all();

    expect($entities)->not->toContain('Far Future School');
});

it('excludes already-expired contracts (before today)', function () {
    $admin = User::factory()->admin()->create();
    $school = School::factory()->create(['display_name' => 'Already Expired School']);

    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYears(2)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $contracts = $response->viewData('upcomingSchoolContracts');
    $entities = collect($contracts)->pluck('entity')->all();

    expect($entities)->not->toContain('Already Expired School');
});
