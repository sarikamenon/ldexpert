<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\TherapistBillStatus;
use App\Models\TherapistBill;

final class TherapistBillRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(TherapistBill $bill): array
    {
        $showUrl = route('admin.billing.therapist-bills.show', $bill);
        $downloadUrl = route('admin.billing.therapist-bills.download', $bill);
        $deleteUrl = route('admin.billing.therapist-bills.destroy', $bill);

        $billNumberBtn = '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors" title="View Bill" aria-label="View bill '.e($bill->bill_number).'">'.e($bill->bill_number).'</a>';
        $therapistCell = '<a href="'.e($showUrl).'" class="text-primary hover:underline font-medium">'.e($bill->therapist_name ?? '—').'</a>';
        $period = $bill->billing_period_start && $bill->billing_period_end
            ? $bill->billing_period_start->format('M d').' - '.$bill->billing_period_end->format('M d, Y')
            : '—';
        $total = '$'.number_format((float) $bill->total_due, 2);

        $statusLabel = $bill->status->label();
        $badgeClass = match ($bill->status) {
            TherapistBillStatus::DRAFT => 'bg-secondary/10 text-secondary border border-secondary/20',
            TherapistBillStatus::SENT => 'bg-primary/10 text-primary border border-primary/20',
            TherapistBillStatus::PAID => 'bg-success/10 text-success border border-success/20',
        };
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.e($statusLabel).'</span>';
        $dueDate = $bill->due_date ? $bill->due_date->format('M d, Y') : '—';

        $actions = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View Bill'),
            ActionButtons::download($downloadUrl, 'Download PDF'),
            ...($bill->isPaid() ? [] : [ActionButtons::delete(
                $deleteUrl,
                'Delete Bill',
                'Delete Bill?',
                'This will unlink all sessions and remove the bill. This cannot be undone.',
                ['form-class' => 'inline js-therapist-bill-delete-form'],
            )]),
        );

        return [
            $billNumberBtn,
            $therapistCell,
            e($period),
            $total,
            $statusBadge,
            e($dueDate),
            $actions,
        ];
    }
}
