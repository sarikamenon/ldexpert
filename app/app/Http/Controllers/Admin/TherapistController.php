<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Constants\UsStates;
use App\Constants\UsTimezones;
use App\DataTables\Transformers\TherapistRowTransformer;
use App\Domain\Contract\Services\TherapistContractService;
use App\Domain\Position\Services\PositionCatalogService;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\Student\Services\StudentService;
use App\Domain\Therapist\Services\TherapistService;
use App\Domain\User\Services\UserService;
use App\DTOs\ChangeTherapistStatusDTO;
use App\DTOs\CreateTherapistDTO;
use App\DTOs\SessionLogIndexDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\TherapistContractFilterDTO;
use App\DTOs\TherapistFilterDTO;
use App\DTOs\UpdateTherapistDTO;
use App\Enums\EmployeeType;
use App\Enums\Role;
use App\Enums\SSAStatus;
use App\Enums\TherapistTitle;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Therapist\ChangeTherapistStatusRequest;
use App\Http\Requests\Admin\Therapist\ExportTherapistsRequest;
use App\Http\Requests\Admin\Therapist\IndexTherapistRequest;
use App\Http\Requests\Admin\Therapist\StoreTherapistRequest;
use App\Http\Requests\Admin\Therapist\TherapistDataRequest;
use App\Http\Requests\Admin\Therapist\UpdateTherapistRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\TherapistProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TherapistController extends Controller
{
    use DataTablesResponse;

    /** @var array<int, string> Column index => allowed order column */
    private const ORDER_WHITELIST = [
        0 => 'users.id',
        1 => 'users.name',
        2 => 'users.email',
        3 => 'managers.name',
        4 => 'positions.name',
        5 => 'therapist_profiles.max_weekly_hours',
        6 => 'users.status',
    ];

    public function __construct(
        private readonly TherapistService $therapistService,
        private readonly UserService $userService,
        private readonly StudentService $studentService,
        private readonly SSAService $ssaService,
        private readonly TherapistContractService $therapistContractService,
        private readonly SchoolRepositoryInterface $schoolRepository,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly SessionLogIndexService $sessionLogIndexService,
        private readonly PositionCatalogService $positionCatalogService,
    ) {}

    public function index(IndexTherapistRequest $request): View
    {
        $this->authorize('viewAny', TherapistProfile::class);

        $filters = TherapistFilterDTO::fromRequest($request->validated());
        $metrics = $this->therapistService->getMetrics();

        return view('admin.therapists.index', [
            'therapists' => collect(),
            'metrics' => $metrics,
            'filters' => $request->validated(),
            'positions' => $this->positionCatalogService->listActiveForSelect(),
            'datatableUrl' => route('admin.therapists.data'),
        ]);
    }

    public function data(TherapistDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', TherapistProfile::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $filterData = [
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
            'position_id' => $request->input('filter_position_id'),
        ];
        $filters = TherapistFilterDTO::fromRequest($filterData);

        $result = $this->therapistService->listForDataTables($filters, $params);
        $result['rows']->load(['therapistProfile.manager', 'therapistProfile.position']);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            [TherapistRowTransformer::class, 'transform']
        );
    }

    public function create(): View
    {
        $this->authorize('create', TherapistProfile::class);

        return view('admin.therapists.create', $this->referenceData());
    }

    public function store(StoreTherapistRequest $request): RedirectResponse
    {
        $this->authorize('create', TherapistProfile::class);

        $validated = $request->validated();
        $validated['password'] = Str::password(12);

        $dto = CreateTherapistDTO::fromArray($validated);
        $this->therapistService->create($dto);

        return redirect()
            ->route('admin.therapists.index')
            ->with('status', 'Therapist added successfully.');
    }

    public function edit(User $therapist): View
    {
        $this->authorize('update', TherapistProfile::class);

        return view('admin.therapists.edit', [
            'therapist' => $therapist->load('therapistProfile.position'),
        ] + $this->referenceData());
    }

    public function show(Request $request, User $therapist): View
    {
        $therapistId = $request->route('therapist');
        if (is_numeric($therapistId)) {
            $therapist = User::findOrFail((int) $therapistId);
        }

        $this->authorize('view', TherapistProfile::class);

        $therapist->load('therapistProfile.position');
        $activeTab = $request->query('tab', 'dashboard');

        $viewData = [
            'therapist' => $therapist,
            'activeTab' => $activeTab,
        ];

        // Load dashboard data (always needed for metrics)
        if ($activeTab === 'dashboard' || $activeTab === 'overview') {
            $ssasForMetrics = $this->ssaService->getSSAsForTherapistMetrics($therapist->id);

            $totalTho = (int) $ssasForMetrics->sum('tho_minutes');
            $served = (int) $ssasForMetrics->sum('served_minutes');

            $viewData['chartData'] = [
                'served' => $served,
                'remaining' => max(0, $totalTho - $served),
                'progress' => $totalTho > 0 ? round(($served / $totalTho) * 100, 1) : 0,
            ];

            $studentsCount = $this->studentService->countStudentsByTherapist($therapist->id);

            $viewData['metrics'] = [
                'total_students' => $studentsCount,
                'active_ssas' => $ssasForMetrics->where('status', SSAStatus::ACTIVE)->count(),
                'completed_ssas' => $ssasForMetrics->where('status', SSAStatus::COMPLETED)->count(),
                'pending_ssas' => $ssasForMetrics->where('status', SSAStatus::PENDING)->count(),
            ];
        }

        // Load tab-specific data only when needed
        if ($activeTab === 'students') {
            $viewData['students'] = $this->studentService->listStudentsByTherapist(
                $therapist->id,
                $request->query('search'),
                $request->query('status'),
                $request->integer('per_page', 15)
            );
            $viewData['studentFilters'] = $request->query();
            $viewData['schools'] = $this->schoolRepository->listAllForSelect();
            $viewData['statuses'] = UserStatus::cases();
        } elseif ($activeTab === 'ssas') {
            $filters = SSAFilterDTO::fromArray(
                array_merge($request->query(), ['therapist_id' => $therapist->id])
            );
            $viewData['ssas'] = $this->ssaService->paginate($filters);
            $viewData['ssaFilters'] = $request->query();
            $viewData['statuses'] = SSAStatus::cases();
            $viewData['students'] = $this->studentService->listActiveStudentsByTherapist($therapist->id);
            // Don't show therapist filter in therapist detail view as it's redundant
            $viewData['therapists'] = [];
            $viewData['services'] = $this->serviceCatalogService->listActiveWithFrequencyFlag();
        } elseif ($activeTab === 'contracts') {
            $filters = TherapistContractFilterDTO::fromArray(
                array_merge($request->query(), ['therapist_id' => $therapist->id])
            );
            $viewData['contracts'] = $this->therapistContractService->paginate($filters);
            $viewData['contractFilters'] = $request->query();
            $viewData['statuses'] = \App\Enums\ContractStatus::cases();
        } elseif ($activeTab === 'session_logs') {
            $dto = SessionLogIndexDTO::fromArray(
                array_merge($request->query(), ['therapist_id' => $therapist->id])
            );
            $sessionLogData = $this->sessionLogIndexService->getAdminIndex($dto);

            $viewData['sessionLogs'] = $sessionLogData['sessionLogs'];
            $viewData['sessionLogColumns'] = $sessionLogData['columns'];
            $viewData['sessionLogRows'] = $sessionLogData['rows'];
            $viewData['sessionLogStatuses'] = $sessionLogData['statuses'];
            $viewData['sessionLogFilters'] = $request->query();
        }

        return view('admin.therapists.show', $viewData);
    }

    public function update(UpdateTherapistRequest $request, User $therapist): RedirectResponse
    {
        $this->authorize('update', TherapistProfile::class);

        $dto = UpdateTherapistDTO::fromArray($request->validated());
        $this->therapistService->update($therapist, $dto);

        return redirect()
            ->route('admin.therapists.index')
            ->with('status', 'Therapist information updated successfully.');
    }

    public function updateStatus(ChangeTherapistStatusRequest $request, User $therapist): JsonResponse
    {
        $this->authorize('changeStatus', TherapistProfile::class);

        $dto = ChangeTherapistStatusDTO::fromArray($request->validated());
        $this->therapistService->changeStatus($therapist, $dto);

        $message = $dto->status === 'active'
            ? 'Therapist activated successfully.'
            : 'Therapist deactivated successfully.';

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    public function export(ExportTherapistsRequest $request): StreamedResponse
    {
        $this->authorize('export', TherapistProfile::class);

        $filters = TherapistFilterDTO::fromRequest($request->validated());
        $therapists = $this->therapistService->export($filters);
        $filename = sprintf('therapists-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($therapists): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Name',
                'Email',
                'Manager',
                'Phone',
                'Position',
                'Type',
                'Status',
            ]);

            foreach ($therapists as $therapist) {
                $profile = $therapist->therapistProfile;
                fputcsv($handle, [
                    $therapist->id,
                    $therapist->name,
                    $therapist->email,
                    $profile?->manager->name ?? '—',
                    $profile->phone ?? '—',
                    $profile?->position->name ?? '—',
                    $profile?->employee_type->value ?? $profile->employee_type ?? '—',
                    $therapist->status->value ?? $therapist->status ?? 'inactive',
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
            'managers' => $this->userService->listByRole(Role::ADMIN),
            'titles' => TherapistTitle::cases(),
            'positions' => $this->positionCatalogService->listActiveForSelect(),
            'employeeTypes' => EmployeeType::cases(),
        ];
    }
}
