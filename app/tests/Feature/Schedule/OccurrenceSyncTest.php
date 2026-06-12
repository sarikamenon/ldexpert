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
function syncDto(array $dates, ?array $startTimes = null, ?array $endTimes = null, ?string $endDate = null): UpdateScheduleDTO
{
    return new UpdateScheduleDTO(
        ssaId: null,
        serviceId: null,
        studentIds: null,
        scheduleDate: $dates[0],
        startTime: '09:00',
        endTime: '10:00',
        recurrenceType: RecurrenceType::DAILY,
        recurrenceEndDate: $endDate,
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

/**
 * Occurrence-scope ("Edit this schedule") DTO: edits a single schedule, posts no
 * recurrence/occurrence fields. Date/time changes detach it from the series.
 */
function occurrenceDto(string $date, string $startTime = '09:00', int $durationMinutes = 60, ?string $notes = null): UpdateScheduleDTO
{
    return new UpdateScheduleDTO(
        ssaId: null,
        serviceId: null,
        studentIds: null,
        scheduleDate: $date,
        startTime: $startTime,
        endTime: null,
        recurrenceType: null,
        recurrenceEndDate: null,
        isGroup: null,
        locationDetails: null,
        notes: $notes,
        billingStatus: null,
        durationMinutes: $durationMinutes,
        editScope: 'occurrence',
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

it('updates a single occurrence time in place and keeps it in the series', function (): void {
    $b = makeDailyBatch('2026-07-06');
    $sibling = $b['siblings'][0]; // 2026-07-07
    $batch = $b['anchor']->recurring_batch_number;

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

    // Same row id, new time — and it STAYS in the series as a modified exception.
    expect($sibling->start_time->format('H:i'))->toBe('11:00')
        ->and($sibling->end_time->format('H:i'))->toBe('12:00')
        ->and($sibling->recurring_batch_number)->toBe($batch)
        ->and($sibling->parent_schedule_id)->toBe($b['anchor']->id)
        ->and($sibling->recurrence_type)->toBe(RecurrenceType::DAILY);

    // All three rows remain in the batch.
    expect(Schedule::query()->where('recurring_batch_number', $batch)->count())->toBe(3);
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

it('overwrites a modified occurrence when the series is rebuilt by a type change', function (): void {
    $b = makeDailyBatch('2026-07-06');
    $modified = $b['siblings'][0]; // 2026-07-07
    $batch = $b['anchor']->recurring_batch_number;

    // First: edit one occurrence's time. It stays in the series as a modified
    // exception (not detached).
    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(
            ['2026-07-06', '2026-07-07', '2026-07-08'],
            ['09:00', '11:00', '09:00'],
            ['10:00', '12:00', '10:00'],
        ),
    );

    $modified->refresh();
    expect($modified->recurring_batch_number)->toBe($batch)
        ->and($modified->start_time->format('H:i'))->toBe('11:00');

    // Then: a recurrence TYPE change rebuilds the series — the old rows (incl. the
    // modified one) are soft-deleted and a fresh series is regenerated.
    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        new UpdateScheduleDTO(
            ssaId: null, serviceId: null, studentIds: null,
            scheduleDate: '2026-07-06', startTime: '09:00', endTime: '10:00',
            recurrenceType: RecurrenceType::WEEKLY, recurrenceEndDate: '2026-08-03',
            isGroup: null, locationDetails: null, notes: null, billingStatus: null,
            durationMinutes: 60,
        ),
    );

    // The modified row was overwritten (soft-deleted) by the rebuild.
    $modified->refresh();
    expect($modified->trashed())->toBeTrue();
});

it('extends the series additively — keeps existing rows and only adds new dates', function (): void {
    $b = makeDailyBatch('2026-07-06'); // anchor 07-06, siblings 07-07, 07-08
    $originalIds = collect([$b['anchor'], ...$b['siblings']])->pluck('id')->sort()->values()->all();

    // Extend the end date by two days; the posted list keeps the existing dates
    // and adds 07-09 and 07-10.
    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(
            ['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10'],
            endDate: '2026-07-10',
        ),
    );

    $batch = $b['anchor']->recurring_batch_number;

    // No deletions, original rows keep their ids.
    expect(Schedule::onlyTrashed()->count())->toBe(0);
    $liveIds = Schedule::query()->where('recurring_batch_number', $batch)->pluck('id')->sort()->values()->all();
    expect($liveIds)->toContain(...$originalIds)->toHaveCount(5);

    // The anchor's recurrence_end_date reflects the extension.
    expect($b['anchor']->fresh()->recurrence_end_date->format('Y-m-d'))->toBe('2026-07-10');

    // The two new dates exist and are linked to the anchor.
    expect(Schedule::query()->where('recurring_batch_number', $batch)->where('schedule_date', '2026-07-09')->exists())->toBeTrue()
        ->and(Schedule::query()->where('recurring_batch_number', $batch)->where('schedule_date', '2026-07-10')->exists())->toBeTrue();
});

it('shrinks the series — deletes rows past the new end date and keeps the rest', function (): void {
    $b = makeDailyBatch('2026-07-06', 4); // anchor 07-06, siblings 07-07..07-10
    $kept = [$b['anchor']->id, $b['siblings'][0]->id]; // 07-06, 07-07
    $dropped = [$b['siblings'][1]->id, $b['siblings'][2]->id, $b['siblings'][3]->id]; // 07-08..07-10

    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(['2026-07-06', '2026-07-07'], endDate: '2026-07-07'),
    );

    $batch = $b['anchor']->recurring_batch_number;

    foreach ($kept as $id) {
        expect(Schedule::find($id))->not->toBeNull();
    }
    foreach ($dropped as $id) {
        expect(Schedule::find($id))->toBeNull()
            ->and(Schedule::onlyTrashed()->find($id))->not->toBeNull();
    }

    expect(Schedule::query()->where('recurring_batch_number', $batch)->count())->toBe(2)
        ->and($b['anchor']->fresh()->recurrence_end_date->format('Y-m-d'))->toBe('2026-07-07');
});

it('demotes the lone survivor to standalone when deleting the last sibling of a 2-occurrence series', function (): void {
    $b = makeDailyBatch('2026-07-06', 1); // anchor 07-06 + one sibling 07-07
    $sibling = $b['siblings'][0];

    app(ScheduleService::class)->deleteSchedule($b['therapist'], $sibling->id);

    $anchor = $b['anchor']->fresh();
    expect($anchor->recurring_batch_number)->toBeNull()
        ->and($anchor->parent_schedule_id)->toBeNull()
        ->and($anchor->recurrence_type)->toBe(RecurrenceType::NONE)
        ->and($anchor->recurrence_end_date)->toBeNull();
});

it('re-anchors the batch when the anchor row is deleted', function (): void {
    $b = makeDailyBatch('2026-07-06', 2); // anchor 07-06, siblings 07-07, 07-08
    $oldAnchor = $b['anchor'];
    $batch = $oldAnchor->recurring_batch_number;

    // Delete the anchor (parent) row.
    app(ScheduleService::class)->deleteSchedule($b['therapist'], $oldAnchor->id);

    $remaining = Schedule::query()
        ->where('recurring_batch_number', $batch)
        ->orderBy('schedule_date')
        ->get();

    // Two sessions survive, re-anchored: exactly one is the new parent and the
    // other points at it — nothing references the deleted anchor.
    expect($remaining)->toHaveCount(2);

    $newAnchor = $remaining->firstWhere('parent_schedule_id', null);
    expect($newAnchor)->not->toBeNull()
        ->and($newAnchor->schedule_date->format('Y-m-d'))->toBe('2026-07-07');

    $remaining->each(function (Schedule $row) use ($newAnchor, $oldAnchor): void {
        if ($row->id !== $newAnchor->id) {
            expect($row->parent_schedule_id)->toBe($newAnchor->id);
        }
        expect($row->parent_schedule_id)->not->toBe($oldAnchor->id);
    });
});

it('does not demote a billed lone survivor', function (): void {
    $b = makeDailyBatch('2026-07-06', 1);
    $b['anchor']->update(['billing_status' => BillingStatus::BILLED]);
    $sibling = $b['siblings'][0];

    app(ScheduleService::class)->deleteSchedule($b['therapist'], $sibling->id);

    // Billed survivor keeps its recurrence metadata (we never mutate billed rows).
    $anchor = $b['anchor']->fresh();
    expect($anchor->recurring_batch_number)->not->toBeNull()
        ->and($anchor->recurrence_type)->toBe(RecurrenceType::DAILY);
});

it('does not demote while two or more occurrences still remain', function (): void {
    $b = makeDailyBatch('2026-07-06', 2); // anchor + 2 siblings (3 total)

    app(ScheduleService::class)->deleteSchedule($b['therapist'], $b['siblings'][0]->id);

    // 2 remain → still a series.
    $anchor = $b['anchor']->fresh();
    expect($anchor->recurring_batch_number)->not->toBeNull()
        ->and($anchor->recurrence_type)->toBe(RecurrenceType::DAILY);
});

it('demotes when an end-date shrink leaves a single occurrence', function (): void {
    $b = makeDailyBatch('2026-07-06', 1); // anchor 07-06 + sibling 07-07

    // Shrink so only the anchor date remains.
    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(['2026-07-06'], endDate: '2026-07-06'),
    );

    $anchor = $b['anchor']->fresh();
    expect($anchor->recurring_batch_number)->toBeNull()
        ->and($anchor->recurrence_type)->toBe(RecurrenceType::NONE)
        ->and($anchor->recurrence_end_date)->toBeNull();
});

// -- Occurrence-scope edit ("Edit this schedule") --------------------------------

it('keeps an occurrence in the series when its date changes in occurrence scope', function (): void {
    $b = makeDailyBatch('2026-07-06', 2); // anchor + 07-07, 07-08
    $sibling = $b['siblings'][0]; // 07-07
    $batch = $b['anchor']->recurring_batch_number;

    // Move this one session to a different day via "Edit this schedule".
    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $sibling->id,
        occurrenceDto('2026-07-20'),
    );

    $sibling->refresh();
    // The session moved but stays in the series as a modified exception.
    expect($sibling->schedule_date->format('Y-m-d'))->toBe('2026-07-20')
        ->and($sibling->recurring_batch_number)->toBe($batch)
        ->and($sibling->parent_schedule_id)->toBe($b['anchor']->id)
        ->and($sibling->recurrence_type)->toBe(RecurrenceType::DAILY);

    // All three rows remain in the batch.
    expect(Schedule::query()->where('recurring_batch_number', $batch)->count())->toBe(3);
});

