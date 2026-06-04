<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            // Advance invoices only: marks a schedule already billed on an advance
            // invoice so the generator never re-charges it. Cleared on detach.
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('school_id')
                ->constrained('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
