<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\SSAImportRowTransformer;
use App\DataTables\Transformers\SSARowTransformer;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SessionLog\Services\SessionLogIndexService;
use App\Domain\SSA\Services\SSAImportListService;
use App\Domain\SSA\Services\SSAImportService;
use App\Domain\SSA\Services\SSAMinutesSummaryService;
use App\Domain\SSA\Services\SSAService;
use App\Domain\User\Services\UserService;
use App\DTOs\ChangeSSAStatusDTO;
use App\DTOs\CreateSSADTO;
use App\DTOs\SessionLogIndexDTO;
use App\DTOs\SSAAssignmentDTO;
use App\DTOs\SSAFilterDTO;
use App\DTOs\StoreSSAImportDTO;
use App\DTOs\UpdateSSADTO;
use App\Enums\ServiceFrequency;
use App\Enums\SSAImportType;
use App\Enums\SSAStatus;
use App\Exceptions\ContractOverlapException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SSA\AssignTherapistRequest;
use App\Http\Requests\Admin\SSA\ChangeSSAStatusRequest;
use App\Http\Requests\Admin\SSA\ImportSSAsRequest;
use App\Http\Requests\Admin\SSA\IndexSSARequest;
use App\Http\Requests\Admin\SSA\SSADataRequest;
use App\Http\Requests\Admin\SSA\SSAImportDataRequest;
use App\Http\Requests\Admin\SSA\StoreSSARequest;
use App\Http\Requests\Admin\SSA\UnassignTherapistRequest;
use App\Http\Requests\Admin\SSA\UpdateSSARequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\ServiceSupportAgreement;
use App\Models\SSAImport;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SSAController extends Controller
{
    use DataTablesResponse;

    /**
     * @var array<int, string>
     */
    private const SSA_IMPORTS_ORDER_WHITELIST = [
        0 => 'ssa_imports.id',
        1 => 'ssa_imports.type',
        2 => 'ssa_imports.file_name',
        4 => 'ssa_imports.status',
        6 => 'ssa_imports.created_at',
    ];

    /**
     * @var array<int, string>
     */
    private const ORDER_WHITELIST = [
        0 => 'service_support_agreements.id',
        1 => 'students.name',
        2 => 'therapists.name',
        3 => 'service_support_agreements.start_date',
        4 => 'service_support_agreements.minutes_per_session',
        5 => 'service_support_agreements.tho_minutes',
        6 => 'service_support_agreements.status',
    ];

    public function __construct(
        private readonly SSAService $ssaService,
        private readonly SSAImportService $importService,
        private readonly UserService $userService,
        private readonly ServiceCatalogService $serviceCatalogService,
        private readonly SessionLogIndexService $sessionLogIndexService,
        private readonly SSAMinutesSummaryService $ssaMinutesSummaryService,
        private readonly SSAImportListService $importListService,
    ) {}

    public function index(IndexSSARequest $request): View
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $filters = SSAFilterDTO::fromArray($request->validated());
        $metrics = $this->ssaService->metrics();

        return view('admin.ssas.index', [
            'ssas' => collect(),
            'metrics' => $metrics,
            'filters' => $request->validated(),
            'statuses' => SSAStatus::cases(),
            'students' => $this->getActiveStudents(),
            'services' => $this->getActiveServices(),
            'therapists' => $this->getActiveTherapists(),
            'datatableUrl' => route('admin.ssas.data'),
        ]);
    }

    public function data(SSADataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);

        $filterData = [
            'search' => $request->input('filter_search'),
            'status' => $request->input('filter_status'),
            'student_id' => $request->input('filter_student_id'),
            'service_id' => $request->input('filter_service_id'),
            'therapist_id' => $request->input('filter_therapist_id'),
        ];
        $filters = SSAFilterDTO::fromArray($filterData);

        $result = $this->ssaService->listForDataTables($filters, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            [SSARowTransformer::class, 'transform']
        );
    }

    public function create(): View
    {
        $this->authorize('create', ServiceSupportAgreement::class);

        return view('admin.ssas.create', $this->formData());
    }

    public function store(StoreSSARequest $request): RedirectResponse
    {
        $this->authorize('create', ServiceSupportAgreement::class);

        try {
            $dto = CreateSSADTO::fromArray($request->validated());
            $this->ssaService->create($dto);

            return redirect()
                ->route('admin.ssas.index')
                ->with('status', 'SSA created successfully.');
        } catch (ContractOverlapException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['overlap' => $e->getMessage()]);
        }
    }

    public function show(Request $request, ServiceSupportAgreement $ssa): View
    {
        $this->authorize('view', $ssa);

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

        // Load tab-specific data only when needed
        if ($activeTab === 'assignment') {
            $ssa->load([
                'assignmentHistory.therapist',
                'assignmentHistory.assignedBy',
            ]);
            $viewData['assignmentHistory'] = $this->ssaService->getAssignmentHistory($ssa)->withUserTimezone();
        } elseif ($activeTab === 'session_logs') {
            $dto = SessionLogIndexDTO::fromArray(
                array_merge($request->query(), ['ssa_id' => $ssa->id])
            );
            $sessionLogData = $this->sessionLogIndexService->getAdminIndex($dto);

            $viewData['sessionLogs'] = $sessionLogData['sessionLogs'];
            $viewData['sessionLogColumns'] = $sessionLogData['columns'];
            $viewData['sessionLogRows'] = $sessionLogData['rows'];
            $viewData['sessionLogStatuses'] = $sessionLogData['statuses'];
            $viewData['sessionLogFilters'] = $request->query();
        }

        if ($activeTab === 'dashboard') {
            $viewData['minutesSummary'] = $this->ssaMinutesSummaryService->getMinutesSummaryForSSA($ssa);
        }

        return view('admin.ssas.show', $viewData);
    }

    public function edit(ServiceSupportAgreement $ssa): View
    {
        $this->authorize('update', $ssa);

        $ssa->load(['primaryService', 'additionalServices']);

        return view('admin.ssas.edit', [
            'ssa' => $ssa,
        ] + $this->formData());
    }

    public function update(UpdateSSARequest $request, ServiceSupportAgreement $ssa): RedirectResponse
    {
        $this->authorize('update', $ssa);

        try {
            $dto = UpdateSSADTO::fromArray($request->validated());
            $this->ssaService->update($ssa, $dto);

            return redirect()
                ->route('admin.ssas.index')
                ->with('status', 'SSA updated successfully.');
        } catch (ContractOverlapException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['overlap' => $e->getMessage()]);
        }
    }

    public function updateStatus(ChangeSSAStatusRequest $request, ServiceSupportAgreement $ssa): JsonResponse
    {
        $this->authorize('changeStatus', $ssa);

        try {
            $dto = ChangeSSAStatusDTO::fromArray($request->validated());
            $ssa = $this->ssaService->changeStatus($ssa, $dto);

            $message = match ($ssa->status) {
                SSAStatus::ACTIVE => 'SSA activated successfully.',
                SSAStatus::COMPLETED => 'SSA completed successfully.',
                SSAStatus::DEACTIVATED => 'SSA deactivated successfully.',
                default => 'SSA status updated successfully.',
            };

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function assignTherapist(AssignTherapistRequest $request, ServiceSupportAgreement $ssa): JsonResponse
    {
        $this->authorize('assignTherapist', $ssa);

        $dto = SSAAssignmentDTO::fromArray($request->validated());
        $this->ssaService->assignTherapist($ssa, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Therapist assigned successfully.',
        ]);
    }

    public function unassignTherapist(UnassignTherapistRequest $request, ServiceSupportAgreement $ssa): JsonResponse
    {
        $this->authorize('unassignTherapist', $ssa);

        $this->ssaService->unassignTherapist($ssa, $request->validated()['reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Therapist unassigned successfully.',
        ]);
    }

    public function export(IndexSSARequest $request)
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $filters = SSAFilterDTO::fromArray($request->validated());
        $ssas = $this->ssaService->paginate($filters);
        $filename = sprintf('ssas-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($ssas): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Student',
                'Primary Service',
                'Therapist',
                'Start Date',
                'End Date',
                'Minutes per Session',
                'Frequency',
                'Sessions per Frequency',
                'THO Minutes',
                'Served Minutes',
                'Status',
            ]);

            foreach ($ssas as $ssa) {
                fputcsv($handle, [
                    $ssa->id,
                    $ssa->student->name ?? '—',
                    $ssa->primaryService->name ?? '—',
                    $ssa->assignedTherapist->name ?? 'Unassigned',
                    $ssa->start_date->format('Y-m-d'),
                    $ssa->end_date->format('Y-m-d'),
                    $ssa->minutes_per_session,
                    $ssa->frequency->label(),
                    $ssa->sessions_per_frequency,
                    $ssa->tho_minutes,
                    $ssa->served_minutes,
                    $ssa->status->label(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function getActiveStudents(): Collection
    {
        return $this->userService->listActiveStudentsForSelect();
    }

    private function getActiveServices(): Collection
    {
        return $this->serviceCatalogService->listActiveWithFrequencyFlag();
    }

    private function getActiveTherapists(): Collection
    {
        return $this->userService->listActiveTherapistsForSelect();
    }

    public function therapistsForService(Request $request): JsonResponse
    {
        $this->authorize('create', ServiceSupportAgreement::class);

        $serviceIds = array_filter(
            array_map('intval', (array) $request->query('service_ids', [])),
            fn (int $id): bool => $id > 0,
        );

        $therapists = $serviceIds !== []
            ? $this->userService->listActiveTherapistsForServices($serviceIds)
            : $this->userService->listActiveTherapistsForSelect();

        return response()->json(
            $therapists->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values()
        );
    }

    public function showImportForm(): View
    {
        $this->authorize('create', ServiceSupportAgreement::class);

        $type = SSAImportType::NOVA;
        $template = $this->importService->getTemplate($type);

        return view('admin.ssas.import', [
            'importTypes' => SSAImportType::cases(),
            'requiredColumns' => $template['required_columns'] ?? [],
            'optionalColumns' => $template['optional_columns'] ?? [],
        ]);
    }

    public function import(ImportSSAsRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceSupportAgreement::class);

        $validated = $request->validated();
        $type = SSAImportType::from($validated['type'] ?? SSAImportType::NOVA->value);

        $dto = StoreSSAImportDTO::fromArray([
            'file' => $request->file('file'),
            'user_id' => $request->user()->id,
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

    public function showImportStatus(Request $request, SSAImport $import): View|JsonResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $import->load(['rows.ssa', 'user']);

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

        return view('admin.ssas.import-status', [
            'import' => $import,
            'stats' => $stats,
        ]);
    }

    public function importHistory(Request $request): View
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        return view('admin.ssas.import-history', [
            'imports' => collect(),
            'datatableUrl' => route('admin.ssas.imports.data'),
        ]);
    }

    public function importHistoryData(SSAImportDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $params = DataTablesRequest::fromRequest($request, self::SSA_IMPORTS_ORDER_WHITELIST);
        $result = $this->importListService->listForDataTables($params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn (SSAImport $import): array => SSAImportRowTransformer::transform($import),
        );
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        $this->authorize('create', ServiceSupportAgreement::class);

        $type = SSAImportType::from($request->query('type', SSAImportType::NOVA->value));
        $template = $this->importService->getTemplate($type);

        $requiredColumns = $template['required_columns'] ?? [];
        $optionalColumns = $template['optional_columns'] ?? [];
        $allColumns = array_merge($requiredColumns, $optionalColumns);

        $filename = sprintf('ssa-import-template-%s-%s.csv', strtolower($type->value), now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($allColumns): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $allColumns);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function formData(): array
    {
        return [
            'students' => $this->userService->listActiveStudentsForSelect(),
            'services' => $this->serviceCatalogService->listActiveWithFrequencyFlag(),
            'indirectServices' => $this->serviceCatalogService->listIndirectServices(),
            'therapists' => $this->userService->listActiveTherapistsForSelect(),
            'frequencies' => ServiceFrequency::cases(),
        ];
    }
}
