<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('therapist_bills', function (Blueprint $table) {
            $table->id();

            // Therapist reference (for filtering, snapshot data used for display)
            $table->foreignId('therapist_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Bill identification
            $table->string('bill_number')->unique();
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->date('bill_date');

            // Status
            $table->string('status', 32)->default('draft'); // draft, sent, paid

            // Financial totals
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('adjustments_total', 10, 2)->default(0);
            $table->decimal('total_due', 10, 2)->default(0);
            $table->date('due_date');

            // Therapist Snapshot Fields (copied at bill creation)
            $table->string('therapist_name')->nullable();
            $table->string('therapist_email')->nullable();
            $table->string('therapist_phone', 32)->nullable();
            $table->text('therapist_address')->nullable();

            // Company Snapshot Fields (copied at bill creation)
            $table->string('company_name')->nullable();
            $table->text('company_address')->nullable();
            $table->string('company_phone', 32)->nullable();
            $table->string('company_email')->nullable();
            $table->string('company_tax_id')->nullable();

            // Sending information
            $table->dateTime('sent_at')->nullable();
            $table->foreignId('sent_by_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('therapist_id');
            $table->index('status');
            $table->index('bill_number');
            $table->index(['billing_period_start', 'billing_period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('therapist_bills');
    }
};
