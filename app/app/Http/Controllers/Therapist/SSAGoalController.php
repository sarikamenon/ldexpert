<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\SSA\Services\SSAGoalService;
use App\DTOs\CreateSSAGoalDTO;
use App\DTOs\UpdateSSAGoalDTO;
use App\Enums\SSAGoalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SSAGoal\ChangeSSAGoalStatusRequest;
use App\Http\Requests\SSAGoal\StoreSSAGoalRequest;
use App\Http\Requests\SSAGoal\UpdateSSAGoalRequest;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAGoal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SSAGoalController extends Controller
{
    public function __construct(
        private readonly SSAGoalService $goalService,
    ) {}

    public function create(ServiceSupportAgreement $ssa): View
    {
        $this->authorize('create', [SSAGoal::class, $ssa]);

        return view('therapist.ssas.goals.create', [
            'ssa' => $ssa,
            'goal' => null,
            'formAction' => route('therapist.ssas.goals.store', $ssa),
            'cancelUrl' => route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']),
        ]);
    }

    public function store(StoreSSAGoalRequest $request, ServiceSupportAgreement $ssa): RedirectResponse
    {
        $this->authorize('create', [SSAGoal::class, $ssa]);

        try {
            $dto = CreateSSAGoalDTO::fromArray(array_merge($request->validated(), [
                'ssa_id' => $ssa->id,
                'student_id' => $ssa->student_id,
            ]));
            $this->goalService->create($dto);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->withErrors(['goal' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Failed to create SSA goal', ['ssa_id' => $ssa->id, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()->withErrors(['goal' => 'Could not create the goal. Please try again.']);
        }

        return redirect()
            ->route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals'])
            ->with('status', 'Goal added successfully.');
    }

    public function edit(ServiceSupportAgreement $ssa, SSAGoal $goal): View
    {
        $this->authorize('update', $goal);
        $this->ensureGoalBelongsToSsa($ssa, $goal);

        return view('therapist.ssas.goals.edit', [
            'ssa' => $ssa,
            'goal' => $goal,
            'formAction' => route('therapist.ssas.goals.update', ['ssa' => $ssa, 'goal' => $goal]),
            'cancelUrl' => route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals']),
        ]);
    }

    public function update(UpdateSSAGoalRequest $request, ServiceSupportAgreement $ssa, SSAGoal $goal): RedirectResponse
    {
        $this->authorize('update', $goal);
        $this->ensureGoalBelongsToSsa($ssa, $goal);

        try {
            $this->goalService->update($goal, UpdateSSAGoalDTO::fromArray($request->validated()));
        } catch (Throwable $e) {
            Log::error('Failed to update SSA goal', ['goal_id' => $goal->id, 'error' => $e->getMessage()]);

            return redirect()->back()->withInput()->withErrors(['goal' => 'Could not update the goal. Please try again.']);
        }

        return redirect()
            ->route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals'])
            ->with('status', 'Goal updated successfully.');
    }

    public function changeStatus(ChangeSSAGoalStatusRequest $request, ServiceSupportAgreement $ssa, SSAGoal $goal): RedirectResponse
    {
        $this->authorize('changeStatus', $goal);
        $this->ensureGoalBelongsToSsa($ssa, $goal);

        try {
            $status = SSAGoalStatus::from((string) $request->validated()['status']);
            $this->goalService->changeStatus($goal, $status);
        } catch (Throwable $e) {
            Log::error('Failed to change SSA goal status', ['goal_id' => $goal->id, 'error' => $e->getMessage()]);

            return redirect()->back()->withErrors(['goal' => 'Could not update goal status. Please try again.']);
        }

        return redirect()
            ->route('therapist.ssas.show', ['ssa' => $ssa, 'tab' => 'goals'])
            ->with('status', 'Goal status updated.');
    }

    private function ensureGoalBelongsToSsa(ServiceSupportAgreement $ssa, SSAGoal $goal): void
    {
        abort_unless($goal->ssa_id === $ssa->id, 404);
    }
}
