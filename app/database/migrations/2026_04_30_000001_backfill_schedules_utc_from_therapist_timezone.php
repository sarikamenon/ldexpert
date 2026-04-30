<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pre-fix, ScheduleService called parseUserLocalToUtc() with
        // $user->timezone (default "UTC"), so user-local times were stored
        // as if they were already UTC. Re-interpret each row in the
        // therapist's actual zone and convert to true UTC.
        //
        // Conversion is done in PHP (Carbon) rather than MySQL CONVERT_TZ
        // because some environments do not have the named-zone tables
        // (mysql.time_zone_name) loaded.

        if (! Schema::hasTable('schedules_timezone_backfill_backup')) {
            DB::statement('
                CREATE TABLE schedules_timezone_backfill_backup AS
                SELECT
                    s.id,
                    s.schedule_date,
                    s.start_time,
                    s.end_time,
                    tp.timezone AS therapist_timezone
                FROM schedules s
                LEFT JOIN therapist_profiles tp ON tp.user_id = s.therapist_id
            ');

            DB::statement('ALTER TABLE schedules_timezone_backfill_backup ADD PRIMARY KEY (id)');
        }

        DB::table('schedules_timezone_backfill_backup')
            ->whereNotNull('therapist_timezone')
            ->where('therapist_timezone', '<>', '')
            ->where('therapist_timezone', '<>', 'UTC')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $tz = (string) $row->therapist_timezone;

                    if (! in_array($tz, timezone_identifiers_list(), true)) {
                        continue;
                    }

                    $date = $row->schedule_date instanceof \DateTimeInterface
                        ? $row->schedule_date->format('Y-m-d')
                        : substr((string) $row->schedule_date, 0, 10);

                    $startUtc = CarbonImmutable::parse($date.' '.$row->start_time, $tz)
                        ->setTimezone('UTC');
                    $endUtc = CarbonImmutable::parse($date.' '.$row->end_time, $tz)
                        ->setTimezone('UTC');

                    DB::table('schedules')
                        ->where('id', $row->id)
                        ->update([
                            'schedule_date' => $startUtc->toDateString(),
                            'start_time' => $startUtc->toTimeString(),
                            'end_time' => $endUtc->toTimeString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedules_timezone_backfill_backup')) {
            return;
        }

        DB::statement('
            UPDATE schedules s
            INNER JOIN schedules_timezone_backfill_backup b ON b.id = s.id
            SET
                s.schedule_date = b.schedule_date,
                s.start_time = b.start_time,
                s.end_time = b.end_time
        ');

        Schema::drop('schedules_timezone_backfill_backup');
    }
};
