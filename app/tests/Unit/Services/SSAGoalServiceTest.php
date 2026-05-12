<?php

declare(strict_types=1);

use App\Domain\SSA\Repositories\SSAGoalRepositoryInterface;
use App\Domain\SSA\Repositories\SSARepositoryInterface;
use App\Domain\SSA\Services\SSAGoalService;
use App\DTOs\CreateSSAGoalDTO;
use App\DTOs\UpdateSSAGoalDTO;
use App\Enums\SSAGoalStatus;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use Illuminate\Database\Eloquent\Collection;
use Mockery\MockInterface;

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

function makeGoalService(): array
{
    $goals = Mockery::mock(SSAGoalRepositoryInterface::class);
    $ssas = Mockery::mock(SSARepositoryInterface::class);
    $service = new SSAGoalService($goals, $ssas);

    return [$service, $goals, $ssas];
}

afterEach(fn () => Mockery::close());

// ---------------------------------------------------------------------------
// listForSsa — attaches can_transition_status flag
// ---------------------------------------------------------------------------

it('listForSsa attaches can_transition_status=true to active goals', function () {
    /** @var SSAGoalService $service */
    /** @var MockInterface $goals */
    [$service, $goals] = makeGoalService();

    $goal = new SSAGoal(['status' => SSAGoalStatus::ACTIVE]);

    $goals->shouldReceive('listForSsa')
        ->once()
        ->with(1)
        ->andReturn(new Collection([$goal]));

    $result = $service->listForSsa(1);

    expect($result->first()->can_transition_status)->toBeTrue();
});

it('listForSsa attaches can_transition_status=false to mastered goals', function () {
    [$service, $goals] = makeGoalService();

    $goal = new SSAGoal(['status' => SSAGoalStatus::MASTERED]);

    $goals->shouldReceive('listForSsa')
        ->once()
        ->with(2)
        ->andReturn(new Collection([$goal]));

    $result = $service->listForSsa(2);

    expect($result->first()->can_transition_status)->toBeFalse();
});

it('listForSsa attaches can_transition_status=false to discontinued goals', function () {
    [$service, $goals] = makeGoalService();

    $goal = new SSAGoal(['status' => SSAGoalStatus::DISCONTINUED]);

    $goals->shouldReceive('listForSsa')
        ->once()
        ->with(3)
        ->andReturn(new Collection([$goal]));

    $result = $service->listForSsa(3);

    expect($result->first()->can_transition_status)->toBeFalse();
});

// ---------------------------------------------------------------------------
// listActiveForSsa
// ---------------------------------------------------------------------------

it('listActiveForSsa delegates to repository and flags goals', function () {
    [$service, $goals] = makeGoalService();

    $goal = new SSAGoal(['status' => SSAGoalStatus::ACTIVE]);

    $goals->shouldReceive('listActiveForSsa')
        ->once()
        ->with(5)
        ->andReturn(new Collection([$goal]));

    $result = $service->listActiveForSsa(5);

    expect($result)->toHaveCount(1)
        ->and($result->first()->can_transition_status)->toBeTrue();
});

// ---------------------------------------------------------------------------
// create — happy path and guards
// ---------------------------------------------------------------------------

it('create delegates to repository when ssa and student match', function () {
    [$service, $goals, $ssas] = makeGoalService();

    $ssa = new ServiceSupportAgreement();
    $ssa->id = 1;
    $ssa->student_id = 10;

    $dto = CreateSSAGoalDTO::fromArray([
        'ssa_id' => 1,
        'student_id' => 10,
        'number' => '1',
        'objective' => 'Objective text.',
        'progress' => null,
    ]);

    $createdGoal = new SSAGoal();

    $ssas->shouldReceive('find')->once()->with(1)->andReturn($ssa);
    $goals->shouldReceive('create')->once()->with($dto)->andReturn($createdGoal);

    $result = $service->create($dto);

    expect($result)->toBe($createdGoal);
});

