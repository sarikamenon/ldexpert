<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Lead\Repositories\LeadRepositoryInterface;
use App\Mail\LeadFollowUpReminderMail;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendLeadFollowUpReminders extends Command
{
    protected $signature = 'leads:send-follow-up-reminders';

    protected $description = 'Send email reminders for leads with follow-up dates scheduled for today';

    public function __construct(
        private readonly LeadRepositoryInterface $leadRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $leads = $this->leadRepository->getFollowUpsOnDate($today);

        if ($leads->isEmpty()) {
            $this->info('No follow-up reminders to send today.');

            return self::SUCCESS;
        }

        $sentCount = 0;

        /** @var Lead $lead */
        foreach ($leads as $lead) {
            $admin = $lead->createdBy;
            if (! $admin || ! $admin->email) {
                $this->warn("Lead #{$lead->id} ({$lead->full_name}) — no admin email, skipping.");

                continue;
            }

            Mail::to($admin->email)->queue(
                new LeadFollowUpReminderMail($lead, $admin->name)
            );

            $sentCount++;
        }

        $this->info("Sent {$sentCount} follow-up reminder(s) for {$leads->count()} lead(s).");

        return self::SUCCESS;
    }
}
