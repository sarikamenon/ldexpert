<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Enums\SchoolStatus;
use App\Mail\SchoolContractExpiryWarningMail;
use App\Models\SchoolContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SchoolContractExpiryNotify extends Command
{
    protected $signature = 'school:notify-expiring-contracts
        {--dry-run : List schools that would be notified without sending email}';

    protected $description = 'Send expiry warning emails to managers of private-student schools whose contract expires in 7 days';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no emails will be sent.');
            $this->newLine();
        }

        $contracts = SchoolContract::query()
            ->where('status', ContractStatus::ACTIVE)
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(14)->toDateString()])
            ->whereHas('school', function ($q): void {
                $q->where('is_private_student', true) // @phpstan-ignore argument.type
                    ->where('status', SchoolStatus::ACTIVE->value); // @phpstan-ignore argument.type
            })
            ->with('school.manager')
            ->get();

        if ($contracts->isEmpty()) {
            $this->info('No contracts expiring in the next 2 weeks.');

            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;

        foreach ($contracts as $contract) {
            $school = $contract->school;

            if (! $school) {
                $this->warn("Contract #{$contract->id}: no school found, skipping.");
                $skipped++;

                continue;
            }

            $localNow = now()->setTimezone($school->timezone);
            $targetDate = $localNow->addDays(7)->toDateString();

            if ($contract->end_date->toDateString() !== $targetDate) {
                continue;
            }

            $manager = $school->manager;

            if (! $manager || ! $manager->email) {
                $this->warn("School [{$school->display_name}]: no manager or manager email, skipping.");
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("Would notify: {$school->display_name} → {$manager->email} (expires {$contract->end_date->toDateString()})");
                $sent++;

                continue;
            }

            Mail::to($manager->email)->queue(new SchoolContractExpiryWarningMail($school, $contract));
            $sent++;
        }

        $this->newLine();
        $this->info("Done. Notified: {$sent}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
