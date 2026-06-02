<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Schedule\Makeup\Services\ScheduleMakeupReminderSender;
use Illuminate\Console\Command;

final class MakeupRemindersSendDue extends Command
{
    protected $signature = 'makeup-reminders:send-due';

    protected $description = 'Send parent reminder emails for pending make-up requests whose reminder_date has arrived.';

    public function handle(ScheduleMakeupReminderSender $sender): int
    {
        $result = $sender->sendDue();

        $this->info(sprintf(
            '%d batch(es) processed: %d sent, %d skipped, %d failed.',
            $result['batches_total'],
            $result['batches_sent'],
            $result['batches_skipped'],
            $result['batches_failed'],
        ));

        return $result['batches_failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
