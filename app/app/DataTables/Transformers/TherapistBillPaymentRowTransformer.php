<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\TherapistBillPayment;

final class TherapistBillPaymentRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(TherapistBillPayment $payment): array
    {
        $date = $payment->paid_at->format('M d, Y');

        $billCell = '—';
        if ($payment->therapistBill) {
            $showUrl = route('admin.billing.therapist-bills.show', $payment->therapistBill);
            $therapistName = $payment->therapist->name ?? $payment->therapistBill->therapist_name ?? '—';
            $billCell = '<a href="'.e($showUrl).'" class="text-primary hover:underline">'.e($payment->therapistBill->bill_number).'</a>'
                .' <span class="text-foreground/60">— '.e($therapistName).'</span>';
        }

        $amount = '$'.number_format((float) $payment->amount, 2);
        $methodLabel = $payment->method->label();
        $methodBadge = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">'.e($methodLabel).'</span>';
        $reference = e($payment->reference ?? '—');
        $recordedBy = e($payment->recordedBy->name ?? 'System');

        $actions = ActionButtons::wrap(
            ActionButtons::delete(
                route('admin.payments.therapist-bills.destroy', $payment),
                'Delete Payment',
                'Delete therapist bill payment?',
                'This will remove all allocations and the related ledger entry. This action cannot be undone.',
                ['form-class' => 'inline js-therapist-bill-payment-delete-form'],
            ),
        );

        return [
            $date,
            $billCell,
            $amount,
            $methodBadge,
            $reference,
            $recordedBy,
            $actions,
        ];
    }
}
