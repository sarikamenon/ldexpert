<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The invoice school_state snapshot must match its source, schools.state,
     * which is varchar(50) to hold international (3-letter) codes. It was left at
     * varchar(2), so snapshotting an international school overflowed the column.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('school_state', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('school_state', 2)->nullable()->change();
        });
    }
};
