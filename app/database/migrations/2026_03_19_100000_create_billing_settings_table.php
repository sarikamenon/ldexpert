<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_frequency', 20)->default('semi_monthly');
            $table->string('default_generation_day_type', 20)->default('day_of_week');
            $table->tinyInteger('default_generation_day_of_week')->default(2);
            $table->unsignedInteger('default_min_grace_days')->default(2);
            $table->unsignedInteger('default_payment_terms_days')->default(30);
            $table->boolean('default_auto_generate')->default(true);
            $table->boolean('default_auto_send')->default(false);
            $table->unsignedInteger('reminder_days_before_due')->default(5);
            $table->unsignedInteger('reminder_days_after_due')->default(3);
            $table->unsignedInteger('reminder_overdue_repeat_days')->default(7);
            $table->unsignedInteger('max_overdue_reminders')->default(3);
            $table->timestamp('updated_at')->nullable();
        });

        DB::table('billing_settings')->insert([
            'default_frequency' => 'semi_monthly',
            'default_generation_day_type' => 'day_of_week',
            'default_generation_day_of_week' => 2,
            'default_min_grace_days' => 2,
            'default_payment_terms_days' => 30,
            'default_auto_generate' => true,
            'default_auto_send' => false,
            'reminder_days_before_due' => 5,
            'reminder_days_after_due' => 3,
            'reminder_overdue_repeat_days' => 7,
            'max_overdue_reminders' => 3,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
