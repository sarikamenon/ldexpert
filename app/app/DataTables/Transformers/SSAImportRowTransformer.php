<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

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
        $iconView = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        $actionsCell = '<div class="flex items-center"><a href="'.e($showUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring" title="View Import" aria-label="View import #'.(int) $import->id.'">'.$iconView.'</a></div>';

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
