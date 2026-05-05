<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\CreateExpenseDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\ExpenseFilterDTO;
use App\DTOs\UpdateExpenseDTO;
use App\Enums\TransactionType;
use App\Models\Expense;
use App\Models\LedgerEntry;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

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
     * Create a new custom expense and write the corresponding ledger entry
     * against the Business account (User#business_account_user_id).
     */
    public function createExpense(CreateExpenseDTO $dto): Expense
    {
        return DB::transaction(function () use ($dto): Expense {
            $expense = Expense::create($dto->toArray());

            $businessUser = $this->resolveBusinessUser();

            $this->ledger->createEntry(
                ledgerableType: User::class,
                ledgerableId: $businessUser->id,
                type: TransactionType::EXPENSE,
                amount: (float) $expense->amount,
                recordedAt: LedgerService::resolveDateOnlyRecordedAt($expense->expense_date),
                referenceType: Expense::class,
                referenceId: $expense->id,
                notes: $expense->description,
                recordedById: $dto->createdById,
            );

            return $expense->load('category', 'createdBy');
        });
    }

    /**
     * Create an expense linked to a source model (e.g. a therapist bill payment).
     * These are already represented in the ledger as payment_made against the therapist;
     * no additional ledger entry is created to avoid double-counting.
     */
    public function createExpenseFromSource(CreateExpenseDTO $dto, Model $source): Expense
    {
        return DB::transaction(function () use ($dto, $source): Expense {
            $data = $dto->toArray();
            $data['source_type'] = $source::class;
            $data['source_id'] = $source->getKey();

            $expense = Expense::create($data);

            return $expense->load('category', 'createdBy');
        });
    }

    /**
     * Update an existing custom expense.
     * When amount or date changes the ledger entry is updated and the Business
     * account chain is recomputed. Non-financial changes only sync the notes.
     */
    public function updateExpense(Expense $expense, UpdateExpenseDTO $dto): Expense
    {
        return DB::transaction(function () use ($expense, $dto): Expense {
            $oldAmount = (float) $expense->amount;
            $oldDateStr = $expense->getRawOriginal('expense_date');

            $expense->update($dto->toArray());

            $entry = LedgerEntry::forReference($expense)->first();

            if ($entry === null) {
                return $expense->load('category', 'createdBy');
            }

            $financiallyChanged = (float) $dto->amount !== $oldAmount || $dto->expenseDate !== $oldDateStr;

            if ($financiallyChanged) {
                $oldRecordedAt = $entry->recorded_at;

                $entry->amount = (string) $dto->amount;
                $entry->recorded_at = LedgerService::resolveDateOnlyRecordedAt($dto->expenseDate);
                $entry->notes = $dto->description;
                $entry->save();

                /** @var class-string $ledgerableType */
                $ledgerableType = $entry->ledgerable_type;
                $this->ledger->recomputeChainFrom($ledgerableType, (int) $entry->ledgerable_id, $oldRecordedAt);
            } else {
                $entry->notes = $dto->description;
                $entry->save();
            }

            return $expense->load('category', 'createdBy');
        });
    }

    /**
     * Soft-delete a custom expense, remove its ledger entry, and recompute
     * the Business account chain from the deleted entry's recorded_at.
     */
    public function deleteExpense(Expense $expense): bool
    {
        return DB::transaction(function () use ($expense): bool {
            $entry = LedgerEntry::forReference($expense)->first();

            $expense->delete();

            if ($entry !== null) {
                /** @var class-string $ledgerableType */
                $ledgerableType = $entry->ledgerable_type;
                $ledgerableId = (int) $entry->ledgerable_id;
                $recordedAt = $entry->recorded_at;

                $entry->delete();

                $this->ledger->recomputeChainFrom($ledgerableType, $ledgerableId, $recordedAt);
            }

            return true;
        });
    }

    private function resolveBusinessUser(): User
    {
        $id = (int) config('finance.business_account_user_id', 1);

        /** @var User */
        return User::withTrashed()->findOrFail($id);
    }
}
