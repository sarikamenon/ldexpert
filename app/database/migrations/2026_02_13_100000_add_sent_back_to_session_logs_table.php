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
            $table->dateTime('sent_back_at')->nullable()->after('approved_by_id');
            $table->foreignId('sent_back_by_id')
                ->nullable()
                ->after('sent_back_at')
                ->constrained('users')
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
            $table->dropForeign(['sent_back_by_id']);
            $table->dropColumn(['sent_back_at', 'sent_back_by_id']);
        });
    }
};
