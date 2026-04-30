<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE users u
            INNER JOIN therapist_profiles tp ON tp.user_id = u.id
            SET u.timezone = tp.timezone
            WHERE tp.timezone IS NOT NULL
              AND tp.timezone <> ""
              AND u.timezone <> tp.timezone
        ');

        DB::statement('
            UPDATE users u
            INNER JOIN student_profiles sp ON sp.user_id = u.id
            SET u.timezone = sp.timezone
            WHERE sp.timezone IS NOT NULL
              AND sp.timezone <> ""
              AND u.timezone <> sp.timezone
        ');
    }

    public function down(): void
    {
        // No-op: restoring prior (often default "UTC") values would be lossy
        // and would re-introduce the timezone-mismatch bug this migration fixes.
    }
};
