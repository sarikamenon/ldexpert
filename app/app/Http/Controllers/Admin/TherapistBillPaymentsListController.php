<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\PaymentsListService;
use App\DTOs\TherapistBillPaymentFilterDTO;
use App\Http\Controllers\Controller;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\Admin\Billing\TherapistBillPaymentIndexRequest;

class TherapistBillPaymentsListController extends Controller
{
    public function __construct(
        private readonly \App\Domain\Finance\Services\TherapistBillPaymentService $service,
        private readonly PaymentsListService $paymentsListService,
    ) {}

    public function index(TherapistBillPaymentIndexRequest $request): View
    {
        $this->authorize('viewAny', TherapistBillPayment::class);

        $filters = TherapistBillPaymentFilterDTO::fromArray($request->validated());

        $result = $this->paymentsListService->getTherapistBillPayments($filters);

        return view('admin.payments.therapist-bill-payments.index', [
            'payments' => $result['payments'],
            'totalAmount' => $result['totalAmount'],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('viewAny', TherapistBillPayment::class);

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
        $this->authorize('viewAny', TherapistBillPayment::class);

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
        $this->authorize('delete', $payment);

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
