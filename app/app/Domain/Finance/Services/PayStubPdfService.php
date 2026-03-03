<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

final class PayStubPdfService
{
    public function __construct(
        private readonly PayStubReportService $reportService,
    ) {}

    public function generatePdf(int $therapistId, int $year): DomPdf
    {
        $data = $this->reportService->getTherapistPayStubData($therapistId, $year);

        /** @var DomPdf $pdf */
        $pdf = Pdf::loadView('admin.finance.pay-stub-report.pdf', [
            'rows' => $data['rows'],
            'summary' => $data['summary'],
            'therapistName' => $data['therapist_name'],
            'year' => $data['year'],
            'companyName' => (string) config('finance.company_name', 'The LD Expert, LLC'),
            'taxStatus' => (string) config('finance.irs_tax_status', '1099-NEC'),
        ]);

        return $pdf->setPaper('a4', 'portrait');
    }
}