it('keeps the anchor in the series when its own date changes in occurrence scope', function (): void {
    $b = makeDailyBatch('2026-07-06', 2); // anchor 07-06, siblings 07-07, 07-08
    $anchor = $b['anchor'];
    $batch = $anchor->recurring_batch_number;

    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $anchor->id,
        occurrenceDto('2026-07-20'),
    );

    $anchor->refresh();
    // The anchor moved but remains the series anchor; siblings still point at it.
    expect($anchor->schedule_date->format('Y-m-d'))->toBe('2026-07-20')
        ->and($anchor->recurring_batch_number)->toBe($batch)
        ->and($anchor->parent_schedule_id)->toBeNull();

    expect(Schedule::query()->where('recurring_batch_number', $batch)->count())->toBe(3);
    $b['siblings'][0]->refresh();
    expect($b['siblings'][0]->parent_schedule_id)->toBe($anchor->id);
});

it('keeps an occurrence in the series when its time changes in occurrence scope', function (): void {
    $b = makeDailyBatch('2026-07-06', 2);
    $sibling = $b['siblings'][0]; // 07-07 at 09:00
    $batch = $b['anchor']->recurring_batch_number;

    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $sibling->id,
        occurrenceDto('2026-07-07', startTime: '14:00'),
    );

    $sibling->refresh();
    expect($sibling->start_time->format('H:i'))->toBe('14:00')
        ->and($sibling->recurring_batch_number)->toBe($batch)
        ->and($sibling->recurrence_type)->toBe(RecurrenceType::DAILY);
});

