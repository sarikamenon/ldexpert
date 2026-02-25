<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\DataTables\Transformers\IrsReportRowTransformer;
use App\Domain\Finance\Services\IrsReportService;
use App\Domain\User\Services\UserService;
use App\DTOs\IrsReportFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\IrsReportDataRequest;
use App\Http\Requests\Admin\Finance\IrsReportRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\TherapistBill;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IrsReportController extends Controller
{
    use DataTablesResponse;

    private const ORDER_WHITELIST = [
        0 => 'recipient',
        1 => 'payment_date',
        2 => 'payment_method',
        3 => 'hourly_rate',
        4 => 'tax_status',
        5 => 'payroll_period',
        6 => 'regular_pay',
        7 => 'additional_pay',
        8 => 'total_deductions',
        9 => 'ytd_regular_pay',
        10 => 'total_gross',
        11 => 'total_net',
    ];

    public function __construct(
        private readonly IrsReportService $reportService,
        private readonly UserService $userService,
    ) {}

    public function index(IrsReportRequest $request): View
    {
        $this->authorize('viewAny', TherapistBill::class);

        $filters = IrsReportFilterDTO::fromArray($request->validated());
        $reportData = $this->reportService->getReportData($filters);

        return view('admin.finance.irs-report.index', [
            'rows' => [],
            'summary' => $reportData['summary'],
            'filters' => $request->validated(),
            'therapists' => $this->getActiveTherapists(),
            'datatableUrl' => route('admin.finance.irs-report.data'),
        ]);
    }

    public function data(IrsReportDataRequest $request): JsonResponse
    {
        $this->authorize('viewAny', TherapistBill::class);

        $filters = IrsReportFilterDTO::fromArray([
            'date_from' => $request->input('filter_date_from'),
            'date_to' => $request->input('filter_date_to'),
            'therapist_ids' => $request->input('filter_therapist_ids'),
        ]);
        $params = DataTablesRequest::fromRequest($request, self::ORDER_WHITELIST);
        $result = $this->reportService->getReportDataForDataTables($filters, $params);

        $transform = static fn (array $row): array => IrsReportRowTransformer::transform($row);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            collect($result['rows']),
            $transform,
        );
    }

    public function export(IrsReportRequest $request): StreamedResponse
    {
        $this->authorize('viewAny', TherapistBill::class);

        $filters = IrsReportFilterDTO::fromArray($request->validated());
        $reportData = $this->reportService->getReportData($filters);
        $filename = sprintf('irs-report-%s.csv', now()->format('Ymd_His'));

        return response()->streamDownload(function () use ($reportData): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Failed to open CSV stream');
            }

            foreach ($reportData['rows'] as $row) {
                $fmt = fn ($n) => number_format((float) $n, 2);
                $company = $row['company_name'];
                $recipient = $row['recipient'];
                $date = $row['payment_date_csv'];
                $method = $row['payment_method'];
                $rate = '$'.$fmt($row['hourly_rate']);
                $taxStatus = $row['tax_status'];
                $period = $row['payroll_period'];
                $addPct = $fmt($row['additional_percentage']).'%';
                $addAmt = '$'.$fmt($row['additional_amount']);
                $regPay = '$'.$fmt($row['regular_pay']);
                $addPay = '$'.$fmt($row['additional_pay']);
                $other1 = '$'.$fmt($row['other_pay_1']);
                $other2 = '$'.$fmt($row['other_pay_2']);
                $fedTax = '$'.$fmt($row['federal_tax']);
                $fedMed = '$'.$fmt($row['federal_med_tax']);
                $otherDed1 = '$'.$fmt($row['other_deduction_1']);
                $otherDed2 = '$'.$fmt($row['other_deduction_2']);
                $ytdReg = '$'.$fmt($row['ytd_regular_pay']);
                $ytdAdd = '$'.$fmt($row['ytd_additional_pay']);
                $ytdFed = '$'.$fmt($row['ytd_federal_tax']);
                $ytdFedMed = '$'.$fmt($row['ytd_federal_med_tax']);
                $totalGross = '$'.$fmt($row['total_gross']);
                $totalDed = '$'.$fmt($row['total_deductions']);
                $totalNet = '$'.$fmt($row['total_net']);

                fputcsv($handle, ['', '', $company, '', '', '']);
                fputcsv($handle, ['RECIPIENT', $recipient, 'TAX STATUS', $taxStatus, '', '']);
                fputcsv($handle, ['PAYMENT DATE', $date, 'PAYROLL PERIOD', $period, '', '']);
                fputcsv($handle, ['PAYMENT METHOD', $method, 'ADDITIONAL PERCENTAGE', $addPct, '', '']);
                fputcsv($handle, ['HOURLY PAY RATE', $rate, 'ADDITIONAL AMOUNT', $addAmt, '', '']);
                fputcsv($handle, ['PAYMENTS', '', 'DEDUCTIONS', '', 'YEAR-TO-DATE', '']);
                fputcsv($handle, ['REGULAR PAY', $regPay, 'FEDERAL TAX', $fedTax, 'REGULAR PAY', $ytdReg]);
                fputcsv($handle, ['ADDITIONAL PAY', $addPay, 'FEDERAL MED TAX', $fedMed, 'ADDITIONAL PAY', $ytdAdd]);
                fputcsv($handle, ['OTHER', $other1, 'OTHER', $otherDed1, 'FEDERAL TAX', $ytdFed]);
                fputcsv($handle, ['OTHER', $other2, 'OTHER', $otherDed2, 'FEDERAL MED TAX', $ytdFedMed]);
                fputcsv($handle, ['TOTAL GROSS PAY', $totalGross, 'TOTAL DEDUCTIONS', $totalDed, 'TOTAL NET PAY', $totalNet]);
                fputcsv($handle, ['', '', '', '', '', '']);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return Collection<int, \App\Models\User> */
    private function getActiveTherapists(): Collection
    {
        return $this->userService->listActiveTherapistsForSelect();
    }
}
