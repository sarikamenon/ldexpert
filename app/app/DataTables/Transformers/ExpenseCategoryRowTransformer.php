<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

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
        $iconEdit = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        $iconActive = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>';
        $iconInactive = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>';

        $toggleClass = $isActive ? 'bg-warning text-warning-foreground hover:bg-warning/90' : 'bg-success text-success-foreground hover:bg-success/90';
        $toggleTitle = $isActive ? 'Deactivate Category' : 'Activate Category';
        $toggleAria = $isActive ? 'Deactivate category '.e($category->name) : 'Activate category '.e($category->name);
        $toggleIcon = $isActive ? $iconActive : $iconInactive;
        $toggleBtn = '<button type="button" class="toggle-expense-category-status inline-flex items-center justify-center w-8 h-8 rounded transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 disabled:pointer-events-none '.$toggleClass.'" data-category-id="'.(int) $category->id.'" data-status="'.($isActive ? 'active' : 'inactive').'" data-toggle-url="'.e($toggleUrl).'" title="'.e($toggleTitle).'" aria-label="'.e($toggleAria).'">'.$toggleIcon.'</button>';

        $actions = '<div class="flex items-center justify-end gap-2">'
            .'<a href="'.e($editUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="Edit Category" aria-label="Edit category '.e($category->name).'">'.$iconEdit.'</a>'
            .$toggleBtn
            .'</div>';

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
