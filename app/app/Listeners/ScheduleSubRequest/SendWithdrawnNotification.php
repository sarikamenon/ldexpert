<?php

declare(strict_types=1);

namespace App\Listeners\ScheduleSubRequest;

use App\Events\ScheduleSubRequest\Withdrawn;
use App\Mail\ScheduleSubRequest\SubRequestWithdrawnMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the covering therapist when an accepted sub request is withdrawn. Queued,
 * and failures are logged and swallowed so a mailer outage never fails the withdrawal.
 */
class SendWithdrawnNotification implements ShouldQueue
{
    public function handle(Withdrawn $event): void
    {
        $therapist = $event->coveringTherapist;

        if (empty($therapist->email)) {
            return;
        }

        try {
            Mail::to($therapist->email)->send(new SubRequestWithdrawnMail($event->subRequest, $therapist));
        } catch (\Throwable $e) {
            Log::error('SendWithdrawnNotification: failed to send withdrawal mail', [
                'sub_request_id' => $event->subRequest->id,
                'covering_therapist_id' => $therapist->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
