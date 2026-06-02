<?php

declare(strict_types=1);

use App\Domain\Schedule\Makeup\Services\ScheduleMakeupResponseService;
use App\Enums\ScheduleMakeupRequestStatus;
use App\Enums\ScheduleMakeupRespondedByType;
use App\Enums\ScheduleMakeupResponseSource;
use App\Exceptions\MakeupResponseNotAllowedException;
use App\Models\ScheduleMakeupRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(ScheduleMakeupResponseService::class);
});

// ─── guardCanRespond ────────────────────────────────────────────────────────

it('guardCanRespond throws BAD_STATE on empty batch', function () {
    $this->service->guardCanRespond(new Collection);
})->throws(MakeupResponseNotAllowedException::class, null);

it('guardCanRespond throws ALREADY_RESPONDED when responded_at is set', function () {
    $row = ScheduleMakeupRequest::factory()->requested()->create();
    // requested() factory sets responded_at

    try {
        $this->service->guardCanRespond(collect([$row]));
        $this->fail('Expected exception not thrown');
    } catch (MakeupResponseNotAllowedException $e) {
        expect($e->reason)->toBe(MakeupResponseNotAllowedException::REASON_ALREADY_RESPONDED);
    }
});

it('guardCanRespond throws BAD_STATE when status is not sent', function () {
    $row = ScheduleMakeupRequest::factory()->pending()->create([
        'responded_at' => null,
        'event_date' => now()->addDays(5)->toDateString(),
        'response_date' => now()->addDays(3)->toDateString(),
    ]);

    try {
        $this->service->guardCanRespond(collect([$row]));
        $this->fail('Expected exception not thrown');
    } catch (MakeupResponseNotAllowedException $e) {
        expect($e->reason)->toBe(MakeupResponseNotAllowedException::REASON_BAD_STATE);
    }
});

it('guardCanRespond throws DEADLINE_PASSED when today > response_date', function () {
    $row = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->subDay()->toDateString(),
        'event_date' => now()->addDays(5)->toDateString(),
    ]);

    $now = CarbonImmutable::now();
    try {
        $this->service->guardCanRespond(collect([$row]), $now);
        $this->fail('Expected exception not thrown');
    } catch (MakeupResponseNotAllowedException $e) {
        expect($e->reason)->toBe(MakeupResponseNotAllowedException::REASON_DEADLINE_PASSED);
    }
});

it('guardCanRespond throws EVENT_PAST when all event_dates are in the past', function () {
    $row = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->subDay()->toDateString(),
    ]);

    $now = CarbonImmutable::now();
    try {
        $this->service->guardCanRespond(collect([$row]), $now);
        $this->fail('Expected exception not thrown');
    } catch (MakeupResponseNotAllowedException $e) {
        expect($e->reason)->toBe(MakeupResponseNotAllowedException::REASON_EVENT_PAST);
    }
});

it('guardCanRespond passes when batch has a future event_date within deadline', function () {
    $row = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    // Should not throw
    $this->service->guardCanRespond(collect([$row]), CarbonImmutable::now());
    expect(true)->toBeTrue();
});

it('guardCanRespond passes for a mixed batch where at least one row is in the future', function () {
    $past = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->subDay()->toDateString(),
        'batch_number' => 'MR_'.str_repeat('x', 29),
        'response_token' => str_repeat('t', 64),
    ]);
    $future = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(5)->toDateString(),
        'batch_number' => $past->batch_number,
        'response_token' => $past->response_token,
    ]);

    // Should not throw — one future row keeps the batch valid
    $this->service->guardCanRespond(collect([$past, $future]), CarbonImmutable::now());
    expect(true)->toBeTrue();
});

// ─── recordParentDecline ────────────────────────────────────────────────────

