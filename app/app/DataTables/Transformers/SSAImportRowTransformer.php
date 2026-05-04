<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\SSAImport;

final class SSAImportRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(SSAImport $import): array
    {
        $idCell = '#'.(int) $import->id;
        $typeCell = e($import->type->value ?? '');
        $fileNameCell = e($import->file_name ?? '—');
        $userCell = e($import->user->name ?? '—');

        $statusValue = $import->status->value ?? '';
        $statusClasses = match ($statusValue) {
            'completed' => 'bg-success/10 text-success border border-success/20',
            'failed' => 'bg-danger/10 text-danger border border-danger/20',
            'processing' => 'bg-primary/10 text-primary border border-primary/20',
            default => 'bg-warning/10 text-warning border border-warning/20',
        };
        $statusCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$statusClasses.'">'.e(strtoupper($statusValue)).'</span>';

        $progressCell = (int) $import->processed_rows.' / '.(int) $import->total_rows;
        $createdCell = $import->created_at ? $import->created_at->format('M d, Y H:i') : '—';

        $showUrl = route('admin.ssas.imports.show', $import);
        $downloadUrl = route('admin.ssas.imports.download', $import);
        $actionsCell = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View Import'),
            ActionButtons::download($downloadUrl, 'Download CSV'),
        );

        return [
            $idCell,
            $typeCell,
            $fileNameCell,
            $userCell,
            $statusCell,
            $progressCell,
            $createdCell,
            $actionsCell,
        ];
    }
}
