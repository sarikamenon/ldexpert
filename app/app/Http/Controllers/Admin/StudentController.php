<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\DataTables\Transformers\ScheduleRowTransformer;
use App\DataTables\Transformers\StudentImportRowTransformer;
use App\DataTables\Transformers\StudentRowTransformer;
use App\Domain\Position\Services\PositionCatalogService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentCommentService;
use App\Domain\Student\Services\StudentDocumentService;
use App\Domain\Student\Services\StudentImportListService;
use App\Domain\Student\Services\StudentImportService;
use App\Domain\Student\Services\StudentService;
use App\Domain\Therapist\Services\ScheduleService;
use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\CreateStudentDTO;
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\StoreStudentImportDTO;
use App\DTOs\StudentFilterDTO;
use App\DTOs\UpdateStudentDTO;
use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SSAStatus;
use App\Enums\StudentImportType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Student\ChangeStudentStatusRequest;
use App\Http\Requests\Admin\Student\ExportStudentsRequest;
use App\Http\Requests\Admin\Student\ImportStudentsRequest;
use App\Http\Requests\Admin\Student\IndexStudentRequest;
use App\Http\Requests\Admin\Student\StoreStudentRequest;
use App\Http\Requests\Admin\Student\StudentDataRequest;
use App\Http\Requests\Admin\Student\StudentImportDataRequest;
use App\Http\Requests\Admin\Student\StudentScheduleDataRequest;
use App\Http\Requests\Admin\Student\UpdateStudentRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\ScheduleEmailLog;
use App\Models\StudentImport;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class StudentController extends Controller
{
    use DataTablesResponse;

    public function __construct(
        private readonly StudentService $studentService,
        private readonly StudentImportService $importService,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly TherapistService $therapistService,
        private readonly SSAService $ssaService,
        private readonly ScheduleService $scheduleService,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly StudentCommentService $commentService,
        private readonly StudentDocumentService $documentService,
        private readonly PositionCatalogService $positionCatalogService,
        private readonly StudentImportListService $importListService,
    ) {}

    /** Column index => allowed order column for student imports list. */
    private const STUDENT_IMPORTS_ORDER_WHITELIST = [
        0 => 'student_imports.id',
        1 => 'student_imports.type',
        2 => 'student_imports.file_name',
        4 => 'student_imports.status',
        6 => 'student_imports.created_at',
    ];

    /** Column index => allowed order column (DB/table qualified). */
    private const STUDENTS_ORDER_WHITELIST = [
        0 => 'users.id',
        1 => 'users.name',
        2 => 'users.username',
        3 => 'users.email',
        4 => 'schools.display_name',
        5 => 'student_profiles.grade_level',
        6 => 'student_profiles.date_of_birth',
        7 => 'users.status',
    ];

    private const STUDENT_SCHEDULES_ORDER_WHITELIST = [
        0 => 'schedule_date',
        1 => 'start_time',
    ];

    public function index(IndexStudentRequest $request): View
    {
        $this->authorize('viewAny', StudentProfile::class);

        $filters = StudentFilterDTO::fromRequest($request->validated());
        $students = $this->studentService->list($filters);
        $metrics = $this->studentService->getMetrics();

        return view('admin.students.index', [
            'students' => collect(), // Server-side DataTables loads via AJAX
            'metrics' => $metrics,
            'filters' => $request->validated(),
            'statuses' => UserStatus::cases(),
            'schools' => $this->schoolRepository->listAllForSelect(),
            'datatableUrl' => route('admin.students.data'),
        ]);
    }

    public function data(StudentDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', StudentProfile::class);

        $params = DataTablesRequest::fromRequest($request, self::STUDENTS_ORDER_WHITELIST);
        $filterData = [
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
            'school_id' => $request->input('filter_school_id'),
            'therapist_id' => $request->input('filter_therapist_id'),
            'per_page' => $params->length,
        ];
        $filters = StudentFilterDTO::fromRequest($filterData);

        $result = $this->studentService->listForDataTables($filters, $params);
        $result['rows']->load(['studentProfile.school']);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (User $student): array => StudentRowTransformer::transform($student),
        );
    }

    public function scheduleData(StudentScheduleDataRequest $request, User $student): JsonResponse
    {
        $this->authorize('view', StudentProfile::class);

        $requestStudentId = (int) $request->input('filter_student_id');
        if ($requestStudentId !== $student->id) {
            abort(403, 'Student mismatch.');
        }

        $filters = ScheduleFilterDTO::fromRequest([
            'student_id' => $student->id,
            'date' => $request->input('filter_date'),
            'status' => $request->input('filter_status'),
            'billing_status' => $request->input('filter_billing_status'),
            'ssa_id' => $request->input('filter_ssa_id'),
            'therapist_id' => $request->input('filter_therapist_id'),
        ]);
        $params = DataTablesRequest::fromRequest($request, self::STUDENT_SCHEDULES_ORDER_WHITELIST);
        $result = $this->scheduleService->listForDataTablesForStudent($student, $filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn ($schedule) => ScheduleRowTransformer::transform($schedule),
        );
    }

    public function create(Request $request): View
    {
        $this->authorize('create', StudentProfile::class);

        $data = $this->referenceData();

        $schoolId = $request->query('school_id');
        if ($schoolId) {
            $school = $this->schoolRepository->find((int) $schoolId);
            if ($school) {
                $data['preselectedSchoolId'] = $school->id;
                $data['preselectedTimezone'] = $school->timezone;
            }
        }

        return view('admin.students.create', $data);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $this->authorize('create', StudentProfile::class);

        $validated = $request->validated();
        $validated['password'] = Str::password(12);

        $dto = CreateStudentDTO::fromArray($validated);
        $profile = $this->studentService->create($dto);

        if ($request->input('redirect_to_ssa') === '1') {
            return redirect()
                ->route('admin.ssas.create', ['student_id' => $profile->user_id])
                ->with('status', 'Student created. Now create an SSA for them.');
        }

        return redirect()
            ->route('admin.students.index')
            ->with('status', 'Student added successfully.');
    }

    public function edit(User $student): View
    {
        $this->authorize('update', StudentProfile::class);

        return view('admin.students.edit', [
            'student' => $student->loadMissing('studentProfile.school'),
        ] + $this->referenceData());
    }

    public function show(Request $request, User $student): View
    {
        $this->authorize('view', StudentProfile::class);

        $student->load(['studentProfile.school', 'studentProfile.parent']);
        $activeTab = $request->query('tab', 'dashboard');

        $viewData = [
            'student' => $student,
            'activeTab' => $activeTab,
        ];

        // Load dashboard data (always needed for metrics)
        if ($activeTab === 'dashboard' || $activeTab === 'overview') {
            $ssasForMetrics = $this->ssaService->getSSAsForStudentMetrics($student->id);

            $totalTho = (int) $ssasForMetrics->sum('tho_minutes');
            $served = (int) $ssasForMetrics->sum('served_minutes');

            $totalThoHours = round($totalTho / 60, 2);
            $servedHours = round($served / 60, 2);

            $viewData['chartData'] = [
                'served' => $servedHours,
                'remaining' => round(max(0, $totalThoHours - $servedHours), 2),
                'progress' => $totalTho > 0 ? round(($served / $totalTho) * 100, 1) : 0,
            ];

            $viewData['metrics'] = [
                'total_ssas' => $ssasForMetrics->count(),
                'active_ssas' => $ssasForMetrics->where('status', SSAStatus::ACTIVE)->count(),
                'completed_ssas' => $ssasForMetrics->where('status', SSAStatus::COMPLETED)->count(),
                'pending_ssas' => $ssasForMetrics->where('status', SSAStatus::PENDING)->count(),
            ];
        }

        // Load tab-specific data only when needed
        if ($activeTab === 'ssas') {
            $viewData['ssas'] = collect();
            $viewData['ssaFilters'] = $request->query();
            $viewData['statuses'] = SSAStatus::cases();
            // Don't show student filter in student detail view as it's redundant
            $viewData['students'] = [];
            $viewData['therapists'] = $this->therapistService->listActiveTherapists();
            $viewData['services'] = $this->serviceCatalogService->listActiveWithFrequencyFlag();
            $viewData['datatableUrl'] = route('admin.ssas.data');
            $viewData['studentId'] = $student->id;
        } elseif ($activeTab === 'therapists') {
            $viewData['therapists'] = collect();
            $viewData['therapistFilters'] = $request->query();
            $viewData['positions'] = $this->positionCatalogService->listActiveForSelect();
            $viewData['datatableUrl'] = route('admin.therapists.data');
            $viewData['studentId'] = $student->id;
        } elseif ($activeTab === 'schedule') {
            $viewData['schedules'] = collect();
            $viewData['scheduleFilters'] = $request->query();
            $viewData['scheduleStatuses'] = ScheduleStatus::cases();
            $viewData['billingStatuses'] = BillingStatus::cases();
            $viewData['ssas'] = $this->ssaService->getSSAsForStudentSchedule($student->id);
            $viewData['therapists'] = $this->therapistService->listTherapistsByStudent($student->id);
            $viewData['scheduleDatatableUrl'] = route('admin.students.schedules.data', $student);
            $viewData['scheduleStudentId'] = $student->id;
        } elseif ($activeTab === 'session_logs') {
            $viewData['sessionLogStatuses'] = \App\Enums\SessionLogStatus::cases();
            $viewData['sessionLogFilters'] = $request->query();
            $viewData['datatableUrl'] = route('admin.session-logs.data');
            $viewData['studentId'] = $student->id;
        } elseif ($activeTab === 'comments') {
            $viewData['comments'] = $this->commentService->listByStudent($student->id);
        } elseif ($activeTab === 'documents') {
            $viewData['documents'] = $this->documentService->listByStudent($student->id);
        } elseif ($activeTab === 'email_history') {
            $studentFallbackTz = $student->studentProfile->timezone
                ?? $student->timezone
                ?? 'UTC';

            $viewData['emailLogs'] = ScheduleEmailLog::query()
                ->whereHas('schedule', function (\Illuminate\Database\Eloquent\Builder $q) use ($student): void {
                    // @phpstan-ignore argument.type
                    $q->where('student_id', $student->id);
                })
                ->with(['sentBy', 'schedule', 'schedule.therapist', 'schedule.therapist.therapistProfile', 'schedule.service'])
                ->orderByDesc('sent_at')
                ->get()
                ->each(function (ScheduleEmailLog $log) use ($studentFallbackTz): void {
                    $therapist = $log->schedule?->therapist;
                    $profileTz = $therapist?->therapistProfile?->timezone;
                    $rowTz = $profileTz ?? ($therapist !== null ? $therapist->timezone : $studentFallbackTz);

                    $log->sent_at_formatted = $log->sent_at->copy()->setTimezone($rowTz)->format('M d, Y h:i A');
                    $log->schedule_local_date = $log->schedule?->localStart($rowTz)->format('M d, Y');
                });
        }

        return view('admin.students.show', $viewData);
    }

    public function update(UpdateStudentRequest $request, User $student): RedirectResponse
    {
        $this->authorize('update', StudentProfile::class);

        $dto = UpdateStudentDTO::fromArray($request->validated());
        $this->studentService->update($student, $dto);

        return redirect()
            ->route('admin.students.index')
            ->with('status', 'Student information updated successfully.');
    }

    public function updateStatus(ChangeStudentStatusRequest $request, User $student): JsonResponse
    {
        $this->authorize('changeStatus', StudentProfile::class);

        $dto = ChangeStudentStatusDTO::fromArray($request->validated());
        $this->studentService->changeStatus($student, $dto);

        $message = $dto->status === UserStatus::ACTIVE->value
            ? 'Student activated successfully.'
            : 'Student deactivated successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function export(ExportStudentsRequest $request): StreamedResponse
    {
        $this->authorize('export', StudentProfile::class);

        $filters = StudentFilterDTO::fromRequest($request->validated());
        $students = $this->studentService->export($filters);
        $filename = sprintf('students-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($students): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Failed to open CSV stream');
            }
            fputcsv($handle, [
                'ID',
                'Name',
                'Username',
                'Email',
                'School/Family',
                'Grade Level',
                'Date of Birth',
                'Status',
            ]);

            foreach ($students as $student) {
                $profile = $student->studentProfile;
                fputcsv($handle, [
                    $student->id,
                    $student->name,
                    $student->username,
                    $student->email,
                    $profile?->school->display_name ?? '—',
                    $profile->grade_level ?? '—',
                    optional($profile?->date_of_birth)->format('Y-m-d') ?? '—',
                    $student->status->value,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function showImportForm(): View
    {
        $this->authorize('create', StudentProfile::class);

        $templates = [];
        foreach (StudentImportType::cases() as $type) {
            $template = $this->importService->getTemplate($type);
            if (! empty($template)) {
                $templates[$type->value] = [
                    'required_columns' => $template['required_columns'] ?? [],
                    'optional_columns' => $template['optional_columns'] ?? [],
                ];
            }
        }

        $novaTemplate = $this->importService->getTemplate(StudentImportType::NOVA);

        return view('admin.students.import', [
            'requiredColumns' => $novaTemplate['required_columns'] ?? [],
            'optionalColumns' => $novaTemplate['optional_columns'] ?? [],
            'importTypes' => StudentImportType::cases(),
            'templates' => $templates,
        ]);
    }

    public function import(ImportStudentsRequest $request): JsonResponse
    {
        $this->authorize('create', StudentProfile::class);

        $validated = $request->validated();
        $type = StudentImportType::from($validated['type'] ?? StudentImportType::NOVA->value);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $dto = StoreStudentImportDTO::fromArray([
            'file' => $request->file('file'),
            'user_id' => $user->id,
            'type' => $type->value,
        ]);

        $import = $this->importService->storeImportRequest($dto);

        return response()->json([
            'success' => true,
            'message' => 'Import queued successfully. You will receive an email notification when it completes.',
            'data' => [
                'import_id' => $import->id,
                'status' => $import->status->value,
            ],
        ]);
    }

    public function showImportStatus(Request $request, StudentImport $import): View|JsonResponse
    {
        $this->authorize('view', StudentProfile::class);

        $import->load(['rows.student', 'user']);

        $stats = [
            'total' => $import->total_rows,
            'processed' => $import->processed_rows,
            'success' => $import->rows()->where('status', 'done')->count(),
            'duplicates' => $import->rows()->where('status', 'duplicate')->count(),
            'errors' => $import->rows()->where('status', 'validation_error')->count(),
            'pending' => $import->rows()->where('status', 'pending')->count(),
        ];

        // Return JSON for AJAX polling
        if ($request->wantsJson()) {
            return response()->json([
                'status' => $import->status->value,
                'stats' => $stats,
            ]);
        }

        return view('admin.students.import-status', [
            'import' => $import,
            'stats' => $stats,
        ]);
    }

    public function importHistory(Request $request): View
    {
        $this->authorize('viewAny', StudentProfile::class);

        return view('admin.students.import-history', [
            'imports' => collect(),
            'datatableUrl' => route('admin.students.imports.data'),
        ]);
    }

    public function importHistoryData(StudentImportDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', StudentProfile::class);

        $params = DataTablesRequest::fromRequest($request, self::STUDENT_IMPORTS_ORDER_WHITELIST);
        $result = $this->importListService->listForDataTables($params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (StudentImport $import): array => StudentImportRowTransformer::transform($import),
        );
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $this->authorize('create', StudentProfile::class);

        $type = StudentImportType::from($request->query('type', StudentImportType::NOVA->value));
        $template = $this->importService->getTemplate($type);

        $requiredColumns = $template['required_columns'] ?? [];
        $optionalColumns = $template['optional_columns'] ?? [];
        $allColumns = array_merge($requiredColumns, $optionalColumns);

        $filename = sprintf('student-import-template-%s-%s.csv', strtolower($type->value), now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($allColumns, $requiredColumns): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Failed to open CSV stream');
            }

            // Write header row
            fputcsv($handle, $allColumns);

            // Write example row with placeholders
            $exampleRow = [];
            foreach ($allColumns as $column) {
                if (in_array($column, $requiredColumns, true)) {
                    $label = str_replace('_', ' ', ucwords($column, '_'));
                    $exampleRow[] = sprintf('[%s]', $label);
                } else {
                    $exampleRow[] = '';
                }
            }
            fputcsv($handle, $exampleRow);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadImported(Request $request, StudentImport $import): StreamedResponse|RedirectResponse
    {
        $this->authorize('viewAny', StudentProfile::class);

        try {
            return \Illuminate\Support\Facades\Storage::download(
                $import->file_path,
                $import->file_name,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Student import file download failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'The original import file could not be found.');
        }
    }

    /** @return array<string, mixed> */
    private function referenceData(): array
    {
        return [
            'states' => UsStates::getStates(),
            'timezones' => UsTimezones::getTimezones(),
            'schools' => $this->schoolRepository->listAllForSelect(),
            'statuses' => UserStatus::cases(),
        ];
    }
}
