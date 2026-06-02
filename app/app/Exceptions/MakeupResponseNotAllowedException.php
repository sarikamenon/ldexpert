<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a parent's token click cannot be applied as a response.
 *
 * The `reason` discriminator lets the controller pick a friendly landing page
 * (deadline passed, already responded, event historical, etc.) without having
 * to inspect row state itself.
 */
final class MakeupResponseNotAllowedException extends RuntimeException
{
    public const REASON_ALREADY_RESPONDED = 'already_responded';

    public const REASON_DEADLINE_PASSED = 'deadline_passed';

    public const REASON_EVENT_PAST = 'event_past';

    public const REASON_BAD_STATE = 'bad_state';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Response cannot be applied: '.$reason);
    }
}
