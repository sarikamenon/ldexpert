<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('therapist_profiles', 'max_weekly_hours')) {
            Schema::table('therapist_profiles', function (Blueprint $table): void {
                $table->unsignedSmallInteger('max_weekly_hours')
                    ->default(40)
                    ->after('manager_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('therapist_profiles', 'max_weekly_hours')) {
            Schema::table('therapist_profiles', function (Blueprint $table): void {
                $table->dropColumn('max_weekly_hours');
            });
        }
    }
};
