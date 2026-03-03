<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\Expense;
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
        $vendor = e($expense->vendor_payee ?? '—');
        $description = e(Str::limit($expense->description ?? '—', 50));
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
