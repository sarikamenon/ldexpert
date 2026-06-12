<?php

declare(strict_types=1);

namespace Database\Seeders\LoadTest;

use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Enums\SessionLogStatus;
use App\Enums\SessionOutcome;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;

/**
 * One session log per COMPLETED load-test schedule. Logs are seeded
 * UN-INVOICED and UN-BILLED on purpose: this is the worst case for the
 * billing sweep (no lower date bound) and the realistic upper bound for the
 * DataTables list measurements. Measure billing:generate with --dry-run.
 */
final class StageTwoSessionLogs implements StageInterface
{
    private const CHUNK = 2000;

    public function run(OutputStyle $output, string $scale): bool
    {
        $alreadySeeded = (int) DB::table('session_logs')->where('notes', 'load-test')->count();
        $sourceCount = (int) DB::table('schedules')->where('notes', 'load-test')
            ->where('status', ScheduleStatus::COMPLETED->value)->count();

        if ($alreadySeeded >= $sourceCount) {
            $output->writeln("Already {$alreadySeeded} load-test session logs — skipping.");

            return true;
        }

        $adminId = User::query()->where('role', Role::ADMIN->value)->orderBy('id')->value('id');
        if ($adminId === null) {
            $output->writeln('<error>No admin user found for approved_by attribution.</error>');

            return false;
        }

        $output->writeln(sprintf('Creating session logs for %s completed schedules…', number_format($sourceCount)));

        $inserted = 0;
        $lastId = 0;

        do {
            /** @var array<int, object> $schedules */
            $schedules = DB::table('schedules')
                ->where('notes', 'load-test')
                ->where('status', ScheduleStatus::COMPLETED->value)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(self::CHUNK)
                ->get(['id', 'therapist_id', 'student_id', 'ssa_id', 'service_id', 'school_id', 'schedule_date', 'start_time', 'end_time'])
                ->all();

            if ($schedules === []) {
                break;
            }

            $rows = collect($schedules)->map(function (object $schedule) use ($adminId): array {
                $start = $schedule->schedule_date.' '.$schedule->start_time;
                $end = $schedule->schedule_date.' '.$schedule->end_time;
                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $roll <= 85 => SessionLogStatus::APPROVED,
                    $roll <= 95 => SessionLogStatus::SUBMITTED,
                    default => SessionLogStatus::DRAFT,
                };
                $outcome = mt_rand(1, 100) <= 92
                    ? SessionOutcome::SERVICES_ADMINISTERED
                    : SessionOutcome::NO_SHOW;
                $therapistRate = (float) mt_rand(40, 80);
                $schoolRate = (float) mt_rand(90, 150);
                $approved = $status === SessionLogStatus::APPROVED;

                return [
                    'therapist_id' => $schedule->therapist_id,
                    'student_id' => $schedule->student_id,
                    'ssa_id' => $schedule->ssa_id,
                    'service_id' => $schedule->service_id,
                    'school_id' => $schedule->school_id,
                    'schedule_id' => $schedule->id,
                    'session_date' => $schedule->schedule_date,
                    'start_time' => $start,
                    'end_time' => $end,
                    'duration_minutes' => 60,
                    'tho_minutes' => 60,
                    'delivery_mode' => 'teletherapy',
                    'outcome' => $outcome->value,
                    'is_group' => 0,
                    'is_billable_therapist' => 1,
                    'therapist_rate_type' => 'H',
                    'therapist_rate_amount' => $therapistRate,
                    'therapist_billable_amount' => $therapistRate,
                    'is_billable_school' => 1,
                    'school_rate_type' => 'H',
                    'school_rate_amount' => $schoolRate,
                    'school_invoice_amount' => $schoolRate,
                    'status' => $status->value,
                    'submitted_at' => $status !== SessionLogStatus::DRAFT ? $end : null,
                    'submitted_by_id' => $status !== SessionLogStatus::DRAFT ? $schedule->therapist_id : null,
                    'approved_at' => $approved ? $end : null,
                    'approved_by_id' => $approved ? $adminId : null,
                    'notes' => 'load-test',
                    'created_at' => $end,
                    'updated_at' => $end,
                ];
            })->all();

            DB::table('session_logs')->insert($rows);
            $inserted += count($rows);
            $lastId = (int) end($schedules)->id;

            if ($inserted % 100_000 < self::CHUNK) {
                $output->writeln(sprintf('  %s inserted…', number_format($inserted)));
            }
        } while (true);

        $output->writeln(sprintf('Inserted %s session log rows.', number_format($inserted)));

        return true;
    }
}
