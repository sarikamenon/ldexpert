<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Shared minute bounds for SSA, Schedules, and Session Logs
    |--------------------------------------------------------------------------
    |
    | These values define the allowed minimum and maximum duration (in minutes)
    | for:
    | - SSA minutes_per_session
    | - Therapist schedule duration_minutes
    | - Therapist session log duration_minutes
    |
    | Adjusting these values will automatically update validation rules and
    | form field constraints wherever they are used.
    |
    */

    'min' => 5,
    'max' => 1440,
];
