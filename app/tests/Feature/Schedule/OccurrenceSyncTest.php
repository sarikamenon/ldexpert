<?php

declare(strict_types=1);

use App\Domain\Therapist\Services\ScheduleService;
use App\DTOs\UpdateScheduleDTO;
use App\Enums\BillingStatus;
use App\Enums\RecurrenceType;
use App\Enums\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a daily recurring batch (anchor + N siblings) at 09:00–10:00 UTC.
 * Therapist timezone is UTC so local == stored, keeping date/time math direct.
 *
 * @return array{therapist: User, student: User, anchor: Schedule, siblings: array<int, Schedule>}
 */
function makeDailyBatch(string $anchorDate, int $siblingDays = 2): array
{
    $therapist = User::factory()->create(['role' => Role::THERAPIST, 'timezone' => 'UTC']);
    $student = User::factory()->create(['role' => Role::STUDENT, 'timezone' => 'UTC']);
    $school = School::factory()->create(['non_billable_scheduling' => false]);
    StudentProfile::factory()->create(['user_id' => $student->id, 'school_id' => $school->id]);
    $service = Service::factory()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'student_id' => $student->id,
        'primary_service_id' => $service->id,
        'assigned_therapist_id' => $therapist->id,
    ]);
    $therapist->students()->attach($student->id, ['assigned_at' => now(), 'status' => 'active']);

    $batch = 'REC-SYNC-'.substr(md5($anchorDate), 0, 8);

    $base = [
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'recurrence_type' => RecurrenceType::DAILY,
        'recurrence_end_date' => Carbon\Carbon::parse($anchorDate)->addDays($siblingDays)->format('Y-m-d'),
        'recurring_batch_number' => $batch,
        'billing_status' => BillingStatus::PENDING,
    ];

    $anchor = Schedule::factory()->create([
        ...$base,
        'schedule_date' => $anchorDate,
        'parent_schedule_id' => null,
    ]);

    $siblings = [];
    for ($i = 1; $i <= $siblingDays; $i++) {
        $siblings[] = Schedule::factory()->create([
            ...$base,
            'schedule_date' => Carbon\Carbon::parse($anchorDate)->addDays($i)->format('Y-m-d'),
            'parent_schedule_id' => $anchor->id,
        ]);
    }

    return ['therapist' => $therapist, 'student' => $student, 'anchor' => $anchor, 'siblings' => $siblings];
}

/**
 * @param  array<int, string>  $dates
 * @param  array<int, string>|null  $startTimes
 * @param  array<int, string>|null  $endTimes
 */
function syncDto(array $dates, ?array $startTimes = null, ?array $endTimes = null): UpdateScheduleDTO
{
    return new UpdateScheduleDTO(
        ssaId: null,
        serviceId: null,
        studentIds: null,
        scheduleDate: $dates[0],
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::DAILY,
        recurrenceEndDate: null,
        isGroup: null,
        locationDetails: null,
        notes: null,
        billingStatus: null,
        durationMinutes: 60,
        occurrenceDates: $dates,
        occurrenceStartTimes: $startTimes,
        occurrenceEndTimes: $endTimes,
    );
}

it('does not rebuild the series when the occurrence list is unchanged', function (): void {
    $b = makeDailyBatch('2026-07-06');
    $originalIds = collect([$b['anchor'], ...$b['siblings']])->pluck('id')->sort()->values()->all();

    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(['2026-07-06', '2026-07-07', '2026-07-08']),
    );

    $live = Schedule::query()
        ->where('recurring_batch_number', $b['anchor']->recurring_batch_number)
        ->orderBy('schedule_date')
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    // Same rows, same ids — no delete + recreate.
    expect($live)->toBe($originalIds);
    expect(Schedule::onlyTrashed()->count())->toBe(0);
});

it('updates a single occurrence time in place and detaches it from the batch', function (): void {
    $b = makeDailyBatch('2026-07-06');
    $sibling = $b['siblings'][0]; // 2026-07-07

    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(
            ['2026-07-06', '2026-07-07', '2026-07-08'],
            ['09:00', '11:00', '09:00'],
            ['10:00', '12:00', '10:00'],
        ),
    );

    $sibling->refresh();

    // Same row id (session-log link preserved), new time, detached from batch.
    expect($sibling->start_time->format('H:i'))->toBe('11:00')
        ->and($sibling->end_time->format('H:i'))->toBe('12:00')
        ->and($sibling->recurring_batch_number)->toBeNull()
        ->and($sibling->parent_schedule_id)->toBeNull()
        ->and($sibling->recurrence_type)->toBe(RecurrenceType::NONE);

    // The two unchanged rows stay in the batch.
    expect(Schedule::query()
        ->where('recurring_batch_number', $b['anchor']->recurring_batch_number)
        ->count())->toBe(2);
});

it('deletes an occurrence removed from the list', function (): void {
    $b = makeDailyBatch('2026-07-06');
    $removed = $b['siblings'][1]; // 2026-07-08

    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(['2026-07-06', '2026-07-07']),
    );

    expect(Schedule::find($removed->id))->toBeNull()
        ->and(Schedule::onlyTrashed()->find($removed->id))->not->toBeNull();

    expect(Schedule::query()
        ->where('recurring_batch_number', $b['anchor']->recurring_batch_number)
        ->count())->toBe(2);
});

it('creates a new occurrence added to the list', function (): void {
    $b = makeDailyBatch('2026-07-06');

    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09']),
    );

    $added = Schedule::query()
        ->where('recurring_batch_number', $b['anchor']->recurring_batch_number)
        ->where('schedule_date', '2026-07-09')
        ->first();

    expect($added)->not->toBeNull()
        ->and($added->parent_schedule_id)->toBe($b['anchor']->id)
        ->and($added->start_time->format('H:i'))->toBe('09:00');

    expect(Schedule::query()
        ->where('recurring_batch_number', $b['anchor']->recurring_batch_number)
        ->count())->toBe(4);
});

it('preserves a detached occurrence when the series is later regenerated', function (): void {
    $b = makeDailyBatch('2026-07-06');
    $detached = $b['siblings'][0]; // 2026-07-07

    // First: edit one occurrence's time so it detaches from the batch.
    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(
            ['2026-07-06', '2026-07-07', '2026-07-08'],
            ['09:00', '11:00', '09:00'],
            ['10:00', '12:00', '10:00'],
        ),
    );

    $detached->refresh();
    expect($detached->recurring_batch_number)->toBeNull();

    // Then: a full series regenerate (extend end date) must leave the detached row alone.
    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        new UpdateScheduleDTO(
            ssaId: null, serviceId: null, studentIds: null,
            scheduleDate: '2026-07-06', startTime: '09:00', endTime: '10:00',
            recurrenceType: RecurrenceType::DAILY, recurrenceEndDate: '2026-07-10',
            isGroup: null, locationDetails: null, notes: null, billingStatus: null,
            durationMinutes: 60,
        ),
    );

    $detached->refresh();
    expect($detached->trashed())->toBeFalse()
        ->and($detached->start_time->format('H:i'))->toBe('11:00')
        ->and($detached->recurring_batch_number)->toBeNull();
});
