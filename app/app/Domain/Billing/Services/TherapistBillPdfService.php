<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Models\TherapistBill;
use Barryvdh\DomPDF\Facade\Pdf;

final class TherapistBillPdfService
{
    public function generatePdf(TherapistBill $bill): \Barryvdh\DomPDF\PDF
    {
        // Load relationships for PDF
        $bill->load([
            'sessionLogs.student',
            'sessionLogs.service',
            'sessionLogs.therapist',
        ]);

        return Pdf::loadView('admin.billing.therapist-bills.pdf', [
            'bill' => $bill,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);
    }
}
