<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\PaymentsListService;
use App\DTOs\TherapistBillPaymentFilterDTO;
use App\Enums\TherapistBillStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\TherapistBillPaymentIndexRequest;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

        $bills = TherapistBill::whereIn('status', [TherapistBillStatus::DRAFT, TherapistBillStatus::SENT])
            ->with('therapist')
            ->orderBy('bill_date')
            ->orderBy('id')
            ->get();

        return view('admin.payments.record-payment', [
            'mode' => 'therapist',
            'bills' => $bills,
        ]);
    }

    public function store(\App\Http\Requests\Admin\Billing\RecordTherapistBillPaymentRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', TherapistBillPayment::class);

        $data = $request->validated();
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
