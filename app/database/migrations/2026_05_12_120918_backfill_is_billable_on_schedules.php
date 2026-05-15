<?php

declare(strict_types=1);

use App\Models\Schedule;
use App\Models\School;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // is_billable defaults to true via the column migration. We only need
        // to flip schedules whose school has non_billable_scheduling = true.
        // Iterate schools (small table) and bulk-update each school's schedules
        // in one query. withTrashed() is used on both ends so soft-deleted
        // rows stay coherent if they are ever restored.
        School::withTrashed()
            ->where('non_billable_scheduling', true)
            ->select(['id'])
            ->chunkById(200, function ($schools): void {
                Schedule::withTrashed()
                    ->whereIn('school_id', $schools->pluck('id'))
                    ->update(['is_billable' => false]);
            });
    }

    public function down(): void
    {
        // No-op: the column itself is dropped by the prior migration's down().
        // The original signal (schools.non_billable_scheduling) remains
        // available to re-run up() if needed.
    }
};
