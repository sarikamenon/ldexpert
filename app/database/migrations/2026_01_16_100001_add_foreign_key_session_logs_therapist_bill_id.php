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
            $table->foreign('therapist_bill_id')
                ->references('id')
                ->on('therapist_bills')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('session_logs', function (Blueprint $table) {
            $table->dropForeign(['therapist_bill_id']);
        });
    }
};
