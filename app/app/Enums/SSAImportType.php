<?php

declare(strict_types=1);

namespace App\Enums;

enum SSAImportType: string
{
    case NOVA = 'NOVA';
    case RSM = 'RSM';
    case MARVIN = 'MARVIN';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Tailwind class list for a source badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::RSM => 'bg-primary/10 text-primary border-primary/20',
            self::NOVA => 'bg-success/10 text-success border-success/20',
            self::MARVIN => 'bg-warning/10 text-warning border-warning/20',
        };
    }

    /**
     * Badge classes with a safe fallback for unknown values.
     */
    public static function badgeClassesFor(string $source): string
    {
        return self::tryFrom($source)?->badgeClasses()
            ?? 'bg-secondary/10 text-foreground border-secondary/20';
    }

    /**
     * Sources that support service alias mappings.
     *
     * NOVA uses the same service names as the system, so no aliases are needed.
     *
     * @return array<int, self>
     */
    public static function aliasSources(): array
    {
        return [
            self::RSM,
            self::MARVIN,
        ];
    }
}
