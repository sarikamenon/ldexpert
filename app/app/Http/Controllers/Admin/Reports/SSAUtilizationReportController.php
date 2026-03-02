<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\DataTables\Transformers\SSAUtilizationReportRowTransformer;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSAUtilizationReportService;
use App\Domain\User\Services\UserService;
use App\DTOs\SSAReport\UtilizationReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\SSA\UtilizationReportDataRequest;
use App\Http\Requests\Admin\Reports\SSA\UtilizationReportRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\School;
use App\Models\ServiceSupportAgreement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SSAUtilizationReportController extends Controller
{
    use DataTablesResponse;

    /** @var array<int, string> */
    private const ORDER_WHITELIST = [
        0 => 'service_support_agreements.id',
        1 => 'student_name',
        2 => 'school_name',
        3 => 'therapist_name',
        4 => 'service_name',
        5 => 'service_support_agreements.tho_minutes',
        6 => 'service_support_agreements.served_minutes',
        7 => 'utilization_percent',
        8 => 'service_support_agreements.status',
    ];

    public function __construct(
        private readonly SSAUtilizationReportService $reportService,
        private readonly UserService $userService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(UtilizationReportRequest $request): View
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        return view('admin.reports.ssa.utilization', [
            'filters' => $request->validated(),
            'schools' => $this->getActiveSchools(),
            'therapists' => $this->getActiveTherapists(),
            'services' => $this->getActiveServices(),
            'datatableUrl' => route('admin.reports.ssa.utilization.data'),
        ]);
    }

    public function data(UtilizationReportDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);

        $filters = UtilizationReportFilterDTO::fromArray([
            'start_date' => $request->input('filter_start_date'),
            'end_date' => $request->input('filter_end_date'),
            'school_ids' => $request->input('filter_school_ids'),
            'therapist_ids' => $request->input('filter_therapist_ids'),
            'service_ids' => $request->input('filter_service_ids'),
            'per_page' => 10000,
        ]);

        $reportData = $this->reportService->getReportData($filters);
        /** @var array<int, ServiceSupportAgreement> $items */
        $items = $reportData['ssas']->items();
        $allItems = new Collection($items);

        if ($params->searchValue) {
            $search = mb_strtolower($params->searchValue);
            $allItems = $allItems->filter(function (ServiceSupportAgreement $ssa) use ($search): bool {
                return str_contains(mb_strtolower($ssa->student->name ?? ''), $search)
                    || str_contains(mb_strtolower($ssa->assignedTherapist->name ?? ''), $search)
                    || str_contains(mb_strtolower($ssa->primaryService->name ?? ''), $search);
            })->values();
        }

        $recordsTotal = $allItems->count();
        $recordsFiltered = $allItems->count();

        $orderColumn = $params->orderColumn;
        $orderDir = $params->orderDir;
        if ($orderColumn) {
            $allItems = $allItems->sortBy(function (ServiceSupportAgreement $ssa) use ($orderColumn): mixed {
                return match ($orderColumn) {
                    'service_support_agreements.id' => $ssa->id,
                    'student_name' => mb_strtolower($ssa->student->name ?? ''),
                    'school_name' => mb_strtolower($ssa->student?->studentProfile?->school->display_name ?? ''),
                    'therapist_name' => mb_strtolower($ssa->assignedTherapist->name ?? ''),
                    'service_name' => mb_strtolower($ssa->primaryService->name ?? ''),
                    'service_support_agreements.tho_minutes' => $ssa->tho_minutes ?? 0,
                    'service_support_agreements.served_minutes' => $ssa->served_minutes ?? 0,
                    'utilization_percent' => ($ssa->tho_minutes ?? 0) > 0
                        ? round((($ssa->served_minutes ?? 0) / ($ssa->tho_minutes ?? 1)) * 100, 2)
                        : 0.0,
                    'service_support_agreements.status' => $ssa->status->value,
                    default => $ssa->id,
                };
            }, descending: $orderDir === 'desc')->values();
        }

        $rows = $allItems->slice($params->start, $params->length)->values();

        $data = $rows->map(
            static fn (ServiceSupportAgreement $ssa): array => SSAUtilizationReportRowTransformer::transform($ssa)
        )->all();

        return response()->json([
            'draw' => $params->draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'summary' => $reportData['summary'],
        ]);
    }

    public function export(UtilizationReportRequest $request): StreamedResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $filters = UtilizationReportFilterDTO::fromArray($request->validated());
        $ssas = $this->reportService->export($filters);
        $filename = sprintf('ssa-utilization-report-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($ssas): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Failed to open CSV stream');
            }
            fputcsv($handle, [
                'SSA ID',
                'Student Name',
                'School',
                'Therapist',
                'Primary Service',
                'Start Date',
                'End Date',
                'THO Minutes',
                'Served Minutes',
                'Utilization %',
                'Variance Minutes',
                'Status',
            ]);

            foreach ($ssas as $ssa) {
                $tho = $ssa->tho_minutes ?? 0;
                $served = $ssa->served_minutes ?? 0;
                $utilization = $tho > 0 ? round(($served / $tho) * 100, 2) : 0;
                $variance = $served - $tho;

                fputcsv($handle, [
                    $ssa->id,
                    $ssa->student->name ?? '—',
                    $ssa->student?->studentProfile?->school->display_name ?? '—',
                    $ssa->assignedTherapist->name ?? 'Unassigned',
                    $ssa->primaryService->name ?? '—',
                    $ssa->start_date->format('Y-m-d'),
                    $ssa->end_date?->format('Y-m-d') ?? '',
                    $tho,
                    $served,
                    $utilization,
                    $variance,
                    $ssa->status->label(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
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
