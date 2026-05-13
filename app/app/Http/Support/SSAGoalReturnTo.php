<?php

declare(strict_types=1);

namespace App\Http\Support;

/**
 * Whitelisted post-create redirect targets for SSA goal flows (avoid open redirects).
 */
enum SSAGoalReturnTo: string
{
    case StudentGoalsTab = 'student_goals';

    /**
     * Resolve a query-string token to a known redirect target, or null if absent/unknown.
     */
    public static function tryFromQuery(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        return self::tryFrom($value);
    }
}