it('create throws InvalidArgumentException when SSA is not found', function () {
    [$service, $goals, $ssas] = makeGoalService();

    $dto = CreateSSAGoalDTO::fromArray([
        'ssa_id' => 999,
        'student_id' => 1,
        'number' => '1',
        'objective' => 'Objective.',
        'progress' => null,
    ]);

    $ssas->shouldReceive('find')->once()->with(999)->andReturnNull();
    $goals->shouldNotReceive('create');

    expect(fn () => $service->create($dto))
        ->toThrow(InvalidArgumentException::class, 'SSA not found.');
});

it('create throws InvalidArgumentException when student does not match the SSA', function () {
    [$service, $goals, $ssas] = makeGoalService();

    $ssa = new ServiceSupportAgreement();
    $ssa->id = 1;
    $ssa->student_id = 10; // mismatches dto

    $dto = CreateSSAGoalDTO::fromArray([
        'ssa_id' => 1,
        'student_id' => 99,
        'number' => '1',
        'objective' => 'Objective.',
        'progress' => null,
    ]);

    $ssas->shouldReceive('find')->once()->with(1)->andReturn($ssa);
    $goals->shouldNotReceive('create');

    expect(fn () => $service->create($dto))
        ->toThrow(InvalidArgumentException::class, 'Student does not match the SSA.');
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

it('update delegates to repository', function () {
    [$service, $goals] = makeGoalService();

    $goal = new SSAGoal();
    $dto = UpdateSSAGoalDTO::fromArray([
        'number' => '2',
        'objective' => 'Updated objective.',
        'progress' => null,
    ]);
    $updatedGoal = new SSAGoal();

    $goals->shouldReceive('update')->once()->with($goal, $dto)->andReturn($updatedGoal);

    $result = $service->update($goal, $dto);

    expect($result)->toBe($updatedGoal);
});

// ---------------------------------------------------------------------------
// changeStatus
// ---------------------------------------------------------------------------

it('changeStatus delegates to repository', function () {
    [$service, $goals] = makeGoalService();

    $goal = new SSAGoal();
    $changedGoal = new SSAGoal();

    $goals->shouldReceive('changeStatus')
        ->once()
        ->with($goal, SSAGoalStatus::MASTERED)
        ->andReturn($changedGoal);

    $result = $service->changeStatus($goal, SSAGoalStatus::MASTERED);

    expect($result)->toBe($changedGoal);
});

it('changeStatus works for discontinued status', function () {
    [$service, $goals] = makeGoalService();

    $goal = new SSAGoal();
    $changedGoal = new SSAGoal();

    $goals->shouldReceive('changeStatus')
        ->once()
        ->with($goal, SSAGoalStatus::DISCONTINUED)
        ->andReturn($changedGoal);

    $result = $service->changeStatus($goal, SSAGoalStatus::DISCONTINUED);

    expect($result)->toBe($changedGoal);
});

// ---------------------------------------------------------------------------
// getMetricsForSsa
// ---------------------------------------------------------------------------

it('getMetricsForSsa delegates to repository', function () {
    [$service, $goals] = makeGoalService();

    $metrics = [
        'total_goals' => 5,
        'active_goals' => 2,
        'mastered_goals' => 2,
        'discontinued_goals' => 1,
        'mastery_rate' => 66.7,
    ];

    $goals->shouldReceive('getMetricsForSsa')
        ->once()
        ->with(1)
        ->andReturn($metrics);

    $result = $service->getMetricsForSsa(1);

    expect($result)->toBe($metrics)
        ->and($result['total_goals'])->toBe(5)
        ->and($result['active_goals'])->toBe(2)
        ->and($result['mastered_goals'])->toBe(2)
        ->and($result['discontinued_goals'])->toBe(1)
        ->and($result['mastery_rate'])->toBe(66.7);
});
