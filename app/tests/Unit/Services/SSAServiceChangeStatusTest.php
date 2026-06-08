<?php

declare(strict_types=1);

use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\SSA\Services\SSAService;
use App\DTOs\ChangeSSAStatusDTO;
use App\Enums\SSAStatus;
use App\Models\ServiceSupportAgreement;
use Illuminate\Validation\ValidationException;

function makeSSAService(): SSAService
{
    $repo = Mockery::mock(SSARepositoryInterface::class);
    $repo->allows('changeStatus')->andReturnUsing(fn($ssa, $dto) => $ssa);

    return new SSAService($repo);
}

function makeSsa(SSAStatus $status, ?int $therapistId = null): ServiceSupportAgreement
{
    $ssa = new ServiceSupportAgreement();
    $ssa->status = $status;
    $ssa->assigned_therapist_id = $therapistId;

    return $ssa;
}

function changeDto(SSAStatus $status): ChangeSSAStatusDTO
{
    return new ChangeSSAStatusDTO(status: $status, reason: null);
}

afterEach(fn() => Mockery::close());

it('allows deactivating a pending SSA', function () {
    $service = makeSSAService();
    $ssa = makeSsa(SSAStatus::PENDING);

    $result = $service->changeStatus($ssa, changeDto(SSAStatus::DEACTIVATED));

    expect($result)->toBe($ssa);
});

it('allows deactivating an active SSA', function () {
    $service = makeSSAService();
    $ssa = makeSsa(SSAStatus::ACTIVE, therapistId: 1);

    $result = $service->changeStatus($ssa, changeDto(SSAStatus::DEACTIVATED));

    expect($result)->toBe($ssa);
});

it('prevents deactivating a completed SSA', function () {
    $service = makeSSAService();
    $ssa = makeSsa(SSAStatus::COMPLETED);

    expect(fn() => $service->changeStatus($ssa, changeDto(SSAStatus::DEACTIVATED)))
        ->toThrow(ValidationException::class);
});

it('prevents completing a pending SSA', function () {
    $service = makeSSAService();
    $ssa = makeSsa(SSAStatus::PENDING);

    expect(fn() => $service->changeStatus($ssa, changeDto(SSAStatus::COMPLETED)))
        ->toThrow(ValidationException::class, 'Can only Completed a Active SSA.');
});

it('prevents any change from a completed SSA', function () {
    $service = makeSSAService();
    $ssa = makeSsa(SSAStatus::COMPLETED);

    expect(fn() => $service->changeStatus($ssa, changeDto(SSAStatus::ACTIVE)))
        ->toThrow(ValidationException::class, 'Cannot change status of a completed SSA.');
});

it('prevents reactivating a deactivated SSA to non-active status', function () {
    $service = makeSSAService();
    $ssa = makeSsa(SSAStatus::DEACTIVATED);

    expect(fn() => $service->changeStatus($ssa, changeDto(SSAStatus::COMPLETED)))
        ->toThrow(ValidationException::class, 'A deactivated SSA can only be reactivated.');
});

it('allows reactivating a deactivated SSA when therapist is assigned', function () {
    $service = makeSSAService();
    $ssa = makeSsa(SSAStatus::DEACTIVATED, therapistId: 1);

    $result = $service->changeStatus($ssa, changeDto(SSAStatus::ACTIVE));

    expect($result)->toBe($ssa);
});

it('prevents activating SSA without therapist', function () {
    $service = makeSSAService();
    $ssa = makeSsa(SSAStatus::PENDING, therapistId: null);

    expect(fn() => $service->changeStatus($ssa, changeDto(SSAStatus::ACTIVE)))
        ->toThrow(ValidationException::class, 'Cannot activate SSA without an assigned therapist.');
});
