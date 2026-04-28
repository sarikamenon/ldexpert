<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\DataTables\Transformers\PayStubReportRowTransformer;
use App\Domain\Finance\Services\PayStubPdfService;
use App\Domain\Finance\Services\PayStubReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\PayStubDownloadRequest;
use App\Http\Requests\Admin\Finance\PayStubReportDataRequest;
use App\Http\Requests\Admin\Finance\PayStubReportRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\TherapistBill;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PayStubReportController extends Controller
{
    use DataTablesResponse;

    /** @var array<int, string> */
    private const ORDER_WHITELIST = [
        0 => 'therapist_name',
        1 => 'payment_count',
        2 => 'total_amount',
    ];

    public function __construct(
        private readonly PayStubReportService $reportService,
        private readonly PayStubPdfService $pdfService,
    ) {}

    public function index(PayStubReportRequest $request): View
    {
        $this->authorize('viewAny', TherapistBill::class);

        $currentYear = (int) date('Y');
        $selectedYear = (int) ($request->validated('year') ?? $currentYear);

        return view('admin.finance.pay-stub-report.index', [
            'selectedYear' => $selectedYear,
            'years' => range($currentYear, 2026),
            'datatableUrl' => route('admin.finance.pay-stub-report.data'),
        ]);
    }

    public function data(PayStubReportDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', TherapistBill::class);

        $year = (int) $request->input('filter_year', (string) date('Y'));
        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);

        $allRows = $this->reportService->getTherapistsWithPayments($year);
        $recordsTotal = count($allRows);

        if ($params->searchValue !== null && $params->searchValue !== '') {
            $sv = mb_strtolower($params->searchValue);
            $allRows = array_values(array_filter($allRows, function (array $row) use ($sv): bool {
                return str_contains(mb_strtolower($row['therapist_name']), $sv);
            }));
        }
        $recordsFiltered = count($allRows);

        $orderKey = $params->orderColumn ?? 'therapist_name';
        $dir = $params->orderDir === 'desc' ? -1 : 1;
        usort($allRows, function (array $a, array $b) use ($orderKey, $dir): int {
            $va = $a[$orderKey] ?? '';
            $vb = $b[$orderKey] ?? '';
            if (is_numeric($va) && is_numeric($vb)) {
                return (int) (($va <=> $vb) * $dir);
            }

            return strcmp((string) $va, (string) $vb) * $dir;
        });

        $allRows = array_slice($allRows, $params->start, $params->length);

        $transform = static fn (array $row): array => PayStubReportRowTransformer::transform($row, $year);

        return $this->dataTablesResponse(
            $params,
            $recordsTotal,
            $recordsFiltered,
            collect($allRows),
            $transform,
        );
    }

    public function download(PayStubDownloadRequest $request): Response
    {
        $this->authorize('viewAny', TherapistBill::class);

        $therapistId = (int) $request->validated('therapist_id');
        $year = (int) $request->validated('year');

        $pdf = $this->pdfService->generatePdf($therapistId, $year);

        /** @var User|null $therapist */
        $therapist = User::find($therapistId);
        $therapistName = $therapist !== null ? strtolower(str_replace(' ', '-', $therapist->name)) : 'unknown';
        $filename = sprintf('pay-stub-%s-%02d-%d.pdf', $therapistName, $therapistId, $year);

        return $pdf->download($filename);
    }
}
