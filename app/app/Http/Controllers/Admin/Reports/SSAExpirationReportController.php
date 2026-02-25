<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSAExpirationReportService;
use App\Domain\User\Services\UserService;
use App\DTOs\SSAReport\ExpirationReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\SSA\ExpirationReportRequest;
use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SSAExpirationReportController extends Controller
{
    public function __construct(
        private readonly SSAExpirationReportService $reportService,
        private readonly UserService $userService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(ExpirationReportRequest $request): View
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $filters = ExpirationReportFilterDTO::fromArray($request->validated());
        $reportData = $this->reportService->getReportData($filters);

        return view('admin.reports.ssa.expirations', [
            'upcoming' => $reportData['upcoming'],
            'expired' => $reportData['expired'],
            'pending' => $reportData['pending'],
            'no_current' => $reportData['no_current'],
            'summary' => $reportData['summary'],
            'filters' => $request->validated(),
            'schools' => $this->getActiveSchools(),
            'therapists' => $this->getActiveTherapists(),
            'services' => $this->getActiveServices(),
        ]);
    }

    public function export(ExpirationReportRequest $request): StreamedResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $filters = ExpirationReportFilterDTO::fromArray($request->validated());
        $ssas = $this->reportService->export($filters);
        $filename = sprintf('ssa-expiration-report-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($ssas): void {
            $handle = fopen('php://output', 'w');
            $today = Carbon::today();

            fputcsv($handle, [
                'SSA ID',
                'Student Name',
                'School',
                'Therapist',
                'Primary Service',
                'Start Date',
                'End Date',
                'Days Until/Since End',
                'THO Minutes',
                'Served Minutes',
                'Status',
            ]);

            foreach ($ssas as $ssa) {
                if (! isset($ssa->id)) {
                    continue;
                }

                $endDate = isset($ssa->end_date) ? Carbon::parse($ssa->end_date) : null;
                $daysDiff = $endDate ? $today->diffInDays($endDate, false) : null;

                fputcsv($handle, [
                    $ssa->id,
                    $ssa->student->name ?? '—',
                    $ssa->student?->studentProfile?->school->display_name ?? '—',
                    $ssa->assignedTherapist->name ?? 'Unassigned',
                    $ssa->primaryService->name ?? '—',
                    isset($ssa->start_date) ? $ssa->start_date->format('Y-m-d') : '—',
                    $endDate ? $endDate->format('Y-m-d') : '—',
                    $daysDiff ?? '—',
                    $ssa->tho_minutes ?? 0,
                    $ssa->served_minutes ?? 0,
                    isset($ssa->status) ? $ssa->status->label() : '—',
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
