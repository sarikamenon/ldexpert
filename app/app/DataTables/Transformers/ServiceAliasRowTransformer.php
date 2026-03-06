<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\SSAImportType;
use App\Models\ServiceAlias;

final class ServiceAliasRowTransformer
{
    /**
     * @return array<int, string> 5 cell HTML strings in column order
     */
    public static function transform(ServiceAlias $alias): array
    {
        $editUrl = route('admin.service-aliases.edit', $alias);
        $deleteUrl = route('admin.service-aliases.destroy', $alias);

        $sourceBadge = self::sourceBadge($alias->source);

        $serviceName = $alias->service
            ? e($alias->service->name)
            : '<span class="text-sm text-foreground/50">Unknown</span>';

        $createdAt = $alias->created_at?->format('M d, Y') ?? '';

        $actions = ActionButtons::wrap(
            ActionButtons::edit($editUrl, 'Edit Alias'),
            ActionButtons::delete(
                $deleteUrl,
                'Delete Alias',
                'Delete Service Alias?',
                'This will permanently remove the mapping for "'.e($alias->external_name).'".',
            ),
        );

        return [
            $sourceBadge,
            e($alias->external_name),
            (string) $serviceName,
            e($createdAt),
            $actions,
        ];
    }

    private static function sourceBadge(string $source): string
    {
        $colors = SSAImportType::badgeClassesFor($source);

        return '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$colors.' border">'.e($source).'</span>';
    }
}
