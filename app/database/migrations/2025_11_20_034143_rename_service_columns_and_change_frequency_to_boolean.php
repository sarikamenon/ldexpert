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
        // Add new columns
        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('is_direct_service')->default(true)->after('description');
            $table->boolean('is_group_service')->default(false)->after('is_direct_service');
            $table->boolean('is_frequency_service')->default(false)->after('is_group_service');
        });

        // Copy data from old columns to new columns
        DB::statement('UPDATE services SET is_direct_service = direct_service');
        DB::statement('UPDATE services SET is_group_service = group_service');
        DB::statement('UPDATE services SET is_frequency_service = CASE WHEN frequency IS NOT NULL THEN 1 ELSE 0 END');

        // Drop old columns
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['direct_service', 'group_service', 'frequency']);
        });
    }

    public function down(): void
    {
        // Add old columns back
        Schema::table('services', function (Blueprint $table): void {
            $table->boolean('direct_service')->default(true)->after('description');
            $table->boolean('group_service')->default(false)->after('direct_service');
            $table->string('frequency', 32)->nullable()->default('adhoc')->after('group_service');
        });

        // Copy data back
        DB::statement('UPDATE services SET direct_service = is_direct_service');
        DB::statement('UPDATE services SET group_service = is_group_service');
        DB::statement("UPDATE services SET frequency = CASE WHEN is_frequency_service = 1 THEN 'adhoc' ELSE NULL END");

        // Drop new columns
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['is_direct_service', 'is_group_service', 'is_frequency_service']);
        });
    }
};
