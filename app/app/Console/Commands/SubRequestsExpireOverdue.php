<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Schedule\Sub\Services\ScheduleSubRequestService;
use Illuminate\Console\Command;

final class SubRequestsExpireOverdue extends Command
{
    protected $signature = 'sub-requests:expire-overdue';

    protected $description = 'Mark open sub requests whose schedule is past the create-cutoff as expired.';

    public function handle(ScheduleSubRequestService $service): int
    {
        $count = $service->expireOverdue();

        $this->info("Expired {$count} sub request(s).");

        return self::SUCCESS;
    }
}
