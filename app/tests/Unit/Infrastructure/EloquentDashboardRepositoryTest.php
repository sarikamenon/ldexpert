<?php

declare(strict_types=1);

use App\Infrastructure\Repositories\EloquentDashboardRepository;
use App\Models\Position;
use App\Models\TherapistProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('therapists by position returns empty chart when no profiles', function () {
    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getTherapistsByPosition();

    expect($result['labels'])->toBe([])
        ->and($result['data'])->toBe([])
        ->and($result['colors'])->toBe([]);
});

test('therapists with null position_id are counted as Unassigned', function () {
    TherapistProfile::factory()->count(2)->create(['position_id' => null]);

    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getTherapistsByPosition();

    expect($result['labels'])->toBe(['Unassigned'])
        ->and($result['data'])->toBe([2])
        ->and($result['colors'])->toHaveCount(1);
});

test('therapists by position groups assigned and unassigned alphabetically', function () {
    $position = Position::factory()->create(['name' => 'OT']);
    TherapistProfile::factory()->create(['position_id' => null]);
    TherapistProfile::factory()->create(['position_id' => $position->id]);

    $repository = app(EloquentDashboardRepository::class);
    $result = $repository->getTherapistsByPosition();

    expect($result['labels'])->toBe(['OT', 'Unassigned'])
        ->and($result['data'])->toBe([1, 1])
        ->and($result['colors'])->toHaveCount(2);
});
