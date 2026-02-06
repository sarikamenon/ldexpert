<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Map legacy SessionOutcome values to new enum values.
     * Run the migrations.
     */
    public function up(): void
    {
        $map = [
            'service_delivered' => 'services_administered',
            'no_show_student' => 'no_show',
            'no_show_therapist' => 'non_billable_cancellation_provider',
            'technical_issue' => 'non_billable_cancellation_provider',
        ];

        foreach ($map as $old => $new) {
            DB::table('session_logs')->where('outcome', $old)->update(['outcome' => $new]);
        }
    }

    /**
     * Reverse the migrations (maps new enum values back to legacy).
     */
    public function down(): void
    {
        $map = [
            'services_administered' => 'service_delivered',
            'no_show' => 'no_show_student',
            'billable_cancellation' => 'no_show_student',
            'non_billable_cancellation_client' => 'technical_issue',
            'non_billable_cancellation_provider' => 'no_show_therapist',
        ];

        foreach ($map as $new => $old) {
            DB::table('session_logs')->where('outcome', $new)->update(['outcome' => $old]);
        }
    }
};
