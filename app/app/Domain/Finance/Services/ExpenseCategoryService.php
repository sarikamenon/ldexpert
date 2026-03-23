<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ExpenseCategoryFilterDTO;
use App\Models\ExpenseCategory;
use Illuminate\Support\Collection;

final class ExpenseCategoryService
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, ExpenseCategory>}
     */
    public function listForDataTables(ExpenseCategoryFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = ExpenseCategory::query()->withCount('expenses');

        if ($filters->search !== null && $filters->search !== '') {
            $baseQuery->where('name', 'like', '%'.$filters->search.'%');
        }
        if ($filters->status === 'active') {
            $baseQuery->where('is_active', true);
        } elseif ($filters->status === 'inactive') {
            $baseQuery->where('is_active', false);
        }

        $recordsTotal = (clone $baseQuery)->count('expense_categories.id');

        if ($params->searchValue) {
            $baseQuery->where('name', 'like', '%'.$params->searchValue.'%');
        }
        $recordsFiltered = (clone $baseQuery)->count('expense_categories.id');

        $orderColumn = $params->orderColumn ?? 'name';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, ExpenseCategory> $rows */
        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }
}
