<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\ScheduleSubRequest;

final class SubRequestRowTransformer extends SubRequestRowBase
{
    /**
     * @return array<int, string>
     */
    public static function transform(ScheduleSubRequest $subRequest, string $viewerTz): array
    {
        $requesterName = $subRequest->requestedBy?->name ?? '—'; // @phpstan-ignore nullsafe.neverNull
        $requesterCell = '<span class="text-sm text-foreground">'.e($requesterName).'</span>';

        $acceptUrl = route('therapist.sub-requests.accept', $subRequest);
        $acceptButton = '<form method="POST" action="'.e($acceptUrl).'" class="inline" data-accept-sub-request>'
            .csrf_field()
            .'<button type="submit" '
            .'class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded bg-success text-success-foreground hover:bg-success/90 transition-colors" '
            .'aria-label="Accept sub request">'
            .'Accept'
            .'</button>'
            .'</form>';

        $declineUrl = route('therapist.sub-requests.decline', $subRequest);
        $declineButton = '<button type="button" '
            .'data-decline-url="'.e($declineUrl).'" '
            .'class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-border bg-background text-foreground hover:bg-muted transition-colors" '
            .'aria-label="Decline sub request">'
            .'Decline'
            .'</button>';

        $actionsCell = '<div class="flex items-center gap-2">'.$acceptButton.$declineButton.'</div>';

        return [
            self::dateTimeCell($subRequest, $viewerTz),
            self::studentCell($subRequest),
            self::serviceCell($subRequest),
            $requesterCell,
            self::reasonCell($subRequest),
            $actionsCell,
        ];
    }
}
