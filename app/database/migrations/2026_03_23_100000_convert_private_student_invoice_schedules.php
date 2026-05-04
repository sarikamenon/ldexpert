<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $count = DB::table('billing_schedules')
            ->where('schedule_type', 'private_student_invoice')
            ->count();

        if ($count === 0) {
            return;
        }

        // Convert private_student_invoice schedules to school_invoice,
        // remapping schedulable from User (student) to their School
        DB::statement("
            UPDATE billing_schedules bs
            INNER JOIN student_profiles sp ON sp.user_id = bs.schedulable_id
            SET bs.schedule_type = 'school_invoice',
                bs.schedulable_type = 'App\\\\Models\\\\School',
                bs.schedulable_id = sp.school_id
            WHERE bs.schedule_type = 'private_student_invoice'
              AND sp.school_id IS NOT NULL
        ");

        // Delete any that couldn't be mapped (no student_profile or no school)
        DB::table('billing_schedules')
            ->where('schedule_type', 'private_student_invoice')
            ->delete();
    }

    public function down(): void
    {
        // Not reversible — data migration only
    }
};
