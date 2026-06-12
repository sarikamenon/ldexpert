<?php

declare(strict_types=1);

namespace Database\Seeders\LoadTest;

use App\Enums\ScheduleStatus;
use App\Enums\SSAStatus;
use Carbon\CarbonImmutable;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;

/**
 * Backfills ~10 years of weekly schedules for every active assigned SSA until
 * the scale target of COMPLETED rows is reached. All harness rows carry
 * notes = 'load-test' so they are identifiable, idempotent and verifiable.
 *
 * Chunked DB::table()->insert() is intentional: per-row Eloquent saves are
 * orders of magnitude too slow at this volume, and withoutEvents alone does
 * not remove model hydration cost. This is a test harness, not production code.
 */
final class StageOneSchedules implements StageInterface
{
    private const WINDOW_START = '2016-06-06'; // a Monday, fixed — never now()-relative

    private const WINDOW_END = '2026-06-28';

    private const CHUNK = 2000;

    public function run(OutputStyle $output, string $scale): bool
    {
        $targetCompleted = $scale === 'full' ? 1_000_000 : 100_000;

        $existing = (int) DB::table('schedules')->where('notes', 'load-test')
            ->where('status', ScheduleStatus::COMPLETED->value)->count();
        if ($existing >= $targetCompleted) {
            $output->writeln("Already {$existing} completed load-test schedules (target {$targetCompleted}) — skipping.");

            return true;
        }

        $ssas = $this->loadSsas();
        if ($ssas === []) {
            $output->writeln('<error>No active assigned SSAs with services found.</error>');

            return false;
        }

        $windowStart = CarbonImmutable::parse(self::WINDOW_START);
        $weeks = (int) $windowStart->diffInWeeks(CarbonImmutable::parse(self::WINDOW_END));
        // ~94% of generated rows land in the past as completed; oversize slightly.
        $needed = (int) ceil(($targetCompleted - $existing) / 0.90);
        $sessionsPerSsa = (int) ceil($needed / count($ssas));
        $perWeek = max(1, (int) ceil($sessionsPerSsa / $weeks));

        $output->writeln(sprintf(
            'Generating ~%s schedules across %d SSAs (%d/week × %d weeks each)…',
            number_format($needed), count($ssas), $perWeek, $weeks
        ));

        $today = CarbonImmutable::parse('2026-06-12');
        $rows = [];
        $inserted = 0;

        foreach ($ssas as $index => $ssa) {
            $batch = 'LT-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT);
            // Deterministic per-SSA timetable: weekday offsets + UTC start hour.
            $startHour = 13 + ($index % 8); // 13:00–20:00 UTC ≈ school hours in US zones

            for ($week = 0; $week < $weeks && $inserted + count($rows) < $needed; $week++) {
                for ($slot = 0; $slot < $perWeek; $slot++) {
                    $date = $windowStart->addWeeks($week)->addDays(($index + $slot * 2) % 5);
                    $isPast = $date->lessThan($today);
                    $status = match (true) {
                        ! $isPast => ScheduleStatus::SCHEDULED,
                        mt_rand(1, 100) <= 94 => ScheduleStatus::COMPLETED,
                        default => ScheduleStatus::CANCELLED,
                    };

                    $rows[] = [
                        'therapist_id' => $ssa->therapist_id,
                        'student_id' => $ssa->student_id,
                        'ssa_id' => $ssa->id,
                        'service_id' => $ssa->service_id,
                        'school_id' => $ssa->school_id,
                        'schedule_date' => $date->toDateString(),
                        'start_time' => sprintf('%02d:00:00', $startHour),
                        'end_time' => sprintf('%02d:00:00', $startHour + 1),
                        'recurrence_type' => 'weekly',
                        'recurring_batch_number' => $batch,
                        'status' => $status->value,
                        'is_group' => 0,
                        'is_billable' => 1,
                        'notes' => 'load-test',
                        'created_at' => $date->subWeeks(2)->toDateTimeString(),
                        'updated_at' => $date->toDateTimeString(),
                    ];

                    if (count($rows) >= self::CHUNK) {
                        DB::table('schedules')->insert($rows);
                        $inserted += count($rows);
                        $rows = [];
                        if ($inserted % 100_000 < self::CHUNK) {
                            $output->writeln(sprintf('  %s inserted…', number_format($inserted)));
                        }
                    }
                }
            }
        }

        if ($rows !== []) {
            DB::table('schedules')->insert($rows);
            $inserted += count($rows);
        }

        $output->writeln(sprintf('Inserted %s schedule rows.', number_format($inserted)));

        return true;
    }

    /**
     * @return array<int, object{id: int, therapist_id: int, student_id: int, school_id: int|null, service_id: int}>
     */
    private function loadSsas(): array
    {
        return DB::table('service_support_agreements as ssa')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'ssa.student_id')
            ->where('ssa.status', SSAStatus::ACTIVE->value)
            ->whereNotNull('ssa.assigned_therapist_id')
            ->whereNotNull('ssa.primary_service_id')
            ->whereNull('ssa.deleted_at')
            ->selectRaw('ssa.id, ssa.assigned_therapist_id as therapist_id, ssa.student_id, student_profiles.school_id, ssa.primary_service_id as service_id')
            ->get()
            ->all();
    }
}
