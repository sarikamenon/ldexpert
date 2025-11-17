<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SchoolStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly School $school,
        public readonly string $oldStatus,
        public readonly string $newStatus
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'school_id' => $this->school->id,
            'school_name' => $this->school->display_name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'message' => "School '{$this->school->display_name}' status changed from {$this->oldStatus} to {$this->newStatus}.",
            'action_url' => route('admin.schools.edit', $this->school),
        ];
    }
}

