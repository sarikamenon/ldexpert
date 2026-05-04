<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\CreateExpenseDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ExpenseFilterDTO;
use App\DTOs\UpdateExpenseDTO;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\TherapistBillPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, Expense>}
     */
    public function listForDataTables(ExpenseFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = Expense::query()->with(['category', 'createdBy']);
        // @phpstan-ignore argument.type
        $baseQuery->with(['source' => function (MorphTo $morphTo): void {
            $morphTo->morphWith([
                TherapistBillPayment::class => ['therapistBill.therapist'],
            ]);
        }]);

        if ($filters->categoryId !== null) {
            $baseQuery->where('expense_category_id', $filters->categoryId);
        }
        if ($filters->dateFrom !== null) {
            $baseQuery->where('expense_date', '>=', $filters->dateFrom);
        }
        if ($filters->dateTo !== null) {
            $baseQuery->where('expense_date', '<=', $filters->dateTo);
        }
        if ($filters->search !== null && $filters->search !== '') {
            $this->applyExpenseSearch($baseQuery, $filters->search);
        }

        $recordsTotal = (clone $baseQuery)->count('expenses.id');

        if ($params->searchValue) {
            $this->applyExpenseSearch($baseQuery, $params->searchValue);
        }
        $recordsFiltered = (clone $baseQuery)->count('expenses.id');

        $orderColumn = $params->orderColumn ?? 'expense_date';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, Expense> $rows */
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

    public function getTotalAmountForFilters(ExpenseFilterDTO $filters): float
    {
        $query = Expense::query();
        if ($filters->categoryId !== null) {
            $query->where('expense_category_id', $filters->categoryId);
        }
        if ($filters->dateFrom !== null) {
            $query->where('expense_date', '>=', $filters->dateFrom);
        }
        if ($filters->dateTo !== null) {
            $query->where('expense_date', '<=', $filters->dateTo);
        }
        if ($filters->search !== null && $filters->search !== '') {
            $this->applyExpenseSearch($query, $filters->search);
        }

        return (float) $query->sum('amount');
    }

    /**
     * @param  Builder<Expense>  $query
     */
    private function applyExpenseSearch(Builder $query, string $search): void
    {
        $query->where(function (Builder $q) use ($search) {
            $q->where('vendor_payee', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%");
        });
    }

    /**
     * Create a new expense.
     */
    public function createExpense(CreateExpenseDTO $dto): Expense
    {
        return DB::transaction(function () use ($dto) {
            // Create the expense record
            $expense = Expense::create($dto->toArray());

            // Create ledger entry (optional - expenses can be tracked separately)
            // Uncomment if you want to track expenses in ledger
            // $this->createLedgerEntry($expense);

            return $expense->load('category', 'createdBy');
        });
    }

    /**
     * Create an expense linked to a source model (e.g. a therapist bill payment).
     * The source polymorphic link makes the expense non-editable via the admin UI.
     */
    public function createExpenseFromSource(CreateExpenseDTO $dto, Model $source): Expense
    {
        return DB::transaction(function () use ($dto, $source) {
            $data = $dto->toArray();
            $data['source_type'] = $source::class;
            $data['source_id'] = $source->getKey();

            $expense = Expense::create($data);

            return $expense->load('category', 'createdBy');
        });
    }

    /**
     * Update an existing expense.
     */
    public function updateExpense(Expense $expense, UpdateExpenseDTO $dto): Expense
    {
        return DB::transaction(function () use ($expense, $dto) {
            // Update the expense record
            $expense->update($dto->toArray());

            return $expense->load('category', 'createdBy');
        });
    }

    /**
     * Delete an expense.
     */
    public function deleteExpense(Expense $expense): bool
    {
        return DB::transaction(function () use ($expense) {
            // Soft delete the expense
            $expense->delete();

            // Delete associated ledger entries if any
            LedgerEntry::forReference($expense)->delete();

            return true;
        });
    }

    /**
     * Create a ledger entry for the expense (optional).
     */
    protected function createLedgerEntry(Expense $expense): void
    {
        // This would require determining which ledger (entity) to charge the expense to
        // For now, expenses are tracked separately without ledger entries
        // This can be implemented later if needed
    }
}
