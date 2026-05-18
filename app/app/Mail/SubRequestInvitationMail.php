<?php

declare(strict_types=1);

namespace App\Mail;

use App\Constants\UsTimezones;
use App\Domain\Time\UserTimezoneService;
use App\Models\ScheduleSubRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubRequestInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ScheduleSubRequest $subRequest,
        public readonly User $invitee,
    ) {}

    public function envelope(): Envelope
    {
        $brandName = config('brand.name');
        $schedule = $this->subRequest->schedule;
        $requesterName = $this->subRequest->requestedBy->name;

        $tz = $this->resolveInviteeTimezone();
        $date = $schedule !== null
            ? $schedule->localStart($tz)->format(config('display.date'))
            : '';

        return new Envelope(
            subject: "{$brandName} - Coverage request from {$requesterName} for {$date}",
        );
    }

    public function content(): Content
    {
        $schedule = $this->subRequest->schedule;
        $tz = $this->resolveInviteeTimezone();

        $localStart = $schedule?->localStart($tz);
        $localEnd = $schedule?->localEnd($tz);

        return new Content(
            view: 'emails.sub-request-invitation',
            with: [
                'subRequest' => $this->subRequest,
                'schedule' => $schedule,
                'invitee' => $this->invitee,
                'requesterName' => $this->subRequest->requestedBy->name ?? 'A colleague',
                'reason' => $this->subRequest->reason,
                'scheduleDateLong' => $localStart?->format(config('display.date_long')) ?? '',
                'scheduleStartTime' => $localStart?->format(config('display.time')) ?? '',
                'scheduleEndTime' => $localEnd?->format(config('display.time')) ?? '',
                'scheduleTimezone' => UsTimezones::getTimezoneLabel($tz),
                'reviewUrl' => url('/therapist/sub-requests'),
            ],
        );
    }

    private function resolveInviteeTimezone(): string
    {
        return app(UserTimezoneService::class)->resolveTimezone($this->invitee);
    }
}
