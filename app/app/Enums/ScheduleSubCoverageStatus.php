<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tracks the sub-coverage state recorded on the schedule row itself
 * (schedules.sub_request_status). Mirrors the parent request lifecycle
 * but stays null when no request has ever been raised for the schedule.
 */
enum ScheduleSubCoverageStatus: string
{
    case REQUESTED = 'requested';
    case ACCEPTED = 'accepted';
}
