<?php

declare(strict_types=1);

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\Domain\Therapist\Services\SessionLogService;
use App\Models\SessionLog;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function deleteService(SessionLogRepositoryInterface $repository): SessionLogService
{
    app()->instance(SessionLogRepositoryInterface::class, $repository);

    return app(SessionLogService::class);
}

dataset('non_approved_states', [
    'draft' => ['draft'],
    'submitted' => ['submitted'],
    'sent_back' => ['sentBack'],
    'cancelled' => ['cancelled'],
]);

it('deletes a non-approved log via the repository', function (string $state) {
    $therapist = User::factory()->therapist()->create();
    $sessionLog = SessionLog::factory()->{$state}()->create([
        'therapist_id' => $therapist->id,
    ]);

    $repository = Mockery::mock(SessionLogRepositoryInterface::class);
    $repository->shouldReceive('deleteAndUnbill')->once()->with($sessionLog);

    deleteService($repository)->deleteSessionLog($therapist, $sessionLog);
})->with('non_approved_states');

it('lets an admin delete a therapist-owned log', function () {
    $admin = User::factory()->admin()->create();
    $sessionLog = SessionLog::factory()->draft()->create();

    $repository = Mockery::mock(SessionLogRepositoryInterface::class);
    $repository->shouldReceive('deleteAndUnbill')->once()->with($sessionLog);

    deleteService($repository)->deleteSessionLog($admin, $sessionLog);
});

it('refuses to delete an approved log', function () {
    $therapist = User::factory()->therapist()->create();
    $sessionLog = SessionLog::factory()->approved()->create([
        'therapist_id' => $therapist->id,
    ]);

    $repository = Mockery::mock(SessionLogRepositoryInterface::class);
    $repository->shouldNotReceive('deleteAndUnbill');

    expect(fn () => deleteService($repository)->deleteSessionLog($therapist, $sessionLog))
        ->toThrow(InvalidArgumentException::class, 'Approved session logs cannot be deleted.');
});

it('refuses to delete a log owned by another therapist', function () {
    $owner = User::factory()->therapist()->create();
    $other = User::factory()->therapist()->create();
    $sessionLog = SessionLog::factory()->draft()->create([
        'therapist_id' => $owner->id,
    ]);

    $repository = Mockery::mock(SessionLogRepositoryInterface::class);
    $repository->shouldNotReceive('deleteAndUnbill');

    expect(fn () => deleteService($repository)->deleteSessionLog($other, $sessionLog))
        ->toThrow(InvalidArgumentException::class, 'Therapist does not have access to this session log.');
});
