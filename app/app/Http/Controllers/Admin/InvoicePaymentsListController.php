<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\PaymentsListService;
use App\DTOs\InvoicePaymentFilterDTO;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Invoice\InvoicePaymentIndexRequest;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoicePaymentsListController extends Controller
{
    public function __construct(
        private readonly \App\Domain\Finance\Services\InvoicePaymentService $service,
        private readonly PaymentsListService $paymentsListService,
    ) {}

    public function index(InvoicePaymentIndexRequest $request): View
    {
        $this->authorize('viewAny', InvoicePayment::class);

        $filters = InvoicePaymentFilterDTO::fromArray($request->validated());

        $result = $this->paymentsListService->getInvoicePayments($filters);

        return view('admin.payments.invoice-payments.index', [
            'payments' => $result['payments'],
            'totalAmount' => $result['totalAmount'],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('viewAny', InvoicePayment::class);

        $invoices = Invoice::whereIn('status', [InvoiceStatus::DRAFT, InvoiceStatus::SENT])
            ->with('school')
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->get();

        return view('admin.payments.record-payment', [
            'mode' => 'invoice',
            'invoices' => $invoices,
        ]);
    }

    public function store(\App\Http\Requests\Admin\Invoice\RecordInvoicePaymentRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', InvoicePayment::class);

        $data = $request->validated();
        $data['recorded_by_id'] = $request->user()->id;

        $dto = \App\DTOs\RecordInvoicePaymentDTO::fromArray($data);

        try {
            $this->service->recordPayment($dto);

            return redirect()
                ->route('admin.payments.invoices.index')
                ->with('success', 'Payment recorded successfully.');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to record payment: '.$e->getMessage());
        }
    }

    public function destroy(InvoicePayment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        try {
            $this->service->deletePayment($payment);

            return redirect()
                ->route('admin.payments.invoices.index')
                ->with('success', 'Payment deleted successfully.');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete payment: '.$e->getMessage());
        }
    }
}
