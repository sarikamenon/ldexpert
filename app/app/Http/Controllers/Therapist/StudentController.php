<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist;

use App\DataTables\Transformers\TherapistStudentRowTransformer;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentCommentService;
use App\Domain\Student\Services\StudentDocumentService;
use App\Domain\Student\Services\StudentService;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\TherapistStudentDataRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentController extends Controller
{
    use DataTablesResponse;

    /** Column index => allowed order column (DB/table qualified). */
    private const STUDENTS_ORDER_WHITELIST = [
        0 => 'users.id',
        1 => 'users.name',
        2 => 'users.email',
        3 => 'schools.display_name',
        4 => 'student_profiles.grade_level',
        5 => 'student_profiles.date_of_birth',
        6 => 'users.status',
    ];

    public function __construct(
        private readonly SSAService $ssaService,
        private readonly StudentService $studentService,
        private readonly StudentCommentService $commentService,
        private readonly StudentDocumentService $documentService,
    ) {}

    public function index(Request $request): View
    {
        return view('therapist.students.index', [
            'students' => collect(),
            'filters' => $request->query(),
            'statuses' => UserStatus::cases(),
            'datatableUrl' => route('therapist.students.data'),
        ]);
    }

    public function data(TherapistStudentDataRequest $request): JsonResponse
    {
        /** @var \App\Models\User $therapist */
        $therapist = $request->user();
        $params = DataTablesRequest::fromRequest($request, self::STUDENTS_ORDER_WHITELIST);
        $filters = [
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
        ];
        $result = $this->studentService->listForDataTablesByTherapist($therapist->id, $filters, $params);
        $result['rows']->load(['studentProfile.school']);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (User $student): array => TherapistStudentRowTransformer::transform($student),
        );
    }

    public function show(Request $request, \App\Models\User $student): View
    {
        /** @var \App\Models\User $therapist */
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

            $totalThoHours = round((float) $ssasForMetrics->sum('tho_minutes') / 60, 2);
            $servedHours = round((float) $ssasForMetrics->sum('served_minutes') / 60, 2);

            $viewData['chartData'] = [
                'served' => $servedHours,
                'remaining' => round(max(0, $totalThoHours - $servedHours), 2),
                'progress' => $totalThoHours > 0 ? round(($servedHours / $totalThoHours) * 100, 1) : 0,
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
            $viewData['ssas'] = collect();
            $viewData['ssaFilters'] = $request->query();
            $viewData['statuses'] = SSAStatus::cases();
            $viewData['datatableUrl'] = route('therapist.ssas.data');
            $viewData['studentId'] = $student->id;
        } elseif ($activeTab === 'session_logs') {
            $viewData['sessionLogStatuses'] = \App\Enums\SessionLogStatus::cases();
            $viewData['sessionLogFilters'] = $request->query();
            $viewData['datatableUrl'] = route('therapist.session-logs.data');
            $viewData['studentId'] = $student->id;
        } elseif ($activeTab === 'comments') {
            $viewData['comments'] = $this->commentService->listByStudent($student->id);
        } elseif ($activeTab === 'documents') {
            $viewData['documents'] = $this->documentService->listByStudent($student->id);
        }

        return view('therapist.students.show', $viewData);
    }
}
