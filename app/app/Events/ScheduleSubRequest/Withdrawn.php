<?php

declare(strict_types=1);

namespace App\Events\ScheduleSubRequest;

use App\Models\ScheduleSubRequest;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an accepted sub request is withdrawn by the requester. Carries the
 * therapist who had been covering so they can be notified that coverage was revoked.
 */
class Withdrawn
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ScheduleSubRequest $subRequest,
        public readonly User $coveringTherapist,
    ) {}
}
