<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Sub\Services;

/**
 * Result of a batched sub-request creation across a recurring schedule's
 * occurrences. The first occurrence is required (its failure propagates);
 * later occurrences are best-effort so an exception on one doesn't undo
 * the requests that already succeeded.
 */
final class SubRequestBatchResult
{
    /**
     * @param  array<int, array{schedule_id: int, reason: string}>  $skippedDetails
     */
    public function __construct(
        public readonly int $created,
        public readonly int $skipped,
        public readonly array $skippedDetails = [],
    ) {}

    /** @return array{created: int, skipped: int} */
    public function toArray(): array
    {
        return ['created' => $this->created, 'skipped' => $this->skipped];
    }
}
