<?php

declare(strict_types=1);

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Enums\BillingStatus;
use App\Models\Audit;
use App\Models\Schedule;
use App\Models\SessionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(SessionLogRepositoryInterface::class);
});

test('deleteAndUnbill soft-deletes a standalone log without touching schedules', function () {
    $sessionLog = SessionLog::factory()->draft()->create(['schedule_id' => null]);

    $this->repository->deleteAndUnbill($sessionLog);

    $this->assertSoftDeleted($sessionLog);
});

test('deleteAndUnbill reverts a billed schedule to pending and soft-deletes the log', function () {
    $schedule = Schedule::factory()->create(['billing_status' => BillingStatus::BILLED]);
    $sessionLog = SessionLog::factory()->draft()->withSchedule($schedule)->create();

    $this->repository->deleteAndUnbill($sessionLog);

    $this->assertSoftDeleted($sessionLog);
    expect($schedule->fresh()->billing_status)->toBe(BillingStatus::PENDING);
});

test('deleteAndUnbill audits the schedule billing_status revert', function () {
    $schedule = Schedule::factory()->create(['billing_status' => BillingStatus::BILLED]);
    $sessionLog = SessionLog::factory()->draft()->withSchedule($schedule)->create();

    $this->repository->deleteAndUnbill($sessionLog);

    $audit = Audit::query()
        ->where('auditable_type', $schedule->getMorphClass())
        ->where('auditable_id', $schedule->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->old_values['billing_status'])->toBe(BillingStatus::BILLED->value)
        ->and($audit->new_values['billing_status'])->toBe(BillingStatus::PENDING->value);
});

test('deleteAndUnbill leaves a non-billed schedule untouched', function () {
    $schedule = Schedule::factory()->create(['billing_status' => BillingStatus::PENDING]);
    $sessionLog = SessionLog::factory()->draft()->withSchedule($schedule)->create();

    $this->repository->deleteAndUnbill($sessionLog);

    $this->assertSoftDeleted($sessionLog);
    expect($schedule->fresh()->billing_status)->toBe(BillingStatus::PENDING);
});
