<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Schedule\Makeup\Repositories\ScheduleMakeupRequestRepositoryInterface;
use App\Domain\Schedule\Makeup\Services\TherapistMakeupNotificationService;
use App\Models\ScheduleMakeupRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class MakeupRemindersAutoDecline extends Command
{
    protected $signature = 'makeup-reminders:auto-decline';

    protected $description = 'Auto-decline make-up reminders whose response deadline has passed without a parent reply.';

    public function handle(
        ScheduleMakeupRequestRepositoryInterface $repository,
        TherapistMakeupNotificationService $notificationService,
    ): int {
        $today = Carbon::today();

        $overdueRows = $repository->listOverdueForResponse($today);

        $count = $repository->bulkAutoDecline($today);

        // One therapist notification per batch (not per missed-session row): a
        // multi-day closure produces several rows in one batch but is a single email.
        $overdueRows
            ->groupBy('batch_number')
            ->each(function (Collection $batch) use ($notificationService): void {
                $head = $batch->first();
                if ($head instanceof ScheduleMakeupRequest) {
                    $notificationService->sendNonAcceptedNotification($head);
                }
            });

        $this->info(sprintf('Auto-declined %d overdue make-up reminder(s).', $count));

        return self::SUCCESS;
    }
}
