<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_schedules', function (Blueprint $table) {
            $table->date('billing_start_date')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('billing_schedules', function (Blueprint $table) {
            $table->dropColumn('billing_start_date');
        });
    }
};
