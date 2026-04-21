<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\Expense;
use App\Models\TherapistBillPayment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class ExpenseRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(Expense $expense): array
    {
        $date = $expense->expense_date->format('M d, Y');
        $categoryName = $expense->category ? e($expense->category->name) : '—';
        if ($expense->source_type !== null) {
            $categoryName .= ' <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-base text-[10px] font-medium bg-info/10 text-info border border-info/20" title="Auto-linked from another module">System</span>';
        }

        $source = $expense->source;
        $vendorText = e($expense->vendor_payee ?? '—');
        $descriptionText = e(Str::limit($expense->description ?? '—', 50));
        $vendor = $vendorText;
        $description = $descriptionText;

        if ($source instanceof TherapistBillPayment) {
            $bill = $source->therapistBill;
            $therapist = $bill?->therapist;
            if ($therapist !== null) {
                $vendor = '<a href="'.e(route('admin.therapists.show', $therapist)).'" class="text-primary hover:underline font-medium">'.$vendorText.'</a>';
            }
            if ($bill !== null) {
                $description = '<a href="'.e(route('admin.billing.therapist-bills.show', $bill)).'" class="text-primary hover:underline">'.$descriptionText.'</a>';
            }
        }
        $amount = '$'.number_format((float) $expense->amount, 2);

        $showUrl = route('admin.expenses.show', $expense);
        $editUrl = route('admin.expenses.edit', $expense);

        $buttons = [ActionButtons::view($showUrl, 'View Expense')];
        if (Gate::allows('update', $expense)) {
            $buttons[] = ActionButtons::edit($editUrl, 'Edit Expense');
        }
        if (Gate::allows('delete', $expense)) {
            $buttons[] = ActionButtons::delete(
                route('admin.expenses.destroy', $expense),
                'Delete Expense',
                'Delete expense?',
                'This action cannot be undone.',
            );
        }
        $actions = ActionButtons::wrap(...$buttons);

        return [
            $date,
            $categoryName,
            $vendor,
            $description,
            $amount,
            $actions,
        ];
    }
}
