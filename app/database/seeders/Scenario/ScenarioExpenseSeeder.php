<?php

declare(strict_types=1);

namespace Database\Seeders\Scenario;

use App\Enums\Role;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

final class ScenarioExpenseSeeder extends Seeder
{
    private const REFERENCE_PREFIX = 'SCEN-EXP-';

    /**
     * Create expenses totaling at least 10% of invoice revenue (scenario invoices).
     */
    public function run(): void
    {
        $revenue = (float) Invoice::query()->sum('total');
        $targetExpense = $revenue * 0.10;
        if ($targetExpense <= 0) {
            $this->command?->warn('ScenarioExpenseSeeder: no invoice revenue found.');

            return;
        }

        $admin = User::query()->where('role', Role::ADMIN->value)->first();
        $categories = ExpenseCategory::query()->active()->orderBy('id')->get();
        if ($categories->isEmpty()) {
            return;
        }

        $created = 0;
        $total = 0.0;
        $perCategory = $targetExpense / max(1, $categories->count());
        $start = Carbon::create(2025, 8, 1);
        $end = Carbon::create(2026, 7, 31);

        foreach ($categories as $index => $category) {
            $remaining = $perCategory;
            $n = 0;
            while ($remaining > 1 && $n < 20) {
                $amount = round(min($remaining, (float) random_int(50, 500)), 2);
                $date = Carbon::createFromTimestamp(random_int($start->timestamp, $end->timestamp));
                $ref = self::REFERENCE_PREFIX.$category->id.'-'.($index * 10 + $n + 1);

                Expense::query()->create([
                    'expense_category_id' => $category->id,
                    'expense_date' => $date->format('Y-m-d'),
                    'amount' => $amount,
                    'vendor_payee' => 'Scenario vendor '.($n + 1),
                    'description' => 'Scenario 2025 expense.',
                    'reference' => $ref,
                    'created_by_id' => $admin?->id,
                ]);
                $total += $amount;
                $remaining -= $amount;
                $created++;
                $n++;
            }
        }

        if ($total < $targetExpense) {
            $extra = round($targetExpense - $total, 2);
            $cat = $categories->first();
            Expense::query()->create([
                'expense_category_id' => $cat->id,
                'expense_date' => $end->format('Y-m-d'),
                'amount' => $extra,
                'vendor_payee' => 'Scenario adjustment',
                'description' => 'Scenario 2025 expense to reach 10% of revenue.',
                'reference' => self::REFERENCE_PREFIX.'adj',
                'created_by_id' => $admin?->id,
            ]);
            $total += $extra;
            $created++;
        }
    }
}
