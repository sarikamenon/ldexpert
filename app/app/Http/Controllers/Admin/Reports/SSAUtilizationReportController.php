<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSAUtilizationReportService;
use App\Domain\User\Services\UserService;
use App\DTOs\SSAReport\UtilizationReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\SSA\UtilizationReportRequest;
use App\Models\ServiceSupportAgreement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Collection;

final class SSAUtilizationReportController extends Controller
{
    public function __construct(
        private readonly SSAUtilizationReportService $reportService,
        private readonly UserService $userService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(UtilizationReportRequest $request): View
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $filters = UtilizationReportFilterDTO::fromArray($request->validated());
        $reportData = $this->reportService->getReportData($filters);

        return view('admin.reports.ssa.utilization', [
            'ssas' => $reportData['ssas'],
            'summary' => $reportData['summary'],
            'filters' => $request->validated(),
            'schools' => $this->getActiveSchools(),
            'therapists' => $this->getActiveTherapists(),
            'services' => $this->getActiveServices(),
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
                    $ssa->student?->studentProfile?->school?->display_name ?? '—',
                    $ssa->assignedTherapist->name ?? 'Unassigned',
                    $ssa->primaryService->name ?? '—',
                    $ssa->start_date->format('Y-m-d'),
                    $ssa->end_date->format('Y-m-d'),
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

    private function getActiveSchools(): Collection
    {
        return \App\Models\School::query()
            ->active()
            ->orderBy('display_name')
            ->get(['id', 'display_name']);
    }

    private function getActiveTherapists(): Collection
    {
        return $this->userService->listActiveTherapistsForSelect();
    }

    private function getActiveServices(): Collection
    {
        return $this->serviceCatalogService->listActiveForSelect();
    }
}
