<?php

declare(strict_types=1);

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use App\Domain\Therapist\Services\SessionLogService;
use App\DTOs\CreateSessionLogDTO;
use App\Enums\SessionOutcome;
use App\Models\SessionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesSubCoverageFixtures;

uses(RefreshDatabase::class, CreatesSubCoverageFixtures::class);

beforeEach(function () {
    Mail::fake();
    config(['scheduling.sub_request_cutoff_hours' => 2]);
});

/**
 * Build the standard "B has accepted the coverage" world and return everything
 * a session-log test needs.
 *
 * @return array{w: array<string, mixed>, dto: CreateSessionLogDTO}
 */
function buildCoveredAndAccepted(\Tests\TestCase $tc): array
{
    $w = $tc->buildSubCoverageWorld();
    $request = app(ScheduleSubRequestService::class)
        ->create($w['A'], $w['schedule'], [$w['B']->id], 'Conference');
    app(ScheduleSubRequestService::class)->accept($w['B'], $request->fresh());

    $w['schedule']->refresh();

    $dto = CreateSessionLogDTO::fromArray([
        // Required identifiers — the service overrides therapist_id/ssa_id
        // for the sub branch, but the DTO still needs valid placeholders.
        'therapist_id' => $w['B']->id,
        'student_id' => $w['student']->id,
        'ssa_id' => $w['ssa']->id,
        'service_id' => $w['service']->id,
        'schedule_id' => $w['schedule']->id,
        'school_id' => $w['school']->id,
        'session_date' => $w['sessionDate']->toDateString(),
        'start_time' => $w['sessionStart']->format('H:i'),
        'end_time' => $w['sessionStart']->copy()->addHour()->format('H:i'),
        'duration_minutes' => 60,
        'tho_minutes' => 60,
        'notes' => 'Covered the session for A.',
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);

    return ['w' => $w, 'dto' => $dto];
}

// ─── 1. Sub can submit a log on a covered schedule ─────────────────────────

it('lets the accepted sub create a session log from a covered schedule', function () {
    ['w' => $w, 'dto' => $dto] = buildCoveredAndAccepted($this);

    $log = app(SessionLogService::class)->createFromSchedule($w['B'], $w['schedule'], $dto);

    expect($log)->toBeInstanceOf(SessionLog::class);
    expect($log->exists)->toBeTrue();
    expect($log->schedule_id)->toBe($w['schedule']->id);
});

// ─── 2. original_therapist_id is set to A ──────────────────────────────────

it('sets original_therapist_id to the original assigned therapist', function () {
    ['w' => $w, 'dto' => $dto] = buildCoveredAndAccepted($this);

    $log = app(SessionLogService::class)->createFromSchedule($w['B'], $w['schedule'], $dto);

    expect($log->original_therapist_id)->toBe($w['A']->id);
});

// ─── 3. therapist_id (performer) is B, not A ───────────────────────────────

it('records the performer (the sub) as therapist_id on the log', function () {
    ['w' => $w, 'dto' => $dto] = buildCoveredAndAccepted($this);

    $log = app(SessionLogService::class)->createFromSchedule($w['B'], $w['schedule'], $dto);

    expect($log->therapist_id)->toBe($w['B']->id);
    expect($log->therapist_id)->not->toBe($w['A']->id);
});

// ─── 4. ssa_id resolves to the snapshotted sub-SSA's ssa_id ────────────────

it('resolves ssa_id from the sub-SSA snapshot, not from the DTO', function () {
    ['w' => $w, 'dto' => $dto] = buildCoveredAndAccepted($this);

    // Replace the DTO's ssa_id with a garbage id; the service should ignore it.
    $hijacked = CreateSessionLogDTO::fromArray(array_merge($dto->toArray(), [
        'ssa_id' => 999_999,
    ]));

    $log = app(SessionLogService::class)->createFromSchedule($w['B'], $w['schedule'], $hijacked);

    expect($log->ssa_id)->toBe($w['ssa']->id);
});

// ─── 5. Billing uses the sub's contract ────────────────────────────────────

it('bills the sub against B contract, not the original therapist contract', function () {
    ['w' => $w, 'dto' => $dto] = buildCoveredAndAccepted($this);

    $log = app(SessionLogService::class)->createFromSchedule($w['B'], $w['schedule'], $dto);

    $bContractId = $w['B']->therapistProfile->contracts()->first()?->id;
    $aContractId = $w['A']->therapistProfile->contracts()->first()?->id;

    expect($log->therapist_contract_id)->toBe($bContractId);
    expect($log->therapist_contract_id)->not->toBe($aContractId);
});

// ─── 7. An uninvolved therapist is rejected ────────────────────────────────

it('rejects log creation by a therapist who is neither owner nor sub', function () {
    ['w' => $w, 'dto' => $dto] = buildCoveredAndAccepted($this);

    expect(fn () => app(SessionLogService::class)->createFromSchedule($w['C'], $w['schedule'], $dto))
        ->toThrow(InvalidArgumentException::class, 'does not have access');
});

// ─── 8. Pin current behavior: original therapist can still log after sub
//        has accepted. UI suppresses the action but service does not block.
//        If this changes, update the test to assert the rejection. ─────────

it('currently allows the original therapist to log even after a sub accepted (UI-only suppression)', function () {
    ['w' => $w, 'dto' => $dto] = buildCoveredAndAccepted($this);

    // A submits with their own SSA — the service's non-sub branch validates
    // SSA access against A, which A owns.
    $log = app(SessionLogService::class)->createFromSchedule($w['A'], $w['schedule'], $dto);

    expect($log->therapist_id)->toBe($w['A']->id);
    expect($log->original_therapist_id)->toBeNull();
});

// ─── 9. Sub trying to log a schedule with no coverage assignment ───────────

it('rejects when the schedule has no sub-coverage assignment for this therapist', function () {
    $w = $this->buildSubCoverageWorld();
    // No accept happened — B has no sub_therapist_id binding on the schedule.

    $dto = CreateSessionLogDTO::fromArray([
        'therapist_id' => $w['B']->id,
        'student_id' => $w['student']->id,
        'ssa_id' => $w['ssa']->id,
        'service_id' => $w['service']->id,
        'schedule_id' => $w['schedule']->id,
        'school_id' => $w['school']->id,
        'session_date' => $w['sessionDate']->toDateString(),
        'start_time' => $w['sessionStart']->format('H:i'),
        'end_time' => $w['sessionStart']->copy()->addHour()->format('H:i'),
        'duration_minutes' => 60,
        'tho_minutes' => 60,
        'notes' => 'No coverage path.',
        'outcome' => SessionOutcome::SERVICES_ADMINISTERED->value,
    ]);

    expect(fn () => app(SessionLogService::class)->createFromSchedule($w['B'], $w['schedule'], $dto))
        ->toThrow(InvalidArgumentException::class, 'does not have access');
});

// ─── 10. Schedule can only be logged once via the sub branch ──────────────

it('refuses a second log on a covered schedule once one has been created', function () {
    ['w' => $w, 'dto' => $dto] = buildCoveredAndAccepted($this);
    app(SessionLogService::class)->createFromSchedule($w['B'], $w['schedule'], $dto);

    // The second attempt fails — either because the schedule was marked
    // billed by the first log, or because the "one log per schedule" guard
    // fires first. Either rejection is correct behaviour; the invariant
    // we care about is "you can't double-log".
    expect(fn () => app(SessionLogService::class)->createFromSchedule($w['B'], $w['schedule'], $dto))
        ->toThrow(InvalidArgumentException::class);

    expect(SessionLog::query()->where('schedule_id', $w['schedule']->id)->count())->toBe(1);
});

// ─── 6. HTTP smoke — confirms StoreSessionLogRequest's SSA-relaxation wiring

it('lets the sub submit through the HTTP route despite not owning the SSA', function () {
    ['w' => $w, 'dto' => $dto] = buildCoveredAndAccepted($this);

    // Strip override-related fields — the form request prohibits is_rate_override
    // for non-admin users, even when false.
    $payload = $dto->toArray();
    unset($payload['is_rate_override'], $payload['override_reason']);

    $response = $this->actingAs($w['B'])
        ->post(route('therapist.session-logs.store'), $payload);
    $response->assertSessionHasNoErrors();

    $log = SessionLog::query()->where('schedule_id', $w['schedule']->id)->first();
    expect($log)->not->toBeNull();
    expect($log->therapist_id)->toBe($w['B']->id);
    expect($log->original_therapist_id)->toBe($w['A']->id);
});
