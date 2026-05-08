<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Centralised writer for audits that bypass model events
 * (pivot syncs, custom domain events, summary roll-ups).
 */
class AuditRecorder
{
    public function __construct(private readonly AuditBatchContext $batchContext) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        Model $auditable,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $createdBy = null,
    ): Audit {
        /** @var Audit $audit */
        $audit = Audit::query()->create([
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'created_by' => $createdBy ?? $this->resolveUserId(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'batch_uuid' => $this->batchContext->current(),
            'source' => $this->resolveSource(),
            'url' => $this->resolveUrl(),
            'ip_address' => $this->resolveIp(),
            'user_agent' => $this->resolveUserAgent(),
        ]);

        return $audit;
    }

    private function resolveUserId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }

    private function resolveSource(): string
    {
        $override = $this->batchContext->source();
        if ($override !== null) {
            return $override;
        }

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return 'console';
        }

        return 'web';
    }

    private function resolveUrl(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return mb_substr(request()->fullUrl(), 0, 2048);
    }

    private function resolveIp(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return request()->ip();
    }

    private function resolveUserAgent(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return request()->userAgent();
    }
}
