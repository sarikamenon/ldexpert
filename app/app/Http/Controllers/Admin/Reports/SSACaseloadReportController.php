<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\DataTables\Transformers\SSACaseloadReportRowTransformer;
use App\DataTables\Transformers\SSACaseloadUnassignedRowTransformer;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSACaseloadReportService;
use App\Domain\User\Services\UserService;
use App\DTOs\SSAReport\CaseloadReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\SSA\CaseloadReportDataRequest;
use App\Http\Requests\Admin\Reports\SSA\CaseloadReportRequest;
use App\Http\Support\DataTablesRequest;
use App\Models\School;
use App\Models\ServiceSupportAgreement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SSACaseloadReportController extends Controller
{
    /** @var array<int, string> */
    private const THERAPIST_ORDER_WHITELIST = [
        0 => 'therapist_name',
        2 => 'active_ssa_count',
        3 => 'authorized_minutes_per_week',
    ];

    /** @var array<int, string> */
    private const UNASSIGNED_ORDER_WHITELIST = [
        0 => 'student_name',
        3 => 'tho_minutes',
    ];

    public function __construct(
        private readonly SSACaseloadReportService $reportService,
        private readonly UserService $userService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(CaseloadReportRequest $request): View
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        return view('admin.reports.ssa.caseload', [
            'filters' => $request->validated(),
            'schools' => $this->getActiveSchools(),
            'therapists' => $this->getActiveTherapists(),
            'services' => $this->getActiveServices(),
            'therapistDatatableUrl' => route('admin.reports.ssa.caseload.therapist-data'),
            'unassignedDatatableUrl' => route('admin.reports.ssa.caseload.unassigned-data'),
        ]);
    }

    public function therapistData(CaseloadReportDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $params = DataTablesRequest::fromRequest($request, self::THERAPIST_ORDER_WHITELIST);
        $filters = $this->buildFilters($request);
        $reportData = $this->reportService->getReportData($filters);

        /** @var Collection<int, array<string, mixed>> $therapistData */
        $therapistData = $reportData['therapistData'];

        if ($params->searchValue) {
            $search = mb_strtolower($params->searchValue);
            $therapistData = $therapistData->filter(function (array $data) use ($search): bool {
                return str_contains(mb_strtolower($data['therapist']->name ?? ''), $search);
            })->values();
        }

        $recordsTotal = $therapistData->count();
        $recordsFiltered = $therapistData->count();

        if ($params->orderColumn) {
            $therapistData = $therapistData->sortBy(function (array $data) use ($params): mixed {
                return match ($params->orderColumn) {
                    'therapist_name' => mb_strtolower($data['therapist']->name ?? ''),
                    'active_ssa_count' => $data['active_ssa_count'] ?? 0,
                    'authorized_minutes_per_week' => $data['authorized_minutes_per_week'] ?? 0,
                    default => mb_strtolower($data['therapist']->name ?? ''),
                };
            }, descending: $params->orderDir === 'desc')->values();
        }

        $rows = $therapistData->slice($params->start, $params->length)->values();
        $data = $rows->map(
            static fn (array $row): array => SSACaseloadReportRowTransformer::transform($row)
        )->all();

        return response()->json([
            'draw' => $params->draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'summary' => $reportData['summary'],
            'unassignedCount' => $reportData['unassignedSSAs']->count(),
        ]);
    }

    public function unassignedData(CaseloadReportDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $params = DataTablesRequest::fromRequest($request, self::UNASSIGNED_ORDER_WHITELIST);
        $filters = $this->buildFilters($request);
        $reportData = $this->reportService->getReportData($filters);

        /** @var Collection<int, ServiceSupportAgreement> $unassigned */
        $unassigned = $reportData['unassignedSSAs'];

        if ($params->searchValue) {
            $search = mb_strtolower($params->searchValue);
            $unassigned = $unassigned->filter(function (ServiceSupportAgreement $ssa) use ($search): bool {
                return str_contains(mb_strtolower($ssa->student->name ?? ''), $search)
                    || str_contains(mb_strtolower($ssa->primaryService->name ?? ''), $search);
            })->values();
        }

        $recordsTotal = $unassigned->count();
        $recordsFiltered = $unassigned->count();

        if ($params->orderColumn) {
            $unassigned = $unassigned->sortBy(function (ServiceSupportAgreement $ssa) use ($params): mixed {
                return match ($params->orderColumn) {
                    'student_name' => mb_strtolower($ssa->student->name ?? ''),
                    'tho_minutes' => $ssa->tho_minutes ?? 0,
                    default => mb_strtolower($ssa->student->name ?? ''),
                };
            }, descending: $params->orderDir === 'desc')->values();
        }

        $rows = $unassigned->slice($params->start, $params->length)->values();
        $data = $rows->map(
            static fn (ServiceSupportAgreement $ssa): array => SSACaseloadUnassignedRowTransformer::transform($ssa)
        )->all();

        return response()->json([
            'draw' => $params->draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    public function export(CaseloadReportRequest $request): StreamedResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $filters = CaseloadReportFilterDTO::fromArray($request->validated());
        $ssas = $this->reportService->export($filters);
        $filename = sprintf('ssa-caseload-report-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($ssas): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Failed to open CSV stream');
            }
            fputcsv($handle, [
                'Therapist Name',
                'School',
                'Student Name',
                'Primary Service',
                'Active SSA Count',
                'THO Minutes',
                'Status',
            ]);

            foreach ($ssas as $ssa) {
                fputcsv($handle, [
                    $ssa->assignedTherapist->name ?? 'Unassigned',
                    $ssa->student?->studentProfile?->school->display_name ?? '—',
                    $ssa->student->name ?? '—',
                    $ssa->primaryService->name ?? '—',
                    '',
                    $ssa->tho_minutes ?? 0,
                    $ssa->status->label(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildFilters(CaseloadReportDataRequest $request): CaseloadReportFilterDTO
    {
        return CaseloadReportFilterDTO::fromArray([
            'school_ids' => $request->input('filter_school_ids'),
            'therapist_ids' => $request->input('filter_therapist_ids'),
            'service_ids' => $request->input('filter_service_ids'),
        ]);
    }

    /** @return Collection<int, School> */
    private function getActiveSchools(): Collection
    {
        return School::query()
            ->active()
            ->orderBy('display_name')
            ->get(['id', 'display_name']);
    }

    /** @return Collection<int, \App\Models\User> */
    private function getActiveTherapists(): Collection
    {
        return $this->userService->listActiveTherapistsForSelect();
    }

    /** @return Collection<int, \App\Models\Service> */
    private function getActiveServices(): Collection
    {
        return $this->serviceCatalogService->listActiveForSelect();
    }
}
