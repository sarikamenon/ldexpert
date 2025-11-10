<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('db:seed', [
            '--class' => Database\Seeders\TherapistSeeder::class,
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        // Intentionally left blank; seeded data is not automatically rolled back
    }
};
