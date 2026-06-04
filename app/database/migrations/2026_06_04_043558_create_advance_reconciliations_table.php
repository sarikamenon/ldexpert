<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_schedule_id')->constrained('billing_schedules')->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->date('reconciled_period_start');
            $table->date('reconciled_period_end');
            $table->foreignId('source_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('credit_note_ledger_entry_id')->nullable()->constrained('ledger_entries')->nullOnDelete();
            $table->foreignId('settlement_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->timestamp('reconciled_at');
            $table->foreignId('recorded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // A period is reconciled at most once per schedule (idempotency guard).
            $table->unique(
                ['billing_schedule_id', 'reconciled_period_start', 'reconciled_period_end'],
                'advance_reconciliations_schedule_period_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_reconciliations');
    }
};
