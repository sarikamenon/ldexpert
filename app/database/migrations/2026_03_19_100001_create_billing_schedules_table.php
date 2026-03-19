<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedulable_type');
            $table->unsignedBigInteger('schedulable_id');
            $table->string('schedule_type', 30);
            $table->string('billing_mode', 20)->default('standard');
            $table->string('frequency', 20)->default('semi_monthly');
            $table->string('generation_day_type', 20)->default('day_of_week');
            $table->tinyInteger('generation_day_of_week')->nullable();
            $table->unsignedInteger('generation_delay_days')->nullable();
            $table->unsignedInteger('min_grace_days')->default(2);
            $table->unsignedInteger('payment_terms_days')->default(30);
            $table->boolean('auto_generate')->default(true);
            $table->boolean('auto_send')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->date('last_period_end')->nullable();
            $table->date('next_run_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['schedulable_type', 'schedulable_id', 'schedule_type'],
                'billing_schedules_entity_type_unique'
            );
            $table->index(['is_active', 'next_run_at']);
            $table->index('schedule_type');
            $table->index('billing_mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_schedules');
    }
};
