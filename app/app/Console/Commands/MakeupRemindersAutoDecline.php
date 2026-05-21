<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class MakeupRemindersAutoDecline extends Command
{
    protected $signature = 'makeup-reminders:auto-decline';

    protected $description = 'Auto-decline make-up reminders whose response deadline has passed without a parent reply.';

    public function handle(ScheduleMakeupRequestRepositoryInterface $repository): int
    {
        $count = $repository->bulkAutoDecline(Carbon::today());

        $this->info(sprintf('Auto-declined %d overdue make-up reminder(s).', $count));

        return self::SUCCESS;
    }
}
