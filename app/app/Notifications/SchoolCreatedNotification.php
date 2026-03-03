<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly School $school
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New School Created')
            ->line("A new school '{$this->school->display_name}' has been created.")
            ->action('View School', route('admin.schools.edit', $this->school))
            ->line('Thank you for using our application!');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'school_id' => $this->school->id,
            'school_name' => $this->school->display_name,
            'message' => "New school '{$this->school->display_name}' has been created.",
            'action_url' => route('admin.schools.edit', $this->school),
        ];
    }
}
