<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\SSA\Services\SSAService;
use App\DTOs\SSAFilterDTO;
use App\Enums\SSAStatus;
use App\Http\Controllers\Controller;
use App\Models\ServiceSupportAgreement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SSAController extends Controller
{
    public function __construct(
        private readonly SSAService $ssaService,
    ) {}

    public function index(Request $request): View
    {
        $therapist = $request->user();

        // Build filters with therapist constraint
        $filters = SSAFilterDTO::fromArray(
            array_merge($request->query(), ['therapist_id' => $therapist->id])
        );

        // Get SSAs assigned to this therapist
        $ssas = $this->ssaService->paginate($filters);

        return view('therapist.ssas.index', [
            'ssas' => $ssas,
            'filters' => $request->query(),
            'statuses' => SSAStatus::cases(),
        ]);
    }

    public function show(Request $request, ServiceSupportAgreement $ssa): View
    {
        $therapist = $request->user();

        // Ensure therapist can only view SSAs assigned to them
        if ($ssa->assigned_therapist_id !== $therapist->id) {
            abort(403, 'You do not have access to this SSA.');
        }

        $activeTab = $request->query('tab', 'dashboard');

        $ssa->load([
            'student',
            'student.studentProfile.school',
            'primaryService',
            'additionalServices',
            'assignedTherapist',
            'assignedTherapist.therapistProfile',
        ]);

        $viewData = [
            'ssa' => $ssa,
            'activeTab' => $activeTab,
        ];

        // Load assignment history if needed
        if ($activeTab === 'assignment') {
            $ssa->load([
                'assignmentHistory.therapist',
                'assignmentHistory.assignedBy',
            ]);
            $viewData['assignmentHistory'] = $this->ssaService->getAssignmentHistory($ssa);
        }

        return view('therapist.ssas.show', $viewData);
    }
}
