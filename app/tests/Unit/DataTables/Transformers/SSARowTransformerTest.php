<?php

declare(strict_types=1);

use App\DataTables\Transformers\SSARowTransformer;
use App\Enums\Role;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows Unassigned text in therapist cell when no therapist is assigned', function () {
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $ssa->load(['student', 'primaryService', 'assignedTherapist', 'student.studentProfile.school']);

    $row = SSARowTransformer::transform($ssa);
    $therapistCell = $row[2];

    expect($therapistCell)->toContain('Unassigned');
});

it('shows therapist name link in therapist cell when therapist is assigned', function () {
    $therapist = User::factory()->create([
        'role' => Role::THERAPIST,
        'status' => UserStatus::ACTIVE,
        'name' => 'Jane Smith',
    ]);

    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    $ssa->load(['student', 'primaryService', 'assignedTherapist', 'student.studentProfile.school']);

    $row = SSARowTransformer::transform($ssa);
    $therapistCell = $row[2];

    expect($therapistCell)->toContain('Jane Smith');
    expect($therapistCell)->toContain(route('admin.therapists.show', $therapist));
});

it('includes assign therapist button in actions cell when SSA is unassigned', function () {
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $ssa->load(['student', 'primaryService', 'assignedTherapist', 'student.studentProfile.school']);

    $row = SSARowTransformer::transform($ssa);
    $actionsCell = $row[6];

    expect($actionsCell)
        ->toContain('assign-therapist-btn')
        ->toContain('data-ssa-id="'.$ssa->id.'"')
        ->toContain('Assign Therapist');
});

it('does not include assign therapist button in actions cell when therapist is assigned', function () {
    $therapist = User::factory()->create([
        'role' => Role::THERAPIST,
        'status' => UserStatus::ACTIVE,
    ]);

    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => $therapist->id,
        'status' => SSAStatus::ACTIVE->value,
    ]);

    $ssa->load(['student', 'primaryService', 'assignedTherapist', 'student.studentProfile.school']);

    $row = SSARowTransformer::transform($ssa);
    $actionsCell = $row[6];

    expect($actionsCell)->not->toContain('assign-therapist-btn');
});

it('includes correct data attributes on assign button for modal population', function () {
    $ssa = ServiceSupportAgreement::factory()->create([
        'assigned_therapist_id' => null,
        'status' => SSAStatus::PENDING->value,
    ]);

    $ssa->load(['student', 'primaryService', 'assignedTherapist', 'student.studentProfile.school']);

    $row = SSARowTransformer::transform($ssa);
    $actionsCell = $row[6];

    expect($actionsCell)
        ->toContain('data-ssa-name=')
        ->toContain('data-ssa-status=')
        ->toContain('data-service-name=')
        ->toContain('data-service-ids=');
});
