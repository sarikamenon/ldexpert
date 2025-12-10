<?php

use Database\Seeders\TherapistSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        if (!app()->environment('production')) {
            Artisan::call('db:seed', [
                '--class' => TherapistSeeder::class,
                '--force' => true,
            ]);
        }
    }

    public function down(): void
    {
        // Seed data intentionally persists; no rollback.
    }
};
