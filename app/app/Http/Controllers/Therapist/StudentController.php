<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\Domain\SSA\Services\SSAService;
use App\DTOs\SSAFilterDTO;
use App\Enums\Role;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\ServiceSupportAgreement;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class StudentController extends Controller
{
    public function __construct(
        private readonly SSAService $ssaService,
    ) {}

    public function index(Request $request): View
    {
        $therapist = $request->user();

        // Get students from SSAs assigned to this therapist
        $studentsQuery = User::query()
            ->where('role', Role::STUDENT)
            ->whereHas('studentProfile.ssas', function ($query) use ($therapist) {
                $query->where('assigned_therapist_id', $therapist->id);
            })
            ->with([
                'studentProfile.school',
            ]);

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->query('search');
            $studentsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->filled('status')) {
            $studentsQuery->where('status', $request->query('status'));
        }

        $students = $studentsQuery->distinct()->orderBy('name')->paginate($request->integer('per_page', 15));

        // Load SSAs for each student
        $students->load([
            'studentProfile.ssas' => function ($query) use ($therapist) {
                $query->where('assigned_therapist_id', $therapist->id);
            },
        ]);

        return view('therapist.students.index', [
            'students' => $students,
            'filters' => $request->query(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function show(Request $request, User $student): View
    {
        $therapist = $request->user();

        // Ensure student has SSAs assigned to this therapist
        $hasAssignedSSA = ServiceSupportAgreement::where('student_id', $student->id)
            ->where('assigned_therapist_id', $therapist->id)
            ->exists();

        if (!$hasAssignedSSA) {
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
            $ssasForMetrics = ServiceSupportAgreement::with(['primaryService', 'assignedTherapist'])
                ->where('student_id', $student->id)
                ->where('assigned_therapist_id', $therapist->id)
                ->get();

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
