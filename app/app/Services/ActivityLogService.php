<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ActivityLog\Repositories\ActivityLogRepositoryInterface;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogs,
    ) {}

    public function log(
        string $action,
        Model $model,
        ?array $changes = null,
        ?string $description = null
    ): ActivityLog {
        return $this->activityLogs->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id ?? null,
            'changes' => $changes,
            'description' => $description ?? $this->generateDescription($action, $model),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function logCreated(Model $model, ?string $description = null): ActivityLog
    {
        return $this->log('created', $model, null, $description);
    }

    public function logUpdated(Model $model, array $changes, ?string $description = null): ActivityLog
    {
        return $this->log('updated', $model, $changes, $description);
    }

    public function logDeleted(Model $model, ?string $description = null): ActivityLog
    {
        return $this->log('deleted', $model, null, $description);
    }

    public function logStatusChanged(Model $model, string $oldStatus, string $newStatus, ?string $reason = null): ActivityLog
    {
        $changes = [
            'status' => [
                'old' => $oldStatus,
                'new' => $newStatus,
            ],
        ];

        if ($reason) {
            $changes['reason'] = ['old' => null, 'new' => $reason];
        }

        return $this->log('status_changed', $model, $changes);
    }

    public function logBulkAction(string $action, string $modelType, array $ids, ?array $metadata = null): ActivityLog
    {
        $model = new $modelType();

        return $this->activityLogs->create([
            'user_id' => Auth::id(),
            'action' => 'bulk_' . $action,
            'model_type' => $modelType,
            'model_id' => null,
            'changes' => [
                'ids' => $ids,
                'count' => count($ids),
                'metadata' => $metadata,
            ],
            'description' => "Bulk {$action} performed on " . count($ids) . " " . class_basename($modelType) . "(s)",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private function generateDescription(string $action, Model $model): string
    {
        $modelName = class_basename($model);
        $identifier = method_exists($model, 'getIdentifierAttribute')
            ? $model->getIdentifierAttribute()
            : ($model->name ?? $model->display_name ?? $model->id ?? 'Unknown');

        return match ($action) {
            'created' => "{$modelName} '{$identifier}' was created",
            'updated' => "{$modelName} '{$identifier}' was updated",
            'deleted' => "{$modelName} '{$identifier}' was deleted",
            'status_changed' => "{$modelName} '{$identifier}' status was changed",
            default => "{$modelName} '{$identifier}' - {$action}",
        };
    }

    public function recent(int $limit = 5): Collection
    {
        return $this->activityLogs->recent($limit);
    }
}
