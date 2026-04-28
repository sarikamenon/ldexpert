<?php

declare(strict_types=1);

namespace App\Http\Controllers\Therapist\Finance;

use App\Domain\Finance\Services\PayStubPdfService;
use App\Domain\Finance\Services\PayStubReportService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Therapist\Finance\PayStubDownloadRequest;
use App\Models\TherapistBill;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PayStubController extends Controller
{
    public function __construct(
        private readonly PayStubReportService $reportService,
        private readonly PayStubPdfService $pdfService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', TherapistBill::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $years = $this->reportService->getYearsWithPayments($user->id);

        $yearRows = collect($years)->map(function (int $year) use ($user): array {
            $data = $this->reportService->getTherapistPayStubData($user->id, $year);

            return [
                'year' => $year,
                'payment_count' => $data['summary']['row_count'],
                'total_amount' => $data['summary']['total_gross'],
            ];
        })->all();

        return view('therapist.finance.pay-stub.index', [
            'yearRows' => $yearRows,
        ]);
    }

    public function download(PayStubDownloadRequest $request): Response
    {
        $this->authorize('viewAny', TherapistBill::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $year = (int) $request->validated('year');

        $pdf = $this->pdfService->generatePdf($user->id, $year);
        $filename = sprintf('pay-stub-%d.pdf', $year);

        return $pdf->download($filename);
    }
}
