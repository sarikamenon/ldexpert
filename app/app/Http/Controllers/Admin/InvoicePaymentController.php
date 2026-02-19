<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\InvoicePaymentService;
use App\DTOs\RecordInvoicePaymentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Invoice\RecordInvoicePaymentRequest;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\RedirectResponse;

class InvoicePaymentController extends Controller
{
    public function __construct(
        private readonly InvoicePaymentService $service,
    ) {}

    /**
     * Record a payment against an invoice.
     */
    public function store(RecordInvoicePaymentRequest $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validated();
        $data['invoice_id'] = $invoice->id;
        $data['recorded_by_id'] = $request->user()?->id;

        $dto = RecordInvoicePaymentDTO::fromArray($data);

        try {
            $this->service->recordPayment($dto);

            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('success', 'Payment recorded successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to record payment: '.$e->getMessage());
        }
    }

    /**
     * Delete a payment.
     */
    public function destroy(Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        try {
            $this->service->deletePayment($payment);

            return redirect()
                ->route('admin.invoices.show', $invoice)
                ->with('success', 'Payment deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete payment: '.$e->getMessage());
        }
    }
}
