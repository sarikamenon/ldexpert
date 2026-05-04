<?php

declare(strict_types=1);

use Database\Seeders\ProductionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        Artisan::call('db:seed', [
            '--class' => ProductionSeeder::class,
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        // No automatic rollback for baseline production data.
    }
};
