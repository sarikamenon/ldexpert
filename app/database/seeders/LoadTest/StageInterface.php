<?php

declare(strict_types=1);

namespace Database\Seeders\LoadTest;

use Illuminate\Console\OutputStyle;

interface StageInterface
{
    /**
     * Run the stage. Stages must be idempotent: re-running against a database
     * that already meets the stage's row target is a no-op.
     */
    public function run(OutputStyle $output, string $scale): bool;
}
