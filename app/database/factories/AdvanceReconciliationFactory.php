<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdvanceReconciliation;
use App\Models\BillingSchedule;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvanceReconciliation>
 */
class AdvanceReconciliationFactory extends Factory
{
    protected $model = AdvanceReconciliation::class;

    public function definition(): array
    {
        return [
            'billing_schedule_id' => BillingSchedule::factory(),
            'school_id' => School::factory(),
            'reconciled_period_start' => '2026-05-01',
            'reconciled_period_end' => '2026-05-31',
            'source_invoice_id' => null,
            'credit_note_ledger_entry_id' => null,
            'settlement_invoice_id' => null,
            'net_amount' => 0,
            'reconciled_at' => now(),
            'recorded_by_id' => null,
        ];
    }
}
