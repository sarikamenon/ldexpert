<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSACaseloadReportService;
use App\Domain\User\Services\UserService;
use App\DTOs\SSAReport\CaseloadReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\SSA\CaseloadReportRequest;
use App\Models\ServiceSupportAgreement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SSACaseloadReportController extends Controller
{
    public function __construct(
        private readonly SSACaseloadReportService $reportService,
        private readonly UserService $userService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(CaseloadReportRequest $request): View
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $filters = CaseloadReportFilterDTO::fromArray($request->validated());
        $reportData = $this->reportService->getReportData($filters);

        return view('admin.reports.ssa.caseload', [
            'therapistData' => $reportData['therapistData'],
            'unassignedSSAs' => $reportData['unassignedSSAs'],
            'summary' => $reportData['summary'],
            'filters' => $request->validated(),
            'schools' => $this->getActiveSchools(),
            'therapists' => $this->getActiveTherapists(),
            'services' => $this->getActiveServices(),
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
                    '', // Active SSA count would need aggregation
                    $ssa->tho_minutes ?? 0,
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
