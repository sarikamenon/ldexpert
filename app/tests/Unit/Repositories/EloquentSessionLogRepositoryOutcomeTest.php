<?php

declare(strict_types=1);

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(SessionLogRepositoryInterface::class);
    $this->student = User::factory()->student()->create();
});

test('returns empty array when student has no approved logs', function () {
    SessionLog::factory()->submitted()->create([
        'student_id' => $this->student->id,
        'tho_minutes' => 60,
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);

    $result = $this->repository->getOutcomeMinutesForStudent($this->student->id);

    expect($result)->toBe([]);
});

test('sums tho minutes grouped by outcome for approved logs only', function () {
    SessionLog::factory()->approved()->create([
        'student_id' => $this->student->id,
        'tho_minutes' => 30,
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);
    SessionLog::factory()->approved()->create([
        'student_id' => $this->student->id,
        'tho_minutes' => 45,
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);
    SessionLog::factory()->approved()->create([
        'student_id' => $this->student->id,
        'tho_minutes' => 60,
        'outcome' => SessionOutcome::NO_SHOW->value,
    ]);

    // Non-approved log should be ignored.
    SessionLog::factory()->submitted()->create([
        'student_id' => $this->student->id,
        'tho_minutes' => 999,
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);

    $result = $this->repository->getOutcomeMinutesForStudent($this->student->id);

    expect($result)
        ->toHaveKey(SessionOutcome::SERVICES_ADMINISTERED->value, 75)
        ->toHaveKey(SessionOutcome::NO_SHOW->value, 60)
        ->not->toHaveKey(SessionOutcome::BILLABLE_CANCELLATION->value);
});

test('excludes logs with zero tho minutes', function () {
    SessionLog::factory()->approved()->create([
        'student_id' => $this->student->id,
        'tho_minutes' => 0,
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);
    SessionLog::factory()->approved()->create([
        'student_id' => $this->student->id,
        'tho_minutes' => 30,
        'outcome' => SessionOutcome::NO_SHOW->value,
    ]);

    $result = $this->repository->getOutcomeMinutesForStudent($this->student->id);

    expect($result)
        ->not->toHaveKey(SessionOutcome::SERVICES_ADMINISTERED->value)
        ->toHaveKey(SessionOutcome::NO_SHOW->value, 30);
});

test('isolates results by student id', function () {
    $otherStudent = User::factory()->student()->create();

    SessionLog::factory()->approved()->create([
        'student_id' => $this->student->id,
        'tho_minutes' => 30,
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);
    SessionLog::factory()->approved()->create([
        'student_id' => $otherStudent->id,
        'tho_minutes' => 999,
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);

    $result = $this->repository->getOutcomeMinutesForStudent($this->student->id);

    expect($result)->toBe([SessionOutcome::SERVICES_ADMINISTERED->value => 30]);
});

test('respects soft deletes', function () {
    $log = SessionLog::factory()->approved()->create([
        'student_id' => $this->student->id,
        'tho_minutes' => 30,
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);
    $log->delete();

    $result = $this->repository->getOutcomeMinutesForStudent($this->student->id);

    expect($result)->toBe([]);
});

test('only the approved status contributes; other statuses are ignored', function () {
    foreach ([SessionLogStatus::DRAFT, SessionLogStatus::SUBMITTED, SessionLogStatus::SENT_BACK] as $status) {
        SessionLog::factory()->create([
            'student_id' => $this->student->id,
            'tho_minutes' => 60,
            'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
            'status' => $status,
        ]);
    }

    expect($this->repository->getOutcomeMinutesForStudent($this->student->id))->toBe([]);
});
