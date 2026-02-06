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
        // Update status enum values in existing records first
        \DB::table('session_logs')
            ->where('status', 'finalized')
            ->update(['status' => 'approved']);

        // For SQLite compatibility, we need to drop and recreate columns
        // First, add new columns
        Schema::table('session_logs', function (Blueprint $table) {
            $table->dateTime('approved_at')->nullable()->after('submitted_by_id');
            $table->foreignId('approved_by_id')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        // Copy data from old columns to new columns
        \DB::statement('UPDATE session_logs SET approved_at = finalized_at, approved_by_id = finalized_by_id WHERE finalized_at IS NOT NULL');

        // Drop old columns
        Schema::table('session_logs', function (Blueprint $table) {
            $table->dropForeign(['finalized_by_id']);
            $table->dropColumn(['finalized_at', 'finalized_by_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status values
        \DB::table('session_logs')
            ->where('status', 'approved')
            ->update(['status' => 'finalized']);

        // Add back old columns
        Schema::table('session_logs', function (Blueprint $table) {
            $table->dateTime('finalized_at')->nullable()->after('submitted_by_id');
            $table->foreignId('finalized_by_id')
                ->nullable()
                ->after('finalized_at')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        // Copy data back
        \DB::statement('UPDATE session_logs SET finalized_at = approved_at, finalized_by_id = approved_by_id WHERE approved_at IS NOT NULL');

        // Drop new columns
        Schema::table('session_logs', function (Blueprint $table) {
            $table->dropForeign(['approved_by_id']);
            $table->dropColumn(['approved_at', 'approved_by_id']);
        });
    }
};