it('keeps an occurrence in the series when only notes change in occurrence scope', function (): void {
    $b = makeDailyBatch('2026-07-06', 2);
    $sibling = $b['siblings'][0]; // 07-07 at 09:00
    $batch = $b['anchor']->recurring_batch_number;

    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $sibling->id,
        occurrenceDto('2026-07-07', startTime: '09:00', notes: 'Updated note only'),
    );

    $sibling->refresh();
    expect($sibling->notes)->toBe('Updated note only')
        ->and($sibling->recurring_batch_number)->toBe($batch)
        ->and($sibling->recurrence_type)->toBe(RecurrenceType::DAILY);

    // Whole batch intact (anchor + 2 siblings).
    expect(Schedule::query()->where('recurring_batch_number', $batch)->count())->toBe(3);
});

it('does not demote the batch when an occurrence-scope move keeps both sessions in the series', function (): void {
    $b = makeDailyBatch('2026-07-06', 1); // anchor 07-06 + sibling 07-07
    $sibling = $b['siblings'][0];
    $batch = $b['anchor']->recurring_batch_number;

    // Moving the sibling keeps it in the series, so the batch still has 2 rows
    // and the anchor is NOT demoted.
    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $sibling->id,
        occurrenceDto('2026-07-20'),
    );

    $anchor = $b['anchor']->fresh();
    expect($anchor->recurring_batch_number)->toBe($batch)
        ->and($anchor->recurrence_type)->toBe(RecurrenceType::DAILY);
    expect(Schedule::query()->where('recurring_batch_number', $batch)->count())->toBe(2);
});

