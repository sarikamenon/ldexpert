<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use Illuminate\Support\Collection;

final class SSACaseloadReportRowTransformer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function transform(array $data): array
    {
        $therapistName = e($data['therapist']->name ?? '—');

        /** @var Collection<int, \App\Models\School> $schools */
        $schools = $data['schools'] ?? collect();
        $schoolBadges = $schools->map(
            static fn ($school): string => '<span class="inline-block bg-background border border-border rounded px-2 py-1 text-xs mr-1 mb-1">'.e($school->display_name).'</span>'
        )->implode('');

        $ssaCount = (string) ($data['active_ssa_count'] ?? 0);
        $minutesPerWeek = number_format((float) ($data['authorized_minutes_per_week'] ?? 0), 0);

        return [
            $therapistName,
            $schoolBadges,
            $ssaCount,
            $minutesPerWeek,
        ];
    }
}
