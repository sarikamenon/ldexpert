<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

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
        $iconView = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        $iconEdit = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        $iconTrash = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path></svg>';

        $actions = '<div class="flex items-center justify-end gap-2">'
            .'<a href="'.e($showUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="View Expense" aria-label="View expense #'.(int) $expense->id.'">'.$iconView.'</a>';
        if (Gate::allows('update', $expense)) {
            $actions .= '<a href="'.e($editUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="Edit Expense" aria-label="Edit expense #'.(int) $expense->id.'">'.$iconEdit.'</a>';
        }
        if (Gate::allows('delete', $expense)) {
            $destroyUrl = route('admin.expenses.destroy', $expense);
            $csrf = csrf_token();
            $actions .= '<form method="POST" action="'.e($destroyUrl).'" class="inline expense-delete-form"><input type="hidden" name="_token" value="'.e($csrf).'"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="inline-flex items-center justify-center w-8 h-8 bg-danger text-danger-foreground rounded hover:bg-danger/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="Delete Expense" aria-label="Delete expense #'.(int) $expense->id.'">'.$iconTrash.'</button></form>';
        }
        $actions .= '</div>';

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
