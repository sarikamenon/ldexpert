<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Set session_logs.outcome column default to the new enum value.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE session_logs MODIFY outcome VARCHAR(64) NOT NULL DEFAULT 'services_administered'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE session_logs MODIFY outcome VARCHAR(64) NOT NULL DEFAULT 'service_delivered'");
    }
};
