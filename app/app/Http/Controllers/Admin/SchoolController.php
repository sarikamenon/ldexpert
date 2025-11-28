<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\Domain\School\Services\SchoolService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Student\Services\StudentService;
use App\Domain\Therapist\Services\TherapistService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\User\Services\UserService;
use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\StudentFilterDTO;
use App\DTOs\TherapistFilterDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Enums\Role;
use App\Enums\SchoolType;
use App\Enums\TherapistPosition;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\School\ChangeSchoolStatusRequest;
use App\Http\Requests\Admin\School\ExportSchoolsRequest;
use App\Http\Requests\Admin\School\IndexSchoolRequest;
use App\Http\Requests\Admin\School\SchoolFormRequest;
use App\Http\Requests\Admin\School\StoreSchoolRequest;
use App\Http\Requests\Admin\School\UpdateSchoolRequest;
use App\Models\School;
use App\Models\User;
use App\Models\ServiceSupportAgreement;
use App\Models\Service;
use App\Enums\SSAStatus;
use App\Enums\ServiceStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SchoolController extends Controller
{
    public function __construct(
        private readonly SchoolService $schoolService,
        private readonly UserService $userService,
        private readonly StudentService $studentService,
        private readonly TherapistService $therapistService,
        private readonly SSAService $ssaService,
        private readonly SchoolRepositoryInterface $schoolRepository,
    ) {}

    public function index(IndexSchoolRequest $request): View
    {
        $this->authorize('viewAny', School::class);

        $filtersPayload = array_merge(
            $request->validated(),
            ['show_deactivated' => $request->boolean('show_deactivated')]
        );
        $filters = SchoolFilterDTO::fromArray($filtersPayload);
        $perPage = $request->integer('per_page', 25);

        return view('admin.schools.index', [
            'schools' => $this->schoolService->listSchools($filters, $perPage),
            'metrics' => $this->schoolService->summaryMetrics(),
            'filters' => $filtersPayload,
        ] + $this->referenceData());
    }

    public function create(): View
    {
        $this->authorize('create', School::class);

        return view('admin.schools.create', $this->referenceData());
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        $this->authorize('create', School::class);

        $dto = CreateSchoolDTO::fromArray($this->formPayload($request));
        $this->schoolService->createSchool($dto);

        return redirect()
            ->route('admin.schools.index')
            ->with('status', 'School added successfully.');
    }

    public function edit(School $school): View
    {
        $this->authorize('update', $school);

        return view('admin.schools.edit', [
            'school' => $school,
        ] + $this->referenceData());
    }

    public function update(UpdateSchoolRequest $request, School $school): RedirectResponse
    {
        $this->authorize('update', $school);

        $dto = UpdateSchoolDTO::fromArray($this->formPayload($request));
        $this->schoolService->updateSchool($school, $dto);

        return redirect()
            ->route('admin.schools.index')
            ->with('status', 'School information updated successfully.');
    }

    public function updateStatus(ChangeSchoolStatusRequest $request, School $school): RedirectResponse
    {
        $this->authorize('changeStatus', $school);

        $dto = ChangeSchoolStatusDTO::fromArray($request->validated());
        $this->schoolService->changeStatus($school, $dto);

        $message = $dto->status->value === 'active'
            ? 'School activated successfully.'
            : 'School deactivated successfully.';

        return redirect()
            ->route('admin.schools.index')
            ->with('status', $message);
    }

    public function show(Request $request, School $school): View
    {
        $this->authorize('view', $school);

        $school->load('manager');
        $activeTab = $request->query('tab', 'dashboard');

        $viewData = [
            'school' => $school,
            'activeTab' => $activeTab,
        ];

        // Load dashboard data (always needed for metrics)
        if ($activeTab === 'dashboard' || $activeTab === 'overview') {
            $ssasForMetrics = ServiceSupportAgreement::with(['student', 'primaryService', 'assignedTherapist'])
                ->whereHas('student.studentProfile', fn($q) => $q->where('school_id', $school->id))
                ->get();

            $statusCounts = [
                'Active' => $ssasForMetrics->where('status', SSAStatus::ACTIVE)->count(),
                'Pending' => $ssasForMetrics->where('status', SSAStatus::PENDING)->count(),
                'Completed' => $ssasForMetrics->where('status', SSAStatus::COMPLETED)->count(),
                'Deactivated' => $ssasForMetrics->where('status', SSAStatus::DEACTIVATED)->count(),
            ];

            $studentsCount = User::query()
                ->where('role', Role::STUDENT)
                ->whereHas('studentProfile', fn($q) => $q->where('school_id', $school->id))
                ->count();

            $therapistsCount = $this->therapistsForSchoolQuery($school->id)
                ->distinct('users.id')
                ->count('users.id');

            $viewData['statusCounts'] = $statusCounts;
            $viewData['metrics'] = [
                'total_students' => $studentsCount,
                'total_therapists' => $therapistsCount,
                'total_ssas' => $ssasForMetrics->count(),
            ];
            $viewData['chartData'] = [
                'labels' => array_keys($statusCounts),
                'data' => array_values($statusCounts),
            ];
        }

        // Load tab-specific data only when needed
        if ($activeTab === 'students') {
            $filters = StudentFilterDTO::fromRequest(
                array_merge($request->query(), ['school_id' => $school->id])
            );
            $viewData['students'] = $this->studentService->list($filters);
            $viewData['studentFilters'] = $request->query();
            $viewData['schools'] = $this->schoolRepository->listAllForSelect();
            $viewData['statuses'] = UserStatus::cases();
        } elseif ($activeTab === 'therapists') {
            $filters = TherapistFilterDTO::fromRequest(
                array_merge($request->query(), ['school_id' => $school->id])
            );
            $viewData['therapists'] = $this->therapistService->list($filters);
            $viewData['therapistFilters'] = $request->query();
            $viewData['positions'] = TherapistPosition::cases();
        } elseif ($activeTab === 'ssas') {
            $filters = SSAFilterDTO::fromArray(
                array_merge($request->query(), ['school_id' => $school->id])
            );
            $viewData['ssas'] = $this->ssaService->paginate($filters);
            $viewData['ssaFilters'] = $request->query();
            $viewData['statuses'] = SSAStatus::cases();
            $viewData['students'] = User::query()
                ->where('role', Role::STUDENT)
                ->where('status', UserStatus::ACTIVE)
                ->whereHas('studentProfile', fn($q) => $q->where('school_id', $school->id))
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
            $viewData['therapists'] = $this->therapistsForSchoolQuery($school->id)
                ->where('status', UserStatus::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
            $viewData['services'] = Service::query()
                ->where('status', ServiceStatus::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name', 'is_frequency_service']);
        }

        return view('admin.schools.show', $viewData);
    }

    public function export(ExportSchoolsRequest $request): StreamedResponse
    {
        $this->authorize('export', School::class);

        $filters = SchoolFilterDTO::fromArray(array_merge(
            $request->validated(),
            ['show_deactivated' => $request->boolean('show_deactivated')]
        ));
        $rows = $this->schoolService->exportSchools($filters);
        $filename = sprintf('schools-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Full Name',
                'Display Name',
                'Manager',
                'State',
                'Email',
                'Timezone',
                'Status',
            ]);

            /** @var \App\Models\School $school */
            foreach ($rows as $school) {
                fputcsv($handle, [
                    $school->id,
                    $school->full_name,
                    $school->display_name,
                    $school->manager?->name,
                    $school->state,
                    $school->contact_email,
                    $school->timezone,
                    $school->status?->value ?? $school->status,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function formPayload(SchoolFormRequest $request): array
    {
        $validated = $request->validated();
        $validated['is_private_student'] = $request->boolean('is_private_student');
        $validated['non_billable_scheduling'] = $request->boolean('non_billable_scheduling');

        return $validated;
    }

    private function referenceData(): array
    {
        return [
            'states' => UsStates::getStates(),
            'timezones' => UsTimezones::getTimezones(),
            'managers' => $this->userService->listByRole(Role::ADMIN),
            'schoolTypes' => SchoolType::values(),
        ];
    }

    private function therapistsForSchoolQuery(int $schoolId): Builder
    {
        return User::query()
            ->where('role', Role::THERAPIST)
            ->where(function (Builder $query) use ($schoolId) {
                $query->whereHas('students.studentProfile', function (Builder $studentQuery) use ($schoolId) {
                    $studentQuery->where('school_id', $schoolId);
                })->orWhereHas('assignedSSAs.student.studentProfile', function (Builder $ssaQuery) use ($schoolId) {
                    $ssaQuery->where('school_id', $schoolId);
                });
            });
    }
}
