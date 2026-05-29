<?php

declare(strict_types=1);

use App\DTOs\Schedule\Makeup\CreateMakeupRequestDTO;
use App\DTOs\Schedule\Makeup\GenerateMakeupRemindersDTO;
use App\DTOs\Schedule\Makeup\MakeupSlotPickDTO;
use App\DTOs\Schedule\Makeup\RecordMakeupResponseDTO;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;
use App\Models\Schedule;
use App\Models\SchoolCalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── RecordMakeupResponseDTO ────────────────────────────────────────────────

it('parentRequest factory builds a REQUESTED dto with parent source', function () {
    $dto = RecordMakeupResponseDTO::parentRequest(42, 7);

    expect($dto->requestId)->toBe(42)
        ->and($dto->status)->toBe(ScheduleMakeupRequestStatus::REQUESTED)
        ->and($dto->respondedByType)->toBe(ScheduleMakeupRespondedByType::PARENT)
        ->and($dto->responseSource)->toBe(ScheduleMakeupResponseSource::EMAIL_LINK)
        ->and($dto->respondedByUserId)->toBe(7)
        ->and($dto->reason)->toBeNull();
});

it('parentDecline factory builds a DECLINED dto with optional reason', function () {
    $dto = RecordMakeupResponseDTO::parentDecline(10, 3, 'scheduling conflict');

    expect($dto->status)->toBe(ScheduleMakeupRequestStatus::DECLINED)
        ->and($dto->responseSource)->toBe(ScheduleMakeupResponseSource::EMAIL_LINK)
        ->and($dto->reason)->toBe('scheduling conflict');
});

it('parentDecline factory works without a reason', function () {
    $dto = RecordMakeupResponseDTO::parentDecline(10, 3);
    expect($dto->reason)->toBeNull();
});

it('therapistDecline factory builds a DECLINED dto with therapist source', function () {
    $dto = RecordMakeupResponseDTO::therapistDecline(5, 99, 'parent unreachable');

    expect($dto->status)->toBe(ScheduleMakeupRequestStatus::DECLINED)
        ->and($dto->respondedByType)->toBe(ScheduleMakeupRespondedByType::THERAPIST)
        ->and($dto->responseSource)->toBe(ScheduleMakeupResponseSource::THERAPIST_MANUAL)
        ->and($dto->respondedByUserId)->toBe(99)
        ->and($dto->reason)->toBe('parent unreachable');
});

it('toAttributes returns all expected keys', function () {
    $clock = CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC');
    $dto = RecordMakeupResponseDTO::parentRequest(1, 2, $clock);
    $attrs = $dto->toAttributes();

    expect($attrs)->toHaveKeys(['status', 'responded_by_type', 'response_source', 'responded_by_user_id', 'responded_at', 'reason'])
        ->and($attrs['status'])->toBe('requested')
        ->and($attrs['responded_by_type'])->toBe('parent')
        ->and($attrs['response_source'])->toBe('email_link')
        ->and($attrs['responded_by_user_id'])->toBe(2)
        ->and($attrs['responded_at'])->toBe('2026-06-01 10:00:00');
});

it('toAttributes includes reason null for parentRequest', function () {
    $dto = RecordMakeupResponseDTO::parentRequest(1, 2);
    expect($dto->toAttributes()['reason'])->toBeNull();
});

// ─── CreateMakeupRequestDTO ─────────────────────────────────────────────────

it('fromGeneration snapshots therapist_id from sub_therapist_id when set', function () {
    $main = \App\Models\User::factory()->therapist()->create();
    $sub = \App\Models\User::factory()->therapist()->create();
    $event = SchoolCalendarEvent::factory()->create();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $main->id,
        'sub_therapist_id' => $sub->id,
    ]);

    $dto = CreateMakeupRequestDTO::fromGeneration(
        event: $event,
        schedule: $schedule,
        eventDate: CarbonImmutable::parse('2026-09-07'),
        reminderDate: CarbonImmutable::parse('2026-09-01'),
        responseDate: CarbonImmutable::parse('2026-09-03'),
        batchNumber: 'MR_abc',
        responseToken: str_repeat('x', 64),
    );

    expect($dto->therapistId)->toBe($sub->id);
});

