<?php

declare(strict_types=1);

use App\Enums\ContractStatus;
use App\Enums\SchoolStatus;
use App\Mail\SchoolContractExpiryWarningMail;
use App\Models\School;
use App\Models\SchoolContract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

test('notifies manager when private-student school contract expires in 7 days', function () {
    $manager = User::factory()->admin()->create(['email' => 'manager@example.com']);
    $school = School::factory()->create([
        'is_private_student' => true,
        'status' => SchoolStatus::ACTIVE->value,
        'manager_id' => $manager->id,
    ]);
    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $this->artisan('school:notify-expiring-contracts')
        ->assertExitCode(0)
        ->expectsOutputToContain('Notified: 1');

    Mail::assertQueued(SchoolContractExpiryWarningMail::class, function ($mail) use ($manager) {
        return $mail->hasTo($manager->email);
    });
});

test('does not notify non-private-student schools', function () {
    $manager = User::factory()->admin()->create();
    $school = School::factory()->create([
        'is_private_student' => false,
        'status' => SchoolStatus::ACTIVE->value,
        'manager_id' => $manager->id,
    ]);
    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $this->artisan('school:notify-expiring-contracts')
        ->assertExitCode(0);

    Mail::assertNothingQueued();
});

test('does not notify when contract expires on a different day', function () {
    $manager = User::factory()->admin()->create();
    $school = School::factory()->create([
        'is_private_student' => true,
        'status' => SchoolStatus::ACTIVE->value,
        'manager_id' => $manager->id,
    ]);
    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addDays(14)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $this->artisan('school:notify-expiring-contracts')
        ->assertExitCode(0);

    Mail::assertNothingQueued();
});

test('dry run lists schools without sending email', function () {
    $manager = User::factory()->admin()->create(['email' => 'manager@example.com']);
    $school = School::factory()->create([
        'is_private_student' => true,
        'status' => SchoolStatus::ACTIVE->value,
        'manager_id' => $manager->id,
    ]);
    SchoolContract::create([
        'school_id' => $school->id,
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'status' => ContractStatus::ACTIVE->value,
    ]);

    $this->artisan('school:notify-expiring-contracts', ['--dry-run' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('DRY RUN');

    Mail::assertNothingQueued();
});

test('outputs message when no contracts are expiring', function () {
    $this->artisan('school:notify-expiring-contracts')
        ->assertExitCode(0)
        ->expectsOutputToContain('No contracts expiring');
});
