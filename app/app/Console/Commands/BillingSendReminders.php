<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\Services\BillingReminderService;
use Illuminate\Console\Command;

class BillingSendReminders extends Command
{
    protected $signature = 'billing:send-reminders
        {--dry-run : Show what reminders would be sent without sending}';

    protected $description = 'Send payment reminders for upcoming and overdue invoices';

    public function __construct(
        private readonly BillingReminderService $reminderService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no reminders will be sent.');
            $this->newLine();
        }

        $this->info('Processing billing reminders...');

        $result = $this->reminderService->processReminders($dryRun);

        $this->info("Sent: {$result['sent']}, Skipped: {$result['skipped']}");

        return self::SUCCESS;
    }
}