it('fromGeneration falls back to therapist_id when no sub', function () {
    $therapist = \App\Models\User::factory()->therapist()->create();
    $event = SchoolCalendarEvent::factory()->create();
    $schedule = Schedule::factory()->create([
        'therapist_id' => $therapist->id,
        'sub_therapist_id' => null,
    ]);

    $dto = CreateMakeupRequestDTO::fromGeneration(
        event: $event,
        schedule: $schedule,
        eventDate: CarbonImmutable::parse('2026-09-07'),
        reminderDate: CarbonImmutable::parse('2026-09-01'),
        responseDate: CarbonImmutable::parse('2026-09-03'),
        batchNumber: 'MR_abc',
        responseToken: str_repeat('x', 64),
    );

    expect($dto->therapistId)->toBe($therapist->id);
});

it('toAttributes maps all columns with correct date strings', function () {
    $therapist = \App\Models\User::factory()->therapist()->create();
    $event = SchoolCalendarEvent::factory()->create();
    $schedule = Schedule::factory()->create(['therapist_id' => $therapist->id, 'sub_therapist_id' => null]);

    $dto = CreateMakeupRequestDTO::fromGeneration(
        event: $event,
        schedule: $schedule,
        eventDate: CarbonImmutable::parse('2026-09-07'),
        reminderDate: CarbonImmutable::parse('2026-09-01'),
        responseDate: CarbonImmutable::parse('2026-09-03'),
        batchNumber: 'MR_test123',
        responseToken: str_repeat('t', 64),
    );

    $attrs = $dto->toAttributes();

    expect($attrs['event_date'])->toBe('2026-09-07')
        ->and($attrs['reminder_date'])->toBe('2026-09-01')
        ->and($attrs['response_date'])->toBe('2026-09-03')
        ->and($attrs['status'])->toBe('pending')
        ->and($attrs['batch_number'])->toBe('MR_test123')
        ->and($attrs['response_token'])->toBe(str_repeat('t', 64));
});

// ─── GenerateMakeupRemindersDTO ─────────────────────────────────────────────

it('fromConfig reads lookahead_days from config', function () {
    config(['schedule_makeup.generator_lookahead_days' => 14]);
    $dto = GenerateMakeupRemindersDTO::fromConfig();
    expect($dto->lookaheadDays)->toBe(14);
});

it('fromConfig uses provided today instead of now()', function () {
    $today = CarbonImmutable::parse('2026-06-15');
    $dto = GenerateMakeupRemindersDTO::fromConfig($today);
    expect($dto->today->toDateString())->toBe('2026-06-15');
});

// ─── MakeupSlotPickDTO ──────────────────────────────────────────────────────

it('date() returns only the Y-m-d portion of startUtc', function () {
    $pick = new MakeupSlotPickDTO(
        makeupRequestId: 1,
        startUtc: CarbonImmutable::parse('2026-06-15 14:30:00', 'UTC'),
        endUtc: CarbonImmutable::parse('2026-06-15 15:00:00', 'UTC'),
    );
    expect($pick->date())->toBe('2026-06-15');
});

it('startTime() and endTime() return H:i:s strings', function () {
    $pick = new MakeupSlotPickDTO(
        makeupRequestId: 1,
        startUtc: CarbonImmutable::parse('2026-06-15 14:30:00', 'UTC'),
        endUtc: CarbonImmutable::parse('2026-06-15 15:00:00', 'UTC'),
    );
    expect($pick->startTime())->toBe('14:30:00')
        ->and($pick->endTime())->toBe('15:00:00');
});
