<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('therapist_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('student_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('ssa_id')
                ->nullable()
                ->constrained('service_support_agreements')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('school_id')
                ->nullable()
                ->constrained('schools')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('parent_schedule_id')
                ->nullable()
                ->constrained('schedules')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->date('schedule_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('recurrence_type', 32)->default('none'); // none, daily, weekly, bi_weekly, monthly
            $table->date('recurrence_end_date')->nullable();
            $table->boolean('is_group')->default(false);
            $table->string('recurring_batch_number', 32)->nullable();
            $table->string('group_batch_number', 32)->nullable();
            $table->string('status', 32)->default('scheduled'); // scheduled, completed, cancelled
            $table->string('billing_status', 32)->default('pending'); // pending, billed, not_billable, waived
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['therapist_id', 'schedule_date', 'start_time']);
            $table->index('student_id');
            $table->index('ssa_id');
            $table->index('parent_schedule_id');
            $table->index('recurring_batch_number');
            $table->index('group_batch_number');
            $table->index('is_group');
            $table->index('status');
            $table->index('billing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};

