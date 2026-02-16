<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExpenseDemoSeeder extends Seeder
{
    private const DEMO_REFERENCE_PREFIX = 'EXP-DEMO-';

    /**
     * Seed demo expenses across existing categories with matching ledger entries.
     */
    public function run(): void
    {
        // Run demo expenses in all non-production environments.
        if (app()->environment('production')) {
            return;
        }

        // Avoid reseeding the same demo expenses.
        if (Expense::where('reference', 'like', self::DEMO_REFERENCE_PREFIX.'%')->exists()) {
            return;
        }

        $categories = ExpenseCategory::query()
            ->active()
            ->orderBy('id')
            ->get();

        if ($categories->isEmpty()) {
            $categories = ExpenseCategory::factory()->count(3)->create();
        }

        /** @var User|null $admin */
        $admin = User::query()->orderBy('id')->first();

        $totalExpenses = 0;

        foreach ($categories as $index => $category) {
            // 5–7 expenses per category to reach ~30–40 total.
            $count = 5 + ($index % 3);

            for ($i = 1; $i <= $count; $i++) {
                $reference = sprintf('%s%02d-%02d', self::DEMO_REFERENCE_PREFIX, $category->id, $i);

                /** @var Expense $expense */
                $expense = Expense::factory()
                    ->for($category, 'category')
                    ->state([
                        'reference' => $reference,
                        'created_by_id' => $admin?->id,
                    ])
                    ->create();

                $this->createExpenseLedgerEntry($expense, $admin);
                $totalExpenses++;
            }
        }
    }

    private function createExpenseLedgerEntry(Expense $expense, ?User $admin): void
    {
        // Track expenses against the user who created them (typically an admin).
        if (! $expense->created_by_id) {
            return;
        }

        $lastEntry = LedgerEntry::where('ledgerable_type', User::class)
            ->where('ledgerable_id', $expense->created_by_id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $previousBalance = $lastEntry ? (float) $lastEntry->balance_after : 0.0;

        // Expense reduces the balance (money going out).
        $newBalance = $previousBalance - (float) $expense->amount;

        LedgerEntry::create([
            'ledgerable_type' => User::class,
            'ledgerable_id' => $expense->created_by_id,
            'transaction_type' => TransactionType::EXPENSE,
            'amount' => (float) $expense->amount,
            'balance_after' => $newBalance,
            'reference_type' => Expense::class,
            'reference_id' => $expense->id,
            'notes' => 'Expense recorded: '.$expense->reference,
            'recorded_by_id' => $admin?->id ?? $expense->created_by_id,
        ]);
    }
}

