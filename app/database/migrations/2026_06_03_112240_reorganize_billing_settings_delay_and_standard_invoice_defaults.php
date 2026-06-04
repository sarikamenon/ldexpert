<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename grace columns to delay (kept in DB, simply no longer floors generation).
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->renameColumn('default_min_grace_days', 'default_delay_days');
            $table->renameColumn('advance_default_min_grace_days', 'advance_default_delay_days');
        });

        // New Standard Invoice Defaults (Postpaid School) section columns.
        // Column defaults populate the existing singleton row automatically.
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->string('standard_default_frequency', 20)->default('semi_monthly')->after('advance_default_auto_send');
            $table->string('standard_default_generation_day_type', 20)->default('day_of_week')->after('standard_default_frequency');
            $table->unsignedTinyInteger('standard_default_generation_day_of_week')->default(2)->after('standard_default_generation_day_type');
            $table->unsignedInteger('standard_default_delay_days')->default(2)->after('standard_default_generation_day_of_week');
            $table->unsignedInteger('standard_default_payment_terms_days')->default(30)->after('standard_default_delay_days');
            $table->boolean('standard_default_auto_generate')->default(true)->after('standard_default_payment_terms_days');
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn([
                'standard_default_frequency',
                'standard_default_generation_day_type',
                'standard_default_generation_day_of_week',
                'standard_default_delay_days',
                'standard_default_payment_terms_days',
                'standard_default_auto_generate',
            ]);
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->renameColumn('default_delay_days', 'default_min_grace_days');
            $table->renameColumn('advance_default_delay_days', 'advance_default_min_grace_days');
        });
    }
};
