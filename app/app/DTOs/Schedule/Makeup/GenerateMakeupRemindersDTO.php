<?php

declare(strict_types=1);

namespace App\DTOs\Schedule\Makeup;

use Carbon\CarbonImmutable;

/**
 * Input transport for ScheduleMakeupReminderGenerator.
 *
 * The generator scans closure events whose date range overlaps the lookahead
 * window [today, today + lookaheadDays] and creates pending reminder rows for
 * any in-scope schedule that doesn't already have one. Each row inherits its
 * reminder/response/deadline dates from the parent calendar event — there are
 * no global offsets anymore.
 */
final class GenerateMakeupRemindersDTO
{
    public function __construct(
        public readonly CarbonImmutable $today,
        public readonly int $lookaheadDays,
    ) {}

    public static function fromConfig(?CarbonImmutable $today = null): self
    {
        return new self(
            today: $today ?? CarbonImmutable::now()->startOfDay(),
            lookaheadDays: (int) config('schedule_makeup.generator_lookahead_days'),
        );
    }
}
