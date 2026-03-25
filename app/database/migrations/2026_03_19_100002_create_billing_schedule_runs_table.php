<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_schedule_id')->constrained('billing_schedules')->cascadeOnDelete();
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->date('generation_date');
            $table->string('status', 30);
            $table->unsignedInteger('sessions_found')->default(0);
            $table->unsignedInteger('sessions_from_prior_periods')->default(0);
            $table->unsignedInteger('adjustments_count')->default(0);
            $table->decimal('adjustment_total', 12, 2)->default(0);
            $table->decimal('carry_forward_amount', 12, 2)->default(0);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('therapist_bill_id')->nullable()->constrained('therapist_bills')->nullOnDelete();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->boolean('auto_sent')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('billing_schedule_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_schedule_runs');
    }
};