it('keeps a non-recurring schedule as-is when edited in occurrence scope', function (): void {
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

    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'student_id' => $student->id,
        'ssa_id' => $ssa->id,
        'service_id' => $service->id,
        'school_id' => $school->id,
        'schedule_date' => '2026-07-06',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'recurrence_type' => RecurrenceType::NONE,
        'recurring_batch_number' => null,
        'billing_status' => BillingStatus::PENDING,
    ]);

    app(ScheduleService::class)->updateSchedule(
        $therapist,
        $schedule->id,
        occurrenceDto('2026-07-09', startTime: '11:00'),
    );

    $schedule->refresh();
    expect($schedule->schedule_date->format('Y-m-d'))->toBe('2026-07-09')
        ->and($schedule->start_time->format('H:i'))->toBe('11:00')
        ->and($schedule->recurrence_type)->toBe(RecurrenceType::NONE);
});

it('series editor: a new date beyond the old end joins the batch (extension)', function (): void {
    $b = makeDailyBatch('2026-07-06', 2); // anchor 07-06, siblings 07-07, 07-08; old end 07-08
    $batch = $b['anchor']->recurring_batch_number;

    // Extend the end date and add 07-10 (> old end 07-08).
    app(ScheduleService::class)->updateSchedule(
        $b['therapist'],
        $b['anchor']->id,
        syncDto(['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10'], endDate: '2026-07-10'),
    );

    $ext = Schedule::query()->where('recurring_batch_number', $batch)->where('schedule_date', '2026-07-10')->first();
    expect($ext)->not->toBeNull()
        ->and($ext->recurrence_type)->toBe(RecurrenceType::DAILY);
});

it('series editor: a new in-range date stays in the series (modified occurrence, not standalone)', function (): void {
    // Weekly batch so there are in-range gaps to drop into.
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

    $batch = 'REC-MOVE-TEST';
    $base = [
        'therapist_id' => $therapist->id, 'student_id' => $student->id, 'ssa_id' => $ssa->id,
        'service_id' => $service->id, 'school_id' => $school->id,
        'start_time' => '09:00:00', 'end_time' => '10:00:00',
        'recurrence_type' => RecurrenceType::WEEKLY, 'recurrence_end_date' => '2026-07-16',
        'recurring_batch_number' => $batch, 'billing_status' => BillingStatus::PENDING,
    ];
    // Thursdays: 07-02 (anchor), 07-09, 07-16
    $anchor = Schedule::factory()->create([...$base, 'schedule_date' => '2026-07-02', 'parent_schedule_id' => null]);
    Schedule::factory()->create([...$base, 'schedule_date' => '2026-07-09', 'parent_schedule_id' => $anchor->id]);
    Schedule::factory()->create([...$base, 'schedule_date' => '2026-07-16', 'parent_schedule_id' => $anchor->id]);

    // Drop 07-09 (Thu) and add 07-08 (Wed) — an in-range move.
    app(ScheduleService::class)->updateSchedule(
        $therapist,
        $anchor->id,
        new UpdateScheduleDTO(
            ssaId: null, serviceId: null, studentIds: null,
            scheduleDate: '2026-07-02', startTime: '09:00', endTime: '10:00',
            recurrenceType: RecurrenceType::WEEKLY, recurrenceEndDate: '2026-07-16',
            isGroup: null, locationDetails: null, notes: null, billingStatus: null,
            durationMinutes: 60,
            occurrenceDates: ['2026-07-02', '2026-07-08', '2026-07-16'],
        ),
    );

    // 07-08 stays in the series as a modified occurrence (iCalendar model).
    $moved = Schedule::query()->where('schedule_date', '2026-07-08')->first();
    expect($moved)->not->toBeNull()
        ->and($moved->recurring_batch_number)->toBe($batch)
        ->and($moved->parent_schedule_id)->toBe($anchor->id)
        ->and($moved->recurrence_type)->toBe(RecurrenceType::WEEKLY);
});
