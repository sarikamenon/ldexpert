<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // Change adjusted_minutes from unsigned integer to signed integer
        // Using raw SQL to ensure proper conversion
        DB::statement('ALTER TABLE `service_support_agreements` MODIFY COLUMN `adjusted_minutes` INT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        // Revert back to unsigned integer
        DB::statement('ALTER TABLE `service_support_agreements` MODIFY COLUMN `adjusted_minutes` INT UNSIGNED NULL');
    }
};
