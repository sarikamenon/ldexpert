<?php

declare(strict_types=1);

namespace App\Enums;

enum SessionLogStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case FINALIZED = 'finalized';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::FINALIZED => 'Finalized',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $status): string => $status->value,
            self::cases()
        );
    }

    public function canEdit(): bool
    {
        return $this === self::DRAFT;
    }

    public function canSubmit(): bool
    {
        return $this === self::DRAFT;
    }

    public function canFinalize(): bool
    {
        return $this === self::SUBMITTED;
    }

    public function canCancel(): bool
    {
        return $this === self::DRAFT || $this === self::SUBMITTED;
    }
}
