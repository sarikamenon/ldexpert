<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\PaymentsListService;
use App\DTOs\InvoicePaymentFilterDTO;
use App\Http\Controllers\Controller;
use App\Models\InvoicePayment;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\Invoice\InvoicePaymentIndexRequest;

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

        $schools = School::orderBy('display_name')
            ->orderBy('full_name')
            ->get();

        return view('admin.payments.record-payment', [
            'mode' => 'invoice',
            'entities' => $schools,
        ]);
    }

    public function store(\App\Http\Requests\Admin\Invoice\RecordInvoicePaymentRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', InvoicePayment::class);

        $data = $request->validated();
        $schoolId = (int) $request->input('school_id');

        $startingInvoice = \App\Models\Invoice::where('school_id', $schoolId)
            ->where(function (Builder $q) {
                $q->where('status', '!=', \App\Enums\InvoiceStatus::PAID);
            })
            ->orderBy('invoice_date')
            ->orderBy('id')
            ->first();

        // If no unpaid invoices exist, this will be treated as an advance payment.
        // We still record the payment and ledger entry, but no allocations are created.
        $data['invoice_id'] = $startingInvoice?->id ?? 0;
        $data['school_id'] = $schoolId;
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
