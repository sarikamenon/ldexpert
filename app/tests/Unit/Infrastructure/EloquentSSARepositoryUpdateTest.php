<?php

declare(strict_types=1);

use App\DTOs\UpdateSSADTO;
use App\Enums\SSAStatus;
use App\Infrastructure\Repositories\EloquentSSARepository;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAAssignmentHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ssaUpdateRepo(): EloquentSSARepository
{
    return app(EloquentSSARepository::class);
}

function ssaUpdateActingAdmin(): User
{
    /** @var User $admin */
    $admin = User::factory()->admin()->create();
    test()->actingAs($admin); // @phpstan-ignore method.notFound

    return $admin;
}

it('clears therapist, logs unassigned history, and resets status to pending when assigned_therapist_id is set to null on an active SSA', function () {
    $admin = ssaUpdateActingAdmin();
    $therapist = User::factory()->therapist()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
    ]);

    $dto = UpdateSSADTO::fromArray(['assigned_therapist_id' => null]);

    $updated = ssaUpdateRepo()->update($ssa, $dto);

    expect($updated->assigned_therapist_id)->toBeNull();
    expect($updated->status)->toBe(SSAStatus::PENDING);

    $history = SSAAssignmentHistory::where('ssa_id', $ssa->id)->get();
    expect($history)->toHaveCount(1);
    $entry = $history->firstOrFail();
    expect($entry->action)->toBe('unassigned');
    expect($entry->therapist_id)->toBe($therapist->id);
    expect($entry->assigned_by)->toBe($admin->id);
    expect($entry->unassigned_at)->not->toBeNull();
});

it('assigns therapist, logs assigned history, and activates a pending SSA when assigned_therapist_id is set from null', function () {
    ssaUpdateActingAdmin();
    $therapist = User::factory()->therapist()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING,
    ]);

    $dto = UpdateSSADTO::fromArray(['assigned_therapist_id' => $therapist->id]);

    $updated = ssaUpdateRepo()->update($ssa, $dto);

    expect($updated->assigned_therapist_id)->toBe($therapist->id);
    expect($updated->status)->toBe(SSAStatus::ACTIVE);

    $history = SSAAssignmentHistory::where('ssa_id', $ssa->id)->get();
    expect($history)->toHaveCount(1);
    $entry = $history->firstOrFail();
    expect($entry->action)->toBe('assigned');
    expect($entry->therapist_id)->toBe($therapist->id);
    expect($entry->assigned_at)->not->toBeNull();
});

it('logs unassigned + assigned history and keeps status active when therapist is swapped to a different therapist', function () {
    ssaUpdateActingAdmin();
    $previousTherapist = User::factory()->therapist()->create();
    $newTherapist = User::factory()->therapist()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $previousTherapist->id,
        'status' => SSAStatus::ACTIVE,
    ]);

    $dto = UpdateSSADTO::fromArray(['assigned_therapist_id' => $newTherapist->id]);

    $updated = ssaUpdateRepo()->update($ssa, $dto);

    expect($updated->assigned_therapist_id)->toBe($newTherapist->id);
    expect($updated->status)->toBe(SSAStatus::ACTIVE);

    $history = SSAAssignmentHistory::where('ssa_id', $ssa->id)
        ->orderBy('id')
        ->get()
        ->values();
    expect($history)->toHaveCount(2);
    $first = $history->firstOrFail();
    $second = $history->skip(1)->firstOrFail();
    expect($first->action)->toBe('unassigned');
    expect($first->therapist_id)->toBe($previousTherapist->id);
    expect($second->action)->toBe('assigned');
    expect($second->therapist_id)->toBe($newTherapist->id);
});

it('does not write history or touch status when assigned_therapist_id is unchanged', function () {
    ssaUpdateActingAdmin();
    $therapist = User::factory()->therapist()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
    ]);

    $dto = UpdateSSADTO::fromArray([
        'assigned_therapist_id' => $therapist->id,
        'additional_notes' => 'updated note',
    ]);

    $updated = ssaUpdateRepo()->update($ssa, $dto);

    expect($updated->assigned_therapist_id)->toBe($therapist->id);
    expect($updated->status)->toBe(SSAStatus::ACTIVE);
    expect(SSAAssignmentHistory::where('ssa_id', $ssa->id)->count())->toBe(0);
});

it('does not write history or change status when assigned_therapist_id key is omitted from the update payload', function () {
    ssaUpdateActingAdmin();
    $therapist = User::factory()->therapist()->create();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE,
    ]);

    $dto = UpdateSSADTO::fromArray(['additional_notes' => 'just a note edit']);

    $updated = ssaUpdateRepo()->update($ssa, $dto);

    expect($updated->assigned_therapist_id)->toBe($therapist->id);
    expect($updated->status)->toBe(SSAStatus::ACTIVE);
    expect(SSAAssignmentHistory::where('ssa_id', $ssa->id)->count())->toBe(0);
});

it('clears therapist but leaves status alone when SSA was already pending with no therapist', function () {
    ssaUpdateActingAdmin();
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING,
    ]);

    $dto = UpdateSSADTO::fromArray(['assigned_therapist_id' => null]);

    $updated = ssaUpdateRepo()->update($ssa, $dto);

    expect($updated->assigned_therapist_id)->toBeNull();
    expect($updated->status)->toBe(SSAStatus::PENDING);
    expect(SSAAssignmentHistory::where('ssa_id', $ssa->id)->count())->toBe(0);
});
