<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

final class ActivityLogRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(ActivityLog $log): array
    {
        $idCell = (string) $log->id;
        $userCell = e($log->user->name ?? 'System');

        $actionKey = $log->action ?? 'activity';
        $actionLabel = Str::headline($actionKey);
        $variant = match (true) {
            str_contains($actionKey, 'created') => 'success',
            str_contains($actionKey, 'updated') => 'primary',
            str_contains($actionKey, 'deleted') => 'danger',
            str_contains($actionKey, 'status') => 'warning',
            default => 'secondary',
        };
        $badgeClass = 'inline-flex items-center gap-1 text-xs font-medium capitalize';
        $variantMap = [
            'success' => 'bg-success/10 text-success border border-success/20',
            'primary' => 'bg-primary/10 text-primary border border-primary/20',
            'danger' => 'bg-danger/10 text-danger border border-danger/20',
            'warning' => 'bg-warning/10 text-warning border border-warning/20',
            'secondary' => 'bg-secondary/10 text-secondary border border-secondary/20',
        ];
        $spanClass = $variantMap[$variant] ?? $variantMap['secondary'];
        $actionCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base '.$spanClass.' '.$badgeClass.'"><span class="h-1.5 w-1.5 rounded-full bg-current"></span> '.e($actionLabel).'</span>';

        $modelCell = e($log->model_type ? class_basename($log->model_type) : '—');

        $desc = e($log->description ?? '');
        $changes = $log->formatted_changes;
        $descriptionCell = '<div class="max-w-md"><div class="truncate" title="'.$desc.'">'.$desc.'</div>';
        if ($changes) {
            $descriptionCell .= '<div class="text-xs text-foreground/60 mt-1">'.e($changes).'</div>';
        }
        $descriptionCell .= '</div>';

        $ipCell = e($log->ip_address ?? '—');

        $dateTitle = $log->created_at_local?->format('Y-m-d H:i:s') ?? '';
        $dateLabel = $log->created_at_local ? $log->created_at_local->diffForHumans() : '—';
        $dateCell = '<span title="'.e($dateTitle).'">'.e($dateLabel).'</span>';

        return [
            $idCell,
            $userCell,
            $actionCell,
            $modelCell,
            $descriptionCell,
            $ipCell,
            $dateCell,
        ];
    }
}
