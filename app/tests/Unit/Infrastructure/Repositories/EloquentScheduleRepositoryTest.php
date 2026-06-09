<?php

declare(strict_types=1);

use App\Domain\Therapist\Repositories\ScheduleRepositoryInterface;
use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(ScheduleRepositoryInterface::class);
    $this->therapist = User::factory()->therapist()->create();
});

/**
 * A schedule owned by the given therapist at the given instant.
 */
function ownedScheduleAt(
    User $therapist,
    CarbonImmutable $instant,
    ScheduleStatus $status = ScheduleStatus::SCHEDULED,
    BillingStatus $billing = BillingStatus::PENDING,
): Schedule {
    return Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'schedule_date' => $instant->toDateString(),
        'start_time' => $instant->format('H:i'),
        'status' => $status,
        'billing_status' => $billing,
    ]);
}

describe('findById', function () {
    it('returns the schedule regardless of owning therapist', function () {
        $schedule = ownedScheduleAt($this->therapist, CarbonImmutable::now()->addWeek());

        $found = $this->repository->findById($schedule->id);

        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($schedule->id);
    });

    it('eager-loads the requested relations', function () {
        $schedule = ownedScheduleAt($this->therapist, CarbonImmutable::now()->addWeek());

        $found = $this->repository->findById($schedule->id, ['therapist', 'student']);

        expect($found->relationLoaded('therapist'))->toBeTrue()
            ->and($found->relationLoaded('student'))->toBeTrue();
    });

    it('returns null when the schedule does not exist', function () {
        expect($this->repository->findById(999999))->toBeNull();
    });
});

describe('getFutureScheduledForTherapistOwned', function () {
    it('returns future, scheduled, unbilled sessions owned by the therapist', function () {
        $future = ownedScheduleAt($this->therapist, CarbonImmutable::now()->addWeek());

        $result = $this->repository->getFutureScheduledForTherapistOwned(
            $this->therapist->id,
            CarbonImmutable::now(),
        );

        expect($result->pluck('id')->all())->toBe([$future->id]);
    });

    it('excludes past sessions', function () {
        ownedScheduleAt($this->therapist, CarbonImmutable::now()->subWeek());

        $result = $this->repository->getFutureScheduledForTherapistOwned(
            $this->therapist->id,
            CarbonImmutable::now(),
        );

        expect($result)->toHaveCount(0);
    });

    it('excludes cancelled and completed sessions', function () {
        ownedScheduleAt($this->therapist, CarbonImmutable::now()->addWeek(), ScheduleStatus::CANCELLED);
        ownedScheduleAt($this->therapist, CarbonImmutable::now()->addWeek(), ScheduleStatus::COMPLETED);

        $result = $this->repository->getFutureScheduledForTherapistOwned(
            $this->therapist->id,
            CarbonImmutable::now(),
        );

        expect($result)->toHaveCount(0);
    });

    it('excludes billed sessions', function () {
        ownedScheduleAt(
            $this->therapist,
            CarbonImmutable::now()->addWeek(),
            ScheduleStatus::SCHEDULED,
            BillingStatus::BILLED,
        );

        $result = $this->repository->getFutureScheduledForTherapistOwned(
            $this->therapist->id,
            CarbonImmutable::now(),
        );

        expect($result)->toHaveCount(0);
    });

    it('excludes sessions the therapist only covers as a sub', function () {
        $owner = User::factory()->therapist()->create();
        Schedule::factory()->coveredBy($this->therapist)->create([
            'therapist_id' => $owner->id,
            'schedule_date' => CarbonImmutable::now()->addWeek()->toDateString(),
            'start_time' => CarbonImmutable::now()->addWeek()->format('H:i'),
            'status' => ScheduleStatus::SCHEDULED,
        ]);

        $result = $this->repository->getFutureScheduledForTherapistOwned(
            $this->therapist->id,
            CarbonImmutable::now(),
        );

        expect($result)->toHaveCount(0);
    });
});
