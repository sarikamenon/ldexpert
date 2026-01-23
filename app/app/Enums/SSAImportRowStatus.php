<?php

declare(strict_types=1);

namespace App\Enums;

enum SSAImportRowStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case DONE = 'done';
    case DUPLICATE = 'duplicate';
    case VALIDATION_ERROR = 'validation_error';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
