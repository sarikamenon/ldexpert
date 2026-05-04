<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasContractDocument
{
    public function getFormattedDocumentSizeAttribute(): string
    {
        $size = $this->document_size;

        if ($size === null) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return sprintf('%.2f %s', round($size, 2), $units[$unit]);
    }
}
