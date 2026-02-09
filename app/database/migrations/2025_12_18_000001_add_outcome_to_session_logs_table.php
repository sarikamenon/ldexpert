<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('session_logs', function (Blueprint $table) {
            $table->string('outcome', 64)
                ->default('service_delivered')
                ->after('delivery_mode')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_logs', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });
    }
};
