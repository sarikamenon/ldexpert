<?php

declare(strict_types=1);

namespace App\Enums;

enum SSAGoalStatus: string
{
    case ACTIVE = 'active';
    case MASTERED = 'mastered';
    case DISCONTINUED = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::MASTERED => 'Mastered',
            self::DISCONTINUED => 'Discontinued',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isMastered(): bool
    {
        return $this === self::MASTERED;
    }

    public function isDiscontinued(): bool
    {
        return $this === self::DISCONTINUED;
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::ACTIVE => 'info',
            self::MASTERED => 'success',
            self::DISCONTINUED => 'muted',
        };
    }

    /** CSS classes for the left-border accent on goal cards. */
    public function borderClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'border-l-4 border-l-primary',
            self::MASTERED => 'border-l-4 border-l-success',
            self::DISCONTINUED => 'border-l-4 border-l-border',
        };
    }

    /** CSS classes for the inline status badge (bg + text). */
    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-primary/10 text-primary',
            self::MASTERED => 'bg-success/15 text-success',
            self::DISCONTINUED => 'bg-muted text-foreground/50',
        };
    }

    /** CSS class for the small status dot. */
    public function dotColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'bg-primary',
            self::MASTERED => 'bg-success',
            self::DISCONTINUED => 'bg-foreground/30',
        };
    }

    /** URL/filter-friendly slug. */
    public function slug(): string
    {
        return $this->value;
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
