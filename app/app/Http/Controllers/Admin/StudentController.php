<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentService;
use App\Domain\Therapist\Services\ScheduleService;
use App\Domain\Therapist\Services\TherapistService;
use App\DTOs\ChangeStudentStatusDTO;
use App\DTOs\CreateStudentDTO;
use App\DTOs\ScheduleFilterDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\StudentFilterDTO;
use App\DTOs\UpdateStudentDTO;
use App\Enums\BillingStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SSAStatus;
use App\Enums\TherapistPosition;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Student\ChangeStudentStatusRequest;
use App\Http\Requests\Admin\Student\ExportStudentsRequest;
use App\Http\Requests\Admin\Student\IndexStudentRequest;
use App\Http\Requests\Admin\Student\StoreStudentRequest;
use App\Http\Requests\Admin\Student\UpdateStudentRequest;
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
    public function __construct(
        private readonly StudentService $studentService,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly TherapistService $therapistService,
        private readonly SSAService $ssaService,
        private readonly ScheduleService $scheduleService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(IndexStudentRequest $request): View
    {
        $this->authorize('viewAny', StudentProfile::class);

        $filters = StudentFilterDTO::fromRequest($request->validated());
        $students = $this->studentService->list($filters);
        $metrics = $this->studentService->getMetrics();

        return view('admin.students.index', [
            'students' => $students,
            'metrics' => $metrics,
            'filters' => $request->validated(),
            'statuses' => UserStatus::cases(),
            'schools' => $this->schoolRepository->listAllForSelect(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', StudentProfile::class);

        return view('admin.students.create', $this->referenceData());
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $this->authorize('create', StudentProfile::class);

        $validated = $request->validated();
        $validated['password'] = Str::password(12);

        $dto = CreateStudentDTO::fromArray($validated);
        $this->studentService->create($dto);

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

        // Load tab-specific data only when needed
        if ($activeTab === 'ssas') {
            $filters = SSAFilterDTO::fromArray(
                array_merge($request->query(), ['student_id' => $student->id])
            );
            $viewData['ssas'] = $this->ssaService->paginate($filters);
            $viewData['ssaFilters'] = $request->query();
            $viewData['statuses'] = SSAStatus::cases();
            // Don't show student filter in student detail view as it's redundant
            $viewData['students'] = [];
            $viewData['therapists'] = $this->therapistService->listActiveTherapists();
            $viewData['services'] = $this->serviceCatalogService->listActiveWithFrequencyFlag();
        } elseif ($activeTab === 'therapists') {
            $viewData['therapists'] = $this->therapistService->paginateTherapistsByStudent(
                $student->id,
                $request->query('search'),
                $request->query('status'),
                $request->query('position'),
                $request->integer('per_page', 15)
            );
            $viewData['therapistFilters'] = $request->query();
            $viewData['positions'] = TherapistPosition::cases();
        } elseif ($activeTab === 'schedule') {
            $filters = ScheduleFilterDTO::fromRequest(
                array_merge($request->query(), ['student_id' => $student->id])
            );
            $perPage = $request->integer('per_page', 15);

            $viewData['schedules'] = $this->scheduleService->paginateForStudent($student, $filters, $perPage);
            $viewData['scheduleFilters'] = $request->query();
            $viewData['scheduleStatuses'] = ScheduleStatus::cases();
            $viewData['billingStatuses'] = BillingStatus::cases();
            $viewData['ssas'] = $this->ssaService->getSSAsForStudentSchedule($student->id);
            $viewData['therapists'] = $this->therapistService->listTherapistsByStudent($student->id);
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
            fputcsv($handle, [
                'ID',
                'Name',
                'Email',
                'School',
                'Grade Level',
                'Date of Birth',
                'Status',
            ]);

            foreach ($students as $student) {
                $profile = $student->studentProfile;
                fputcsv($handle, [
                    $student->id,
                    $student->name,
                    $student->email,
                    $profile?->school?->display_name ?? '—',
                    $profile?->grade_level ?? '—',
                    optional($profile?->date_of_birth)->format('Y-m-d') ?? '—',
                    $student->status?->value ?? $student->status ?? 'inactive',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

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
