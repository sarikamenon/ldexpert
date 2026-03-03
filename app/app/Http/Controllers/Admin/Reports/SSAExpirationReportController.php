<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\DataTables\Transformers\SSAExpirationReportRowTransformer;
use App\Domain\Service\Services\ServiceCatalogService;
use App\Domain\SSA\Services\SSAExpirationReportService;
use App\Domain\User\Services\UserService;
use App\DTOs\SSAReport\ExpirationReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\SSA\ExpirationReportDataRequest;
use App\Http\Requests\Admin\Reports\SSA\ExpirationReportRequest;
use App\Http\Support\DataTablesRequest;
use App\Models\School;
use App\Models\ServiceSupportAgreement;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SSAExpirationReportController extends Controller
{
    /** @var array<int, string> */
    private const ORDER_WHITELIST = [
        0 => 'id',
        1 => 'student_name',
        2 => 'school_name',
        3 => 'therapist_name',
        4 => 'service_name',
        5 => 'end_date',
        6 => 'days_diff',
    ];

    public function __construct(
        private readonly SSAExpirationReportService $reportService,
        private readonly UserService $userService,
        private readonly ServiceCatalogService $serviceCatalogService,
    ) {}

    public function index(ExpirationReportRequest $request): View
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        return view('admin.reports.ssa.expirations', [
            'filters' => $request->validated(),
            'schools' => $this->getActiveSchools(),
            'therapists' => $this->getActiveTherapists(),
            'services' => $this->getActiveServices(),
            'datatableUrl' => route('admin.reports.ssa.expirations.data'),
        ]);
    }

    public function data(ExpirationReportDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceSupportAgreement::class);

        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);

        $filters = ExpirationReportFilterDTO::fromArray([
            'expiration_window_days' => $request->input('filter_expiration_window_days', 30),
            'school_ids' => $request->input('filter_school_ids'),
            'therapist_ids' => $request->input('filter_therapist_ids'),
            'bucket' => $request->input('filter_bucket', 'upcoming'),
        ]);

        $reportData = $this->reportService->getReportData($filters);

        $bucket = $request->input('filter_bucket', 'upcoming');
        /** @var Collection<int, ServiceSupportAgreement> $items */
        $items = match ($bucket) {
            'expired' => $reportData['expired'],
            'pending' => $reportData['pending'],
            default => $reportData['upcoming'],
        };

        if ($params->searchValue) {
            $search = mb_strtolower($params->searchValue);
            $items = $items->filter(function ($ssa) use ($search): bool {
                if (! isset($ssa->id)) {
                    return false;
                }

                return str_contains(mb_strtolower($ssa->student->name ?? ''), $search)
                    || str_contains(mb_strtolower($ssa->assignedTherapist->name ?? ''), $search)
                    || str_contains(mb_strtolower($ssa->primaryService->name ?? ''), $search);
            })->values();
        }

        $recordsTotal = $items->count();
        $recordsFiltered = $items->count();

        $today = Carbon::today();
        if ($params->orderColumn) {
            $items = $items->sortBy(function ($ssa) use ($params, $today): mixed {
                return match ($params->orderColumn) {
                    'id' => $ssa->id ?? 0,
                    'student_name' => mb_strtolower($ssa->student->name ?? ''),
                    'school_name' => mb_strtolower($ssa->student?->studentProfile?->school->display_name ?? ''),
                    'therapist_name' => mb_strtolower($ssa->assignedTherapist->name ?? ''),
                    'service_name' => mb_strtolower($ssa->primaryService->name ?? ''),
                    'end_date' => $ssa->end_date !== null ? $ssa->end_date->timestamp : 0,
                    'days_diff' => $ssa->end_date ? (int) $today->diffInDays($ssa->end_date, false) : 0,
                    default => $ssa->id ?? 0,
                };
            }, descending: $params->orderDir === 'desc')->values();
        }

        $rows = $items->slice($params->start, $params->length)->values();

        $data = $rows
            ->map(static fn (ServiceSupportAgreement $ssa): array => SSAExpirationReportRowTransformer::transform($ssa))
            ->values()
            ->all();

        return response()->json([
            'draw' => $params->draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
            'summary' => $reportData['summary'],
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
            if ($handle === false) {
                throw new \RuntimeException('Failed to open CSV stream');
            }
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
