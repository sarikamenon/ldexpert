<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use Illuminate\Support\Str;

/**
 * Request/job-scoped batch UUID + source override for audit grouping.
 *
 * Phase 1 ships the class. Middleware/job wiring lands in Phase 2;
 * until then audits will have batch_uuid = null unless code calls start().
 */
class AuditBatchContext
{
    private ?string $batchUuid = null;

    private ?string $source = null;

    public function start(?string $source = null): string
    {
        $this->batchUuid = (string) Str::uuid();
        $this->source = $source;

        return $this->batchUuid;
    }

    public function current(): ?string
    {
        return $this->batchUuid;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function clear(): void
    {
        $this->batchUuid = null;
        $this->source = null;
    }
}
