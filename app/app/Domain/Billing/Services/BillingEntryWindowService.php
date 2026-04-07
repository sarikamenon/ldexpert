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
     * Work week: Monday–Sunday. Cutoff: end of day on the day given by
     * config billing.entry_window_days_after_week_start (days after Monday week start, app timezone).
     */
    public function checkWindow(Carbon $sessionDate, ?Carbon $now = null): BillingEntryWindowDTO
    {
        $appTz = (string) config('app.timezone', 'UTC');
        $now = ($now ?? Carbon::now($appTz))->copy()->setTimezone($appTz);

        $daysAfterWeekStart = max(0, (int) config('billing.entry_window_days_after_week_start', 9));

        $weekStart = $sessionDate->copy()->setTimezone($appTz)->startOfWeek(Carbon::MONDAY);
        $cutoff = $weekStart->copy()->addDays($daysAfterWeekStart)->endOfDay();

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
