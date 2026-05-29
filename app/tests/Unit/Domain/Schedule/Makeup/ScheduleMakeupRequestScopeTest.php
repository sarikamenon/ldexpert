<?php

declare(strict_types=1);

use App\Models\Schedule;
use App\Models\ScheduleMakeupRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('unresponded scope returns only pending and sent rows with no responded_at', function () {
    $pending = ScheduleMakeupRequest::factory()->pending()->create();
    $sent = ScheduleMakeupRequest::factory()->sent()->create();

    // Excluded: responded (REQUESTED), declined, scheduled.
    ScheduleMakeupRequest::factory()->requested()->create();
    ScheduleMakeupRequest::factory()->declined()->create();
    ScheduleMakeupRequest::factory()->scheduled(Schedule::factory()->create())->create();

    $ids = ScheduleMakeupRequest::query()->unresponded()->pluck('id');

    expect($ids)->toHaveCount(2)
        ->and($ids)->toContain($pending->id)
        ->and($ids)->toContain($sent->id);
});

it('unresponded scope excludes a pending row that already has responded_at', function () {
    // A row left in PENDING status but stamped responded_at must not surface.
    ScheduleMakeupRequest::factory()->pending()->create([
        'responded_at' => now(),
    ]);

    expect(ScheduleMakeupRequest::query()->unresponded()->count())->toBe(0);
});