it('recordParentDecline flips all batch rows to DECLINED with parent attribution', function () {
    $token = str_repeat('d', 64);
    $bn = 'MR_'.str_repeat('d', 29);

    $row1 = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
        'batch_number' => $bn,
        'response_token' => $token,
    ]);
    $row2 = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(8)->toDateString(),
        'batch_number' => $bn,
        'response_token' => $token,
    ]);

    $clock = CarbonImmutable::parse('2026-06-01 10:00:00', 'UTC');
    $this->service->recordParentDecline(collect([$row1, $row2]), null, $clock);

    foreach ([$row1, $row2] as $row) {
        $row->refresh();
        expect($row->status)->toBe(ScheduleMakeupRequestStatus::DECLINED)
            ->and($row->responded_by_type)->toBe(ScheduleMakeupRespondedByType::PARENT)
            ->and($row->response_source)->toBe(ScheduleMakeupResponseSource::EMAIL_LINK)
            ->and($row->responded_at)->not->toBeNull();
    }
});

it('recordParentDecline stores the decline reason on the rows', function () {
    $row = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    $this->service->recordParentDecline(collect([$row]), 'scheduling conflict', CarbonImmutable::now());

    expect($row->fresh()->reason)->toBe('scheduling conflict');
});

// ─── recordParentRequest ────────────────────────────────────────────────────

it('recordParentRequest flips batch rows to REQUESTED with parent attribution', function () {
    $row = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    $clock = CarbonImmutable::parse('2026-06-01 12:00:00', 'UTC');
    $this->service->recordParentRequest(collect([$row]), $clock);

    $row->refresh();
    expect($row->status)->toBe(ScheduleMakeupRequestStatus::REQUESTED)
        ->and($row->responded_by_type)->toBe(ScheduleMakeupRespondedByType::PARENT)
        ->and($row->response_source)->toBe(ScheduleMakeupResponseSource::EMAIL_LINK)
        ->and($row->responded_at->toDateTimeString())->toBe('2026-06-01 12:00:00');
});

it('recordParentRequest throws DEADLINE_PASSED after response_date', function () {
    $row = ScheduleMakeupRequest::factory()->sent()->create([
        'responded_at' => null,
        'response_date' => now()->subDays(2)->toDateString(),
        'event_date' => now()->addDays(5)->toDateString(),
    ]);

    try {
        $this->service->recordParentRequest(collect([$row]), CarbonImmutable::now());
        $this->fail('Expected exception not thrown');
    } catch (MakeupResponseNotAllowedException $e) {
        expect($e->reason)->toBe(MakeupResponseNotAllowedException::REASON_DEADLINE_PASSED);
    }
});

it('recordParentRequest throws ALREADY_RESPONDED on a second click', function () {
    $row = ScheduleMakeupRequest::factory()->requested()->create([
        'response_date' => now()->addDays(3)->toDateString(),
        'event_date' => now()->addDays(7)->toDateString(),
    ]);

    try {
        $this->service->recordParentRequest(collect([$row]), CarbonImmutable::now());
        $this->fail('Expected exception not thrown');
    } catch (MakeupResponseNotAllowedException $e) {
        expect($e->reason)->toBe(MakeupResponseNotAllowedException::REASON_ALREADY_RESPONDED);
    }
});

// ─── findBatchByToken ───────────────────────────────────────────────────────

it('findBatchByToken returns all rows sharing the response_token', function () {
    $token = str_repeat('f', 64);
    $bn = 'MR_'.str_repeat('f', 29);

    $r1 = ScheduleMakeupRequest::factory()->sent()->create(['response_token' => $token, 'batch_number' => $bn]);
    $r2 = ScheduleMakeupRequest::factory()->sent()->create(['response_token' => $token, 'batch_number' => $bn]);
    ScheduleMakeupRequest::factory()->sent()->create(); // different token

    $batch = $this->service->findBatchByToken($token);

    expect($batch)->toHaveCount(2)
        ->and($batch->pluck('id')->all())->toContain($r1->id)
        ->and($batch->pluck('id')->all())->toContain($r2->id);
});

it('findBatchByToken returns empty collection for unknown token', function () {
    $batch = $this->service->findBatchByToken(str_repeat('z', 64));
    expect($batch)->toHaveCount(0);
});
