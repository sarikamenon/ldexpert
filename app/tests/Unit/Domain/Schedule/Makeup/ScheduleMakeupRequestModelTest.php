<?php

declare(strict_types=1);

use App\Enums\ScheduleMakeupRequestStatus;
use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Boolean helpers ────────────────────────────────────────────────────────

it('isPending returns true only for PENDING status', function () {
    $r = ScheduleMakeupRequest::factory()->pending()->make();
    expect($r->isPending())->toBeTrue();
});

it('isPending returns false for non-pending statuses', function () {
    $r = ScheduleMakeupRequest::factory()->sent()->make();
    expect($r->isPending())->toBeFalse();
});

it('isSent returns true only for SENT status', function () {
    $r = ScheduleMakeupRequest::factory()->sent()->make();
    expect($r->isSent())->toBeTrue();
});

it('isSent returns false for non-sent statuses', function () {
    $r = ScheduleMakeupRequest::factory()->pending()->make();
    expect($r->isSent())->toBeFalse();
});

it('isRequested returns true only for REQUESTED status', function () {
    $r = ScheduleMakeupRequest::factory()->requested()->make();
    expect($r->isRequested())->toBeTrue();
});

it('isRequested returns false for non-requested statuses', function () {
    $r = ScheduleMakeupRequest::factory()->pending()->make();
    expect($r->isRequested())->toBeFalse();
});

it('isResponded returns true when responded_at is set', function () {
    $r = ScheduleMakeupRequest::factory()->requested()->make();
    expect($r->isResponded())->toBeTrue();
});

it('isResponded returns false when responded_at is null', function () {
    $r = ScheduleMakeupRequest::factory()->pending()->make();
    expect($r->isResponded())->toBeFalse();
});

// ─── scopePending ───────────────────────────────────────────────────────────

it('scopePending returns only pending rows', function () {
    $pending = ScheduleMakeupRequest::factory()->pending()->create();
    ScheduleMakeupRequest::factory()->sent()->create();
    ScheduleMakeupRequest::factory()->declined()->create();

    $ids = ScheduleMakeupRequest::query()->pending()->pluck('id');

    expect($ids)->toHaveCount(1)->toContain($pending->id);
});

// ─── scopeSent ──────────────────────────────────────────────────────────────

it('scopeSent returns only sent rows', function () {
    $sent = ScheduleMakeupRequest::factory()->sent()->create();
    ScheduleMakeupRequest::factory()->pending()->create();
    ScheduleMakeupRequest::factory()->requested()->create();

    $ids = ScheduleMakeupRequest::query()->sent()->pluck('id');

    expect($ids)->toHaveCount(1)->toContain($sent->id);
});

// ─── scopeWithStatus ────────────────────────────────────────────────────────

it('scopeWithStatus filters by the given status', function () {
    $requested = ScheduleMakeupRequest::factory()->requested()->create();
    ScheduleMakeupRequest::factory()->pending()->create();
    ScheduleMakeupRequest::factory()->declined()->create();

    $ids = ScheduleMakeupRequest::query()
        ->withStatus(ScheduleMakeupRequestStatus::REQUESTED)
        ->pluck('id');

    expect($ids)->toHaveCount(1)->toContain($requested->id);
});

// ─── scopeForBatch ──────────────────────────────────────────────────────────

it('scopeForBatch returns all rows sharing the same batch_number', function () {
    $bn = 'MR_'.str_repeat('a', 29);

    $r1 = ScheduleMakeupRequest::factory()->pending()->create(['batch_number' => $bn]);
    $r2 = ScheduleMakeupRequest::factory()->pending()->create(['batch_number' => $bn]);
    ScheduleMakeupRequest::factory()->pending()->create(['batch_number' => 'MR_'.str_repeat('b', 29)]);

    $ids = ScheduleMakeupRequest::query()->forBatch($bn)->pluck('id');

    expect($ids)->toHaveCount(2)
        ->toContain($r1->id)
        ->toContain($r2->id);
});

