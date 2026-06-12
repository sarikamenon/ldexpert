<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\LoadTest\StageInterface;
use Database\Seeders\LoadTest\StageOneSchedules;
use Database\Seeders\LoadTest\StageTwoSessionLogs;
use Database\Seeders\LoadTest\StageVerify;
use Database\Seeders\LoadTest\StageZeroBase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class LoadTestSeed extends Command
{
    protected $signature = 'load-test:seed
        {--scale=tenth : tenth (~100k rows/table, laptop-fast) or full (~1M rows/table)}
        {--stage=* : run only the named stages (base, schedules, session-logs, verify)}
        {--seed=20260611 : RNG seed for reproducible datasets}';

    protected $description = 'Seed a 10-year volume dataset for schedules + session_logs load testing (synthetic data only — never run against production)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        // Bulk inserts: stop anything that buffers per-query state in memory.
        ini_set('memory_limit', '1024M');
        DB::disableQueryLog();
        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        // Deterministic randomness so runs are comparable across machines.
        mt_srand((int) $this->option('seed'));

        $scale = (string) $this->option('scale');
        if (! in_array($scale, ['tenth', 'full'], true)) {
            $this->error('Scale must be tenth or full.');

            return self::FAILURE;
        }

        /** @var array<string, StageInterface> $stages */
        $stages = [
            'base' => new StageZeroBase,
            'schedules' => new StageOneSchedules,
            'session-logs' => new StageTwoSessionLogs,
            'verify' => new StageVerify,
        ];

        /** @var array<int, string> $only */
        $only = $this->option('stage');

        foreach ($stages as $name => $stage) {
            if ($only !== [] && ! in_array($name, $only, true)) {
                continue;
            }

            $this->info("=== Stage: {$name}");
            $startedAt = microtime(true);

            if (! $stage->run($this->output, $scale)) {
                $this->error("Stage {$name} failed.");

                return self::FAILURE;
            }

            $this->info(sprintf('=== Stage %s done in %.1fs', $name, microtime(true) - $startedAt));
        }

        return self::SUCCESS;
    }
}
