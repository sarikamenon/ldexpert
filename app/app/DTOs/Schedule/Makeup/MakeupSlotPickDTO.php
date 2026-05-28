<?php

declare(strict_types=1);

namespace App\DTOs\Schedule\Makeup;

use Carbon\CarbonImmutable;

/**
 * One parent-picked sub-slot for a single missed session (one row in the batch).
 * The controller validates and builds these; the booking service consumes them.
 */
final class MakeupSlotPickDTO
{
    public function __construct(
        public readonly int $makeupRequestId,
        public readonly CarbonImmutable $startUtc,
        public readonly CarbonImmutable $endUtc,
    ) {}

    public function date(): string
    {
        return $this->startUtc->format('Y-m-d');
    }

    public function startTime(): string
    {
        return $this->startUtc->format('H:i:s');
    }

    public function endTime(): string
    {
        return $this->endUtc->format('H:i:s');
    }
}