// ─── scopeDueForReminder ────────────────────────────────────────────────────

it('scopeDueForReminder returns pending rows whose reminder_date <= given date', function () {
    $due = ScheduleMakeupRequest::factory()->pending()->create([
        'reminder_date' => now()->subDay()->toDateString(),
    ]);
    $today = ScheduleMakeupRequest::factory()->pending()->create([
        'reminder_date' => now()->toDateString(),
    ]);
    // Future — must not appear
    ScheduleMakeupRequest::factory()->pending()->create([
        'reminder_date' => now()->addDays(3)->toDateString(),
    ]);
    // Sent — must not appear even if date past
    ScheduleMakeupRequest::factory()->sent()->create([
        'reminder_date' => now()->subDay()->toDateString(),
    ]);

    $ids = ScheduleMakeupRequest::query()->dueForReminder(now())->pluck('id');

    expect($ids)->toHaveCount(2)
        ->toContain($due->id)
        ->toContain($today->id);
});

// ─── scopeOverdueForResponse ────────────────────────────────────────────────

it('scopeOverdueForResponse returns sent, no response, past response_date', function () {
    $overdue = ScheduleMakeupRequest::factory()->sent()->create([
        'response_date' => now()->subDay()->toDateString(),
        'responded_at' => null,
    ]);
    // responded — excluded
    ScheduleMakeupRequest::factory()->sent()->create([
        'response_date' => now()->subDay()->toDateString(),
        'responded_at' => now(),
    ]);
    // not yet overdue — excluded
    ScheduleMakeupRequest::factory()->sent()->create([
        'response_date' => now()->toDateString(),
        'responded_at' => null,
    ]);
    // pending — excluded
    ScheduleMakeupRequest::factory()->pending()->create([
        'response_date' => now()->subDay()->toDateString(),
        'responded_at' => null,
    ]);

    $ids = ScheduleMakeupRequest::query()->overdueForResponse(now())->pluck('id');

    expect($ids)->toHaveCount(1)->toContain($overdue->id);
});

// ─── scopeForTherapist ──────────────────────────────────────────────────────

it('scopeForTherapist filters by therapist_id', function () {
    $therapist = \App\Models\User::factory()->therapist()->create();
    $mine = ScheduleMakeupRequest::factory()->pending()->create(['therapist_id' => $therapist->id]);
    ScheduleMakeupRequest::factory()->pending()->create(); // different therapist

    $ids = ScheduleMakeupRequest::query()->forTherapist($therapist)->pluck('id');

    expect($ids)->toHaveCount(1)->toContain($mine->id);
});

// ─── scopeUnresponded ───────────────────────────────────────────────────────

it('scopeUnresponded includes pending and sent with no responded_at', function () {
    $p = ScheduleMakeupRequest::factory()->pending()->create(['responded_at' => null]);
    $s = ScheduleMakeupRequest::factory()->sent()->create(['responded_at' => null]);
    ScheduleMakeupRequest::factory()->requested()->create(); // has responded_at
    ScheduleMakeupRequest::factory()->declined()->create();
    ScheduleMakeupRequest::factory()->scheduled(Schedule::factory()->create())->create();

    $ids = ScheduleMakeupRequest::query()->unresponded()->pluck('id');

    expect($ids)->toHaveCount(2)->toContain($p->id)->toContain($s->id);
});

// ─── casts ──────────────────────────────────────────────────────────────────

it('status is cast to ScheduleMakeupRequestStatus enum', function () {
    $r = ScheduleMakeupRequest::factory()->pending()->create();
    expect($r->fresh()->status)->toBe(ScheduleMakeupRequestStatus::PENDING);
});

it('event_date is cast to a Carbon date', function () {
    $r = ScheduleMakeupRequest::factory()->pending()->create([
        'event_date' => '2026-09-07',
    ]);
    expect($r->fresh()->event_date)->toBeInstanceOf(Carbon::class);
});
