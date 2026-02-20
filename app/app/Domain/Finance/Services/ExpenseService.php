<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\DTOs\CreateExpenseDTO;
use App\DTOs\UpdateExpenseDTO;
use App\Models\Expense;
use App\Models\LedgerEntry;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
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
            LedgerEntry::where('reference_type', Expense::class)
                ->where('reference_id', $expense->id)
                ->delete();

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
