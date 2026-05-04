<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('frequency', 32)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Keep nullable — reverting to NOT NULL would fail if null values exist
        Schema::table('services', function (Blueprint $table): void {
            $table->string('frequency', 32)->nullable()->default('adhoc')->change();
        });
    }
};
