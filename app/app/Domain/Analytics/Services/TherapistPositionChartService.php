<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Models\TherapistProfile;
use Illuminate\Support\Facades\DB;

final class TherapistPositionChartService
{
    private const POSITION_EXPRESSION = "COALESCE(positions.name, 'Unassigned')";

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function getCounts(): array
    {
        $rows = TherapistProfile::query()
            ->leftJoin('positions', 'therapist_profiles.position_id', '=', 'positions.id')
            ->selectRaw(self::POSITION_EXPRESSION.' as position, count(*) as count')
            ->groupBy(DB::raw(self::POSITION_EXPRESSION))
            ->orderBy('position')
            ->get();

        return [
            'labels' => $rows->pluck('position')->map(static fn ($label): string => (string) $label)->values()->all(),
            'data' => $rows->pluck('count')->map(static fn ($count): int => (int) $count)->values()->all(),
        ];
    }
}
