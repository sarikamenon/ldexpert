<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentService;
use App\DTOs\SSAFilterDTO;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class StudentController extends Controller
{
    public function __construct(
        private readonly SSAService $ssaService,
        private readonly StudentService $studentService,
    ) {}

    public function index(Request $request): View
    {
        $therapist = $request->user();

        $students = $this->studentService->listByTherapist(
            $therapist->id,
            $request->query('search'),
            $request->query('status'),
            $request->integer('per_page', 15)
        );

        return view('therapist.students.index', [
            'students' => $students,
            'filters' => $request->query(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function show(Request $request, \App\Models\User $student): View
    {
        $therapist = $request->user();

        // Ensure student has SSAs assigned to this therapist
        if (! $this->ssaService->hasStudentAssignedToTherapist($student->id, $therapist->id)) {
            abort(403, 'You do not have access to this student.');
        }

        $student->load(['studentProfile.school', 'studentProfile.parent']);
        $activeTab = $request->query('tab', 'dashboard');

        $viewData = [
            'student' => $student,
            'activeTab' => $activeTab,
        ];

        // Load dashboard data
        if ($activeTab === 'dashboard' || $activeTab === 'overview') {
            $ssasForMetrics = $this->ssaService->getSSAsForMetrics($student->id, $therapist->id);

            $totalTho = (int) $ssasForMetrics->sum('tho_minutes');
            $served = (int) $ssasForMetrics->sum('served_minutes');

            $viewData['chartData'] = [
                'served' => $served,
                'remaining' => max(0, $totalTho - $served),
                'progress' => $totalTho > 0 ? round(($served / $totalTho) * 100, 1) : 0,
            ];

            $viewData['metrics'] = [
                'total_ssas' => $ssasForMetrics->count(),
                'active_ssas' => $ssasForMetrics->where('status', SSAStatus::ACTIVE)->count(),
                'completed_ssas' => $ssasForMetrics->where('status', SSAStatus::COMPLETED)->count(),
                'pending_ssas' => $ssasForMetrics->where('status', SSAStatus::PENDING)->count(),
            ];
        }

        // Load SSAs tab data
        if ($activeTab === 'ssas') {
            $filters = SSAFilterDTO::fromArray(
                array_merge($request->query(), [
                    'student_id' => $student->id,
                    'therapist_id' => $therapist->id,
                ])
            );
            $viewData['ssas'] = $this->ssaService->paginate($filters);
            $viewData['ssaFilters'] = $request->query();
            $viewData['statuses'] = SSAStatus::cases();
        }

        return view('therapist.students.show', $viewData);
    }
}
