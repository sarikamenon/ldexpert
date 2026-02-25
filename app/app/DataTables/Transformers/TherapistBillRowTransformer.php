<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

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

        $iconView = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        $iconDownload = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1"></path><path d="M12 12v9"></path><path d="M8 16l4 4 4-4"></path><path d="M12 3v9"></path></svg>';
        $actions = '<div class="flex space-x-1">'
            .'<a href="'.e($showUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="View Bill" aria-label="View bill '.e($bill->bill_number).'">'.$iconView.'</a>'
            .'<a href="'.e($downloadUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="Download PDF" aria-label="Download bill '.e($bill->bill_number).' as PDF">'.$iconDownload.'</a>'
            .'</div>';

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
