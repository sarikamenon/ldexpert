<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\ExpenseCategory;

final class ExpenseCategoryRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(ExpenseCategory $category): array
    {
        $name = e($category->name);
        $slug = '<code class="text-xs bg-background/subtle px-2 py-1 rounded">'.e($category->slug).'</code>';
        $isActive = (bool) $category->is_active;
        $statusBadge = $isActive
            ? '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-success/10 text-success border border-success/20">Active</span>'
            : '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-background/subtle text-foreground border border-border">Inactive</span>';
        $expensesCount = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">'.(int) $category->expenses_count.'</span>';
        $created = $category->created_at ? $category->created_at->format('M d, Y') : '—';

        $editUrl = route('admin.settings.expense-categories.edit', $category);
        $toggleUrl = route('admin.settings.expense-categories.toggle-status', $category);

        $toggleAttrs = ['data-category-id' => (int) $category->id, 'data-status' => $isActive ? 'active' : 'inactive', 'data-toggle-url' => e($toggleUrl), 'class' => 'toggle-expense-category-status'];
        $toggleBtn = $isActive
            ? ActionButtons::deactivate('Deactivate Category', $toggleAttrs)
            : ActionButtons::activate('Activate Category', $toggleAttrs);

        $actions = ActionButtons::wrap(
            ActionButtons::edit($editUrl, 'Edit Category'),
            $toggleBtn,
        );

        return [
            $name,
            $slug,
            $statusBadge,
            $expensesCount,
            e($created),
            $actions,
        ];
    }
}
