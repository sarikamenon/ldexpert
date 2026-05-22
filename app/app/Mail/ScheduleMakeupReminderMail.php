<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Schedule\Makeup\Presenters\MakeupRequestPresenter;
use App\Enums\ServiceFrequency;
use App\Models\ScheduleMakeupRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Reminder email sent to a parent for one batch of make-up requests
 * (one batch = same calendar event, student, therapist; may span multiple dates).
 *
 * View variant is selected by the SSA's frequency_type — weekly/bi-weekly use
 * the "weekly" template; monthly/quarterly/one-time use the "monthly" template.
 *
 * Copy is intentionally placeholder until the client confirms wording.
 */
class ScheduleMakeupReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, ScheduleMakeupRequest>  $batch
     */
    public function __construct(
        public readonly Collection $batch,
        public readonly string $recipientName,
        public readonly User $therapist,
        public readonly ServiceFrequency $frequency,
    ) {}

    public function envelope(): Envelope
    {
        /** @var ScheduleMakeupRequest $head */
        $head = $this->batch->first();
        /** @var User $student */
        $student = $head->student;

        return new Envelope(
            from: new Address(
                address: $this->therapist->email,
                name: $this->therapist->name,
            ),
            replyTo: [new Address($this->therapist->email, $this->therapist->name)],
            subject: "Make-up session needed for {$student->name}",
        );
    }

    public function content(MakeupRequestPresenter $presenter): Content
    {
        $head = $this->batch->first();
        if ($head === null) {
            // Defensive: sender never invokes the mailable with an empty batch.
            return new Content(view: $this->viewName(), with: []);
        }

        $token = $head->response_token;

        $requestUrl = URL::signedRoute('makeup-response.request', ['token' => $token]);
        $declineUrl = URL::signedRoute('makeup-response.decline', ['token' => $token]);

        $dateFormat = (string) config('display.date_long');

        $sessionLabels = $presenter->sessionLabels($this->batch);

        $responseByDate = Carbon::parse($head->response_date->toDateString())
            ->format($dateFormat);

        /** @var User $student */
        $student = $head->student;

        return new Content(
            view: $this->viewName(),
            with: [
                'recipientName' => $this->recipientName,
                'studentName' => $student->name,
                'therapistName' => $this->therapist->name,
                'therapistEmail' => $this->therapist->email,
                'dates' => $sessionLabels,
                'responseByDate' => $responseByDate,
                'requestUrl' => $requestUrl,
                'declineUrl' => $declineUrl,
            ],
        );
    }

    private function viewName(): string
    {
        return match ($this->frequency) {
            ServiceFrequency::MONTHLY,
            ServiceFrequency::QUARTERLY,
            ServiceFrequency::ONE_TIME => 'emails.makeup-reminder-monthly',
            ServiceFrequency::WEEKLY,
            ServiceFrequency::BI_WEEKLY => 'emails.makeup-reminder-weekly',
        };
    }
}
