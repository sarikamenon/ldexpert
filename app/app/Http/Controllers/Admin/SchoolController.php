<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\DataTables\Transformers\SchoolRowTransformer;
use App\Domain\Contract\Services\SchoolContractService;
use App\Domain\Position\Services\PositionCatalogService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\School\Services\SchoolService;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentService;
use App\Domain\Therapist\Services\TherapistService;
use App\Domain\User\Services\UserService;
use App\DTOs\ChangeSchoolStatusDTO;
use App\DTOs\CreateSchoolDTO;
use App\DTOs\SchoolContractFilterDTO;
use App\DTOs\SchoolFilterDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\StudentFilterDTO;
use App\DTOs\TherapistFilterDTO;
use App\DTOs\UpdateSchoolDTO;
use App\Enums\Role;
use App\Enums\SchoolType;
use App\Enums\SSAStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\School\ChangeSchoolStatusRequest;
use App\Http\Requests\Admin\School\ExportSchoolsRequest;
use App\Http\Requests\Admin\School\IndexSchoolRequest;
use App\Http\Requests\Admin\School\SchoolDataRequest;
use App\Http\Requests\Admin\School\SchoolFormRequest;
use App\Http\Requests\Admin\School\StoreSchoolRequest;
use App\Http\Requests\Admin\School\UpdateSchoolRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\School;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SchoolController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'schools.id',
        1 => 'schools.display_name',
        2 => 'users.name',
        3 => 'schools.state',
        4 => 'schools.contact_email',
        5 => 'schools.status',
    ];

    public function __construct(
        private readonly SchoolService $schoolService,
        private readonly UserService $userService,
        private readonly StudentService $studentService,
        private readonly TherapistService $therapistService,
        private readonly SSAService $ssaService,
        private readonly SchoolContractService $schoolContractService,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly PositionCatalogService $positionCatalogService,
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
            'schools' => collect(),
            'metrics' => $this->schoolService->summaryMetrics(),
            'filters' => $filtersPayload,
            'datatableUrl' => route('admin.schools.data'),
        ] + $this->referenceData());
    }

    public function data(SchoolDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', School::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);

        $filterData = [
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
        ];
        $filters = SchoolFilterDTO::fromArray($filterData);

        $result = $this->schoolService->listForDataTables($filters, $params);

        $result['rows']->load('manager');

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            [SchoolRowTransformer::class, 'transform']
        );
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

    public function updateStatus(ChangeSchoolStatusRequest $request, School $school): JsonResponse
    {
        $this->authorize('changeStatus', $school);

        $dto = ChangeSchoolStatusDTO::fromArray($request->validated());
        $this->schoolService->changeStatus($school, $dto);

        $message = $dto->status->value === 'active'
            ? 'School activated successfully.'
            : 'School deactivated successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
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
            $ssasForMetrics = $this->ssaService->getSSAsForSchoolMetrics($school->id);

            $statusCounts = [
                'Active' => $ssasForMetrics->where('status', SSAStatus::ACTIVE)->count(),
                'Pending' => $ssasForMetrics->where('status', SSAStatus::PENDING)->count(),
                'Completed' => $ssasForMetrics->where('status', SSAStatus::COMPLETED)->count(),
                'Deactivated' => $ssasForMetrics->where('status', SSAStatus::DEACTIVATED)->count(),
            ];

            $studentsCount = $this->studentService->countStudentsBySchool($school->id);
            $therapistsCount = $this->therapistService->countTherapistsBySchool($school->id);

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
            // Don't show school filter in school detail view as it's redundant
            $viewData['schools'] = [];
            $viewData['statuses'] = UserStatus::cases();
        } elseif ($activeTab === 'therapists') {
            $filters = TherapistFilterDTO::fromRequest(
                array_merge($request->query(), ['school_id' => $school->id])
            );
            $viewData['therapists'] = $this->therapistService->list($filters);
            $viewData['therapistFilters'] = $request->query();
            $viewData['positions'] = $this->positionCatalogService->listActiveForSelect();
        } elseif ($activeTab === 'ssas') {
            $filters = SSAFilterDTO::fromArray(
                array_merge($request->query(), ['school_id' => $school->id])
            );
            $viewData['ssas'] = $this->ssaService->paginate($filters);
            $viewData['ssaFilters'] = $request->query();
            $viewData['statuses'] = SSAStatus::cases();
            $viewData['students'] = $this->studentService->listActiveStudentsBySchool($school->id);
            $viewData['therapists'] = $this->therapistService->listActiveTherapistsBySchool($school->id);
            $viewData['services'] = $this->serviceCatalogService->listActiveWithFrequencyFlag();
        } elseif ($activeTab === 'contracts') {
            $filters = SchoolContractFilterDTO::fromArray(
                array_merge($request->query(), ['school_id' => $school->id])
            );
            $viewData['contracts'] = $this->schoolContractService->paginate($filters);
            $viewData['contractFilters'] = $request->query();
            $viewData['statuses'] = \App\Enums\ContractStatus::cases();
        } elseif ($activeTab === 'calendar') {
            $viewData['selectedDate'] = $request->query('date')
                ? CarbonImmutable::parse((string) $request->query('date'))
                : CarbonImmutable::today();
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
            if ($handle === false) {
                throw new \RuntimeException('Failed to open CSV stream');
            }
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
}
