<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\DTOs\BillingEntryWindowDTO;
use App\Exceptions\BillingWindowClosedException;
use Carbon\Carbon;

final class BillingEntryWindowService
{
    /**
     * Check whether a session date falls within the billing entry window.
     *
     * Work week: Monday–Sunday. Cutoff: end of the following Wednesday (app timezone).
     */
    public function checkWindow(Carbon $sessionDate, ?Carbon $now = null): BillingEntryWindowDTO
    {
        $appTz = (string) config('app.timezone', 'UTC');
        $now = ($now ?? Carbon::now($appTz))->copy()->setTimezone($appTz);

        $weekStart = $sessionDate->copy()->setTimezone($appTz)->startOfWeek(Carbon::MONDAY);
        $cutoff = $weekStart->copy()->addDays(9)->endOfDay();

        return new BillingEntryWindowDTO(
            sessionDate: $sessionDate->format('Y-m-d'),
            weekStart: $weekStart->format('Y-m-d'),
            cutoff: $cutoff->format('Y-m-d H:i:s'),
            isWithinWindow: $now->lte($cutoff),
        );
    }

    /**
     * Assert the session date is within the billing entry window, or throw.
     *
     * @throws BillingWindowClosedException
     */
    public function assertWithinWindow(Carbon $sessionDate, ?Carbon $now = null): void
    {
        $result = $this->checkWindow($sessionDate, $now);

        if (! $result->isWithinWindow) {
            $appTz = (string) config('app.timezone', 'UTC');
            $cutoff = Carbon::parse($result->cutoff, $appTz);

            throw new BillingWindowClosedException($cutoff);
        }
    }
}
