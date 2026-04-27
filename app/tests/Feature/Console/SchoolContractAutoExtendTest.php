<?php

declare(strict_types=1);

use App\Enums\ContractStatus;
use App\Enums\SchoolStatus;
use App\Enums\SSAStatus;
use App\Mail\SchoolContractAutoExtendedMail;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function autoExtendSchool(array $overrides = []): School
{
    $manager = User::factory()->admin()->create(['email' => 'manager@school.com']);

    return School::factory()->create(array_merge([
        'is_private_student' => true,
        'is_auto_extend' => true,
        'status' => SchoolStatus::ACTIVE->value,
        'manager_id' => $manager->id,
    ], $overrides));
}

function expiredContract(School $school, int $daysAgo = 0): SchoolContract
{
    return SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->subDays($daysAgo)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);
}

function activeStudentInSchool(School $school): User
{
    $student = User::factory()->student()->create();
    StudentProfile::where('user_id', $student->id)->update(['school_id' => $school->id]);

    return $student;
}

test('extends contract and SSAs for eligible school', function () {
    $school = autoExtendSchool();
    $contract = expiredContract($school, daysAgo: 0);
    $originalEndDate = $contract->end_date->copy();

    $student = activeStudentInSchool($school);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'status' => SSAStatus::ACTIVE->value,
        'end_date' => now()->toDateString(),
    ]);

    $this->artisan('school:auto-extend-contracts-ssas')
        ->assertExitCode(0)
        ->expectsOutputToContain('Extended: 1');

    $this->assertDatabaseHas('school_contracts', [
        'id' => $contract->id,
        'end_date' => $originalEndDate->addYear()->toDateString(),
    ]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'end_date' => now()->addYear()->toDateString(),
    ]);

    Mail::assertQueued(SchoolContractAutoExtendedMail::class);
});

test('skips school with no active contract', function () {
    $school = autoExtendSchool();

    $this->artisan('school:auto-extend-contracts-ssas')
        ->assertExitCode(0)
        ->expectsOutputToContain('Skipped: 1');

    Mail::assertNothingQueued();
});

test('skips contract not yet due', function () {
    $school = autoExtendSchool();
    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addDays(30)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $this->artisan('school:auto-extend-contracts-ssas')
        ->assertExitCode(0)
        ->expectsOutputToContain('Skipped: 1');

    Mail::assertNothingQueued();
});

test('skips school without is_auto_extend enabled', function () {
    $manager = User::factory()->admin()->create();
    $school = School::factory()->create([
        'is_private_student' => true,
        'is_auto_extend' => false,
        'status' => SchoolStatus::ACTIVE->value,
        'manager_id' => $manager->id,
    ]);
    expiredContract($school);

    $this->artisan('school:auto-extend-contracts-ssas')
        ->assertExitCode(0)
        ->expectsOutputToContain('No eligible schools found');

    Mail::assertNothingQueued();
});

test('targets specific school with --school option', function () {
    $schoolA = autoExtendSchool();
    $contractA = expiredContract($schoolA);

    $schoolB = autoExtendSchool();
    $contractB = expiredContract($schoolB);

    $this->artisan('school:auto-extend-contracts-ssas', ['--school' => $schoolA->id])
        ->assertExitCode(0)
        ->expectsOutputToContain('Extended: 1');

    $this->assertDatabaseMissing('school_contracts', [
        'id' => $contractA->id,
        'end_date' => $contractA->end_date->toDateString(),
    ]);

    $this->assertDatabaseHas('school_contracts', [
        'id' => $contractB->id,
        'end_date' => $contractB->end_date->toDateString(),
    ]);
});

test('dry run does not save changes or send email', function () {
    $school = autoExtendSchool();
    $contract = expiredContract($school, daysAgo: 0);
    $originalEndDate = $contract->end_date->copy();

    $this->artisan('school:auto-extend-contracts-ssas', ['--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN');

    $this->assertDatabaseHas('school_contracts', [
        'id' => $contract->id,
        'end_date' => $originalEndDate->toDateString(),
    ]);

    Mail::assertNothingQueued();
});

test('outputs message when no eligible schools found', function () {
    $this->artisan('school:auto-extend-contracts-ssas')
        ->assertExitCode(0)
        ->expectsOutputToContain('No eligible schools found');
});

test('extends only SSAs when contract not due (no email sent)', function () {
    $school = autoExtendSchool();
    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addDays(60)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $student = activeStudentInSchool($school);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'status' => SSAStatus::ACTIVE->value,
        'end_date' => now()->toDateString(),
    ]);
    $originalSsaEnd = $ssa->end_date->copy();

    $this->artisan('school:auto-extend-contracts-ssas')->assertExitCode(0);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'end_date' => $originalSsaEnd->copy()->addYear()->toDateString(),
    ]);

    Mail::assertNothingQueued();
});

test('extends contract only when no SSAs are due', function () {
    $school = autoExtendSchool();
    $contract = expiredContract($school, daysAgo: 0);
    $originalEndDate = $contract->end_date->copy();

    $student = activeStudentInSchool($school);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'status' => SSAStatus::ACTIVE->value,
        'end_date' => now()->addDays(90)->toDateString(),
    ]);
    $originalSsaEnd = $ssa->end_date->copy();

    $this->artisan('school:auto-extend-contracts-ssas')->assertExitCode(0);

    $this->assertDatabaseHas('school_contracts', [
        'id' => $contract->id,
        'end_date' => $originalEndDate->copy()->addYear()->toDateString(),
    ]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $ssa->id,
        'end_date' => $originalSsaEnd->toDateString(),
    ]);

    Mail::assertQueued(SchoolContractAutoExtendedMail::class);
});

test('only extends active SSAs not inactive ones', function () {
    $school = autoExtendSchool();
    $contract = expiredContract($school, daysAgo: 0);

    $student = activeStudentInSchool($school);
    $service = Service::factory()->create();

    $activeSsa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'status' => SSAStatus::ACTIVE->value,
        'end_date' => now()->toDateString(),
    ]);

    $inactiveSsa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'status' => SSAStatus::DEACTIVATED->value,
        'end_date' => now()->toDateString(),
    ]);

    $inactiveEndDate = $inactiveSsa->end_date->copy();

    $this->artisan('school:auto-extend-contracts-ssas')->assertExitCode(0);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $activeSsa->id,
        'end_date' => now()->addYear()->toDateString(),
    ]);

    $this->assertDatabaseHas('service_support_agreements', [
        'id' => $inactiveSsa->id,
        'end_date' => $inactiveEndDate->toDateString(),
    ]);
});
