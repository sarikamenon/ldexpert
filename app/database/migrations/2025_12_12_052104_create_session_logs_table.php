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
        Schema::create('session_logs', function (Blueprint $table) {
            $table->id();

            // Core Identification
            $table->foreignId('therapist_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('ssa_id')
                ->constrained('service_support_agreements')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->nullable()
                ->constrained('schools')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Schedule Linkage
            $table->foreignId('schedule_id')
                ->nullable()
                ->constrained('schedules')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Session Details
            $table->date('session_date');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('tho_minutes');
            $table->text('notes');

            // Delivery & Group (Fixed Values)
            $table->string('delivery_mode', 32)->default('virtual');
            $table->boolean('is_group')->default(false);

            // Billing - Therapist Side
            $table->boolean('is_billable_therapist')->default(true);
            $table->foreignId('therapist_contract_id')
                ->nullable()
                ->constrained('therapist_contracts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->char('therapist_rate_type', 1)->nullable(); // H=Hourly, F=Flat
            $table->decimal('therapist_rate_amount', 10, 2)->nullable();
            $table->decimal('therapist_billable_amount', 10, 2)->nullable();
            $table->unsignedBigInteger('therapist_bill_id')->nullable(); // FK to therapist_bills (future table)

            // Billing - School Side
            $table->boolean('is_billable_school')->default(true);
            $table->foreignId('school_contract_id')
                ->nullable()
                ->constrained('school_contracts')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->char('school_rate_type', 1)->nullable(); // H=Hourly, F=Flat
            $table->decimal('school_rate_amount', 10, 2)->nullable();
            $table->decimal('school_invoice_amount', 10, 2)->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable(); // FK to invoices (future table)

            // Rate Override
            $table->boolean('is_rate_override')->default(false);
            $table->text('override_reason')->nullable();

            // Status & Workflow
            $table->string('status', 32)->default('draft'); // draft, submitted, finalized, cancelled
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('submitted_by_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->foreignId('finalized_by_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Additional Fields
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['therapist_id', 'session_date']);
            $table->index('student_id');
            $table->index('ssa_id');
            $table->index('schedule_id');
            $table->index('service_id');
            $table->index('school_id');
            $table->index('status');
            $table->index('session_date');
            $table->index(['therapist_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_logs');
    }
};
