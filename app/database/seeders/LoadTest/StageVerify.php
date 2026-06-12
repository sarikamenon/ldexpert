<?php

declare(strict_types=1);

namespace Database\Seeders\LoadTest;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;

/**
 * Post-seed invariants. A dataset that fails here must be discarded —
 * measurements against a broken graph are noise, not findings.
 */
final class StageVerify implements StageInterface
{
    public function run(OutputStyle $output, string $scale): bool
    {
        $targetCompleted = $scale === 'full' ? 1_000_000 : 100_000;
        $ok = true;

        $completed = (int) DB::table('schedules')->where('notes', 'load-test')->where('status', 'completed')->count();
        $logs = (int) DB::table('session_logs')->where('notes', 'load-test')->count();
        $output->writeln(sprintf('Completed load-test schedules: %s (target %s)', number_format($completed), number_format($targetCompleted)));
        $output->writeln(sprintf('Load-test session logs:        %s', number_format($logs)));

        if ($completed < $targetCompleted) {
            $output->writeln('<error>Schedule target not met.</error>');
            $ok = false;
        }
        if ($logs < $completed) {
            $output->writeln('<error>Fewer session logs than completed schedules.</error>');
            $ok = false;
        }

        // Event-date invariant: session_date must equal the UTC date of start_time.
        $dateDrift = (int) DB::table('session_logs')
            ->where('notes', 'load-test')
            ->whereRaw('session_date <> DATE(start_time)')
            ->count();
        $output->writeln("session_date <> DATE(start_time) violations: {$dateDrift}");
        if ($dateDrift > 0) {
            $ok = false;
        }

        // Referential integrity: every log points at a real schedule.
        $orphans = (int) DB::table('session_logs as sl')
            ->leftJoin('schedules as s', 's.id', '=', 'sl.schedule_id')
            ->where('sl.notes', 'load-test')
            ->whereNull('s.id')
            ->count();
        $output->writeln("Orphaned session logs: {$orphans}");
        if ($orphans > 0) {
            $ok = false;
        }

        foreach (['schedules', 'session_logs'] as $table) {
            $size = DB::selectOne(
                'SELECT ROUND((data_length + index_length) / 1048576) AS mb FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );
            $output->writeln(sprintf('%s on-disk size: %d MB', $table, (int) ($size->mb ?? 0)));
        }

        return $ok;
    }
}
