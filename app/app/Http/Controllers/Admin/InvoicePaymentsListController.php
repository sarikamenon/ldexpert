<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoicePayment;
use App\Models\School;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class InvoicePaymentsListController extends Controller
{
    public function __construct(
        private readonly \App\Domain\Finance\Services\InvoicePaymentService $service,
    ) {}

    public function index(Request $request): View
    {
        $query = InvoicePayment::query()
            ->with(['school', 'allocations.invoice.school', 'recordedBy'])
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at');

        // Filter by date range
        if ($request->filled('from_date')) {
            $query->where('paid_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->where('paid_at', '<=', $request->to_date);
        }

        // Filter by payment method
        if ($request->filled('method')) {
            $query->where('method', $request->method);
        }

        // Search by reference or school name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('allocations.invoice.school', function (Builder $sq) use ($search) {
                        $sq->where(function (Builder $sqq) use ($search) {
                            $sqq->where('display_name', 'like', "%{$search}%")
                                ->orWhere('full_name', 'like', "%{$search}%");
                        });
                    });
            });
        }

        $payments = $query->paginate(25)->withQueryString();

        $totalAmount = InvoicePayment::query()
            ->when($request->filled('from_date'), fn ($q) => $q->where('paid_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($q) => $q->where('paid_at', '<=', $request->to_date))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->method))
            ->sum('amount');

        return view('admin.payments.invoice-payments.index', compact('payments', 'totalAmount'));
    }

    public function create(Request $request): View
    {
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
