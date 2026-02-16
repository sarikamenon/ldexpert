<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class TherapistBillPaymentsListController extends Controller
{
    public function __construct(
        private readonly \App\Domain\Finance\Services\TherapistBillPaymentService $service,
    ) {}

    public function index(Request $request): View
    {
        $query = TherapistBillPayment::query()
            ->with(['therapist', 'allocations.therapistBill.therapist', 'recordedBy'])
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

        // Search by reference or therapist name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function (Builder $q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('allocations.therapistBill.therapist', function (Builder $sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $payments = $query->paginate(25)->withQueryString();

        $totalAmount = TherapistBillPayment::query()
            ->when($request->filled('from_date'), fn ($q) => $q->where('paid_at', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn ($q) => $q->where('paid_at', '<=', $request->to_date))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->method))
            ->sum('amount');

        return view('admin.payments.therapist-bill-payments.index', compact('payments', 'totalAmount'));
    }

    public function create(Request $request): View
    {
        $therapists = User::where('role', \App\Enums\Role::THERAPIST)
            ->orderBy('name')
            ->get();

        return view('admin.payments.record-payment', [
            'mode' => 'therapist',
            'entities' => $therapists,
        ]);
    }

    public function store(\App\Http\Requests\Admin\Billing\RecordTherapistBillPaymentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $therapistId = (int) $request->input('therapist_id');

        $startingBill = \App\Models\TherapistBill::where('therapist_id', $therapistId)
            ->where(function (Builder $q) {
                $q->where('status', '!=', \App\Enums\TherapistBillStatus::PAID);
            })
            ->orderBy('bill_date')
            ->orderBy('id')
            ->first();

        // If no unpaid bills exist, this will be treated as an advance payment.
        $data['therapist_bill_id'] = $startingBill?->id ?? 0;
        $data['therapist_id'] = $therapistId;
        $data['recorded_by_id'] = $request->user()->id;

        $dto = \App\DTOs\RecordTherapistBillPaymentDTO::fromArray($data);

        try {
            $this->service->recordPayment($dto);

            return redirect()
                ->route('admin.payments.therapist-bills.index')
                ->with('success', 'Payment recorded successfully.');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to record payment: '.$e->getMessage());
        }
    }

    public function destroy(TherapistBillPayment $payment): RedirectResponse
    {
        try {
            $this->service->deletePayment($payment);

            return redirect()
                ->route('admin.payments.therapist-bills.index')
                ->with('success', 'Payment deleted successfully.');
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete payment: '.$e->getMessage());
        }
    }
}
