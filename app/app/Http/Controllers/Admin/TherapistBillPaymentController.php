<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\TherapistBillPaymentService;
use App\DTOs\RecordTherapistBillPaymentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Billing\RecordTherapistBillPaymentRequest;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use Illuminate\Http\RedirectResponse;

class TherapistBillPaymentController extends Controller
{
    public function __construct(
        private readonly TherapistBillPaymentService $service,
    ) {}

    /**
     * Record a payment against a therapist bill.
     */
    public function store(RecordTherapistBillPaymentRequest $request, TherapistBill $therapistBill): RedirectResponse
    {
        $data = $request->validated();
        $data['therapist_bill_id'] = $therapistBill->id;
        $data['recorded_by_id'] = $request->user()?->id;

        $dto = RecordTherapistBillPaymentDTO::fromArray($data);

        try {
            $this->service->recordPayment($dto);

            return redirect()
                ->route('admin.billing.therapist-bills.show', $therapistBill)
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
    public function destroy(TherapistBill $therapistBill, TherapistBillPayment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        try {
            $this->service->deletePayment($payment);

            return redirect()
                ->route('admin.billing.therapist-bills.show', $therapistBill)
                ->with('success', 'Payment deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to delete payment: '.$e->getMessage());
        }
    }
}
