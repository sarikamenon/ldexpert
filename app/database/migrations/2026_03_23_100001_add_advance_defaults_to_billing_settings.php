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
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->string('advance_default_frequency', 20)->default('semi_monthly')->after('default_auto_send');
            $table->string('advance_default_generation_day_type', 20)->default('day_of_week')->after('advance_default_frequency');
            $table->unsignedTinyInteger('advance_default_generation_day_of_week')->default(2)->after('advance_default_generation_day_type');
            $table->unsignedInteger('advance_default_min_grace_days')->default(2)->after('advance_default_generation_day_of_week');
            $table->unsignedInteger('advance_default_payment_terms_days')->default(30)->after('advance_default_min_grace_days');
            $table->boolean('advance_default_auto_generate')->default(true)->after('advance_default_payment_terms_days');
            $table->boolean('advance_default_auto_send')->default(false)->after('advance_default_auto_generate');
        });

        // Fill existing row with defaults
        DB::table('billing_settings')->update([
            'advance_default_frequency' => 'semi_monthly',
            'advance_default_generation_day_type' => 'day_of_week',
            'advance_default_generation_day_of_week' => 2,
            'advance_default_min_grace_days' => 2,
            'advance_default_payment_terms_days' => 30,
            'advance_default_auto_generate' => true,
            'advance_default_auto_send' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn([
                'advance_default_frequency',
                'advance_default_generation_day_type',
                'advance_default_generation_day_of_week',
                'advance_default_min_grace_days',
                'advance_default_payment_terms_days',
                'advance_default_auto_generate',
                'advance_default_auto_send',
            ]);
        });
    }
};
