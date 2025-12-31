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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // School reference (for filtering, snapshot data used for display)
            $table->foreignId('school_id')
                ->nullable()
                ->constrained('schools')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Invoice identification
            $table->string('invoice_number')->unique();
            $table->date('billing_period_start');
            $table->date('billing_period_end');

            // Status
            $table->string('status', 32)->default('draft'); // draft, sent, paid, voided

            // Financial totals
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->date('due_date');

            // School Snapshot Fields (copied at invoice creation)
            $table->string('school_name')->nullable();
            $table->string('school_display_name')->nullable();
            $table->text('school_address')->nullable();
            $table->string('school_state', 2)->nullable();
            $table->string('school_contact_first_name')->nullable();
            $table->string('school_contact_last_name')->nullable();
            $table->string('school_contact_phone', 32)->nullable();
            $table->string('school_contact_email')->nullable();
            $table->string('school_invoice_email')->nullable();

            // Company Snapshot Fields (copied at invoice creation)
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
            $table->index('school_id');
            $table->index('status');
            $table->index('invoice_number');
            $table->index(['billing_period_start', 'billing_period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
