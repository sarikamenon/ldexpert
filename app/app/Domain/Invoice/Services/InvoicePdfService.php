<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

final class InvoicePdfService
{
    public function generatePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        // Load relationships for PDF
        $invoice->load([
            'sessionLogs.student',
            'sessionLogs.service',
            'sessionLogs.therapist',
        ]);

        return Pdf::loadView('admin.invoices.pdf', [
            'invoice' => $invoice,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);
    }
}
