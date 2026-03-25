<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Services;

use App\Enums\BillingMode;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

final class InvoicePdfService
{
    public function generatePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        if ($invoice->billing_mode === BillingMode::ADVANCE) {
            return $this->generateAdvancePdf($invoice);
        }

        return $this->generateStandardPdf($invoice);
    }

    private function generateStandardPdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
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

    private function generateAdvancePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load([
            'lineItems',
            'student.studentProfile',
        ]);

        $adjustmentLines = $invoice->lineItems->filter(
            fn ($item) => $item->isAdjustment()
        )->values();

        $advanceLines = $invoice->lineItems->filter(
            fn ($item) => $item->isAdvanceCharge()
        )->values();

        $standardLines = $invoice->lineItems->filter(
            fn ($item) => ! $item->isAdjustment() && ! $item->isAdvanceCharge()
        )->values();

        $adjustmentSubtotal = $adjustmentLines->sum('total');
        $advanceSubtotal = $advanceLines->sum('total');

        return Pdf::loadView('admin.invoices.pdf-advance', [
            'invoice' => $invoice,
            'adjustmentLines' => $adjustmentLines,
            'advanceLines' => $advanceLines,
            'standardLines' => $standardLines,
            'adjustmentSubtotal' => $adjustmentSubtotal,
            'advanceSubtotal' => $advanceSubtotal,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);
    }
}
