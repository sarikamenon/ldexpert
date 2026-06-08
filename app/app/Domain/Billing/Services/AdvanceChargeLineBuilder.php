<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Therapist\Services\SessionLogRateService;
use App\DTOs\InvoiceLineItemDTO;
use App\Enums\InvoiceLineType;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Builds ADVANCE_SCHEDULED charge lines from a school's not-yet-invoiced
 * scheduled sessions for a period. Extracted so both the automated path
 * (AdvanceBillingService) and the manual path (InvoiceService) share identical
 * rate + description logic without a service dependency cycle.
 */
final class AdvanceChargeLineBuilder
{
    public function __construct(
        private readonly SessionLogRateService $rateService,
    ) {}

    /**
     * @return Collection<int, InvoiceLineItemDTO>
     */
    public function build(int $schoolId, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        /** @var Collection<int, InvoiceLineItemDTO> $lines */
        $lines = collect();

        /** @var Collection<int, Schedule> $schedules */
        $schedules = Schedule::query()
            ->where('school_id', $schoolId)
            ->betweenScheduleDates($periodStart->toDateString(), $periodEnd->toDateString())
            ->scheduled()
            ->notYetInvoiced()
            ->with(['service', 'therapist', 'school'])
            ->orderBy('schedule_date')
            ->get();

        foreach ($schedules as $schedule) {
            $rate = $this->getScheduleRate($schedule);

            if ($rate === null) {
                Log::warning('Could not determine rate for scheduled session', [
                    'schedule_id' => $schedule->id,
                    'school_id' => $schoolId,
                ]);

                continue;
            }

            $serviceName = $schedule->service->name ?? 'Session';
            $date = $schedule->schedule_date->format('D M j');
            $duration = $schedule->durationMinutes();
            $isGroup = $schedule->is_group ? ' (group)' : '';

            $lines->push(new InvoiceLineItemDTO(
                lineType: InvoiceLineType::ADVANCE_SCHEDULED->value,
                description: "{$serviceName} — {$date} ({$duration} min){$isGroup}",
                billingPeriodStart: $periodStart->toDateString(),
                billingPeriodEnd: $periodEnd->toDateString(),
                quantity: 1,
                unitPrice: $rate,
                total: $rate,
                scheduleId: $schedule->id,
            ));
        }

        return $lines;
    }

    /**
     * Get the school billing rate for a scheduled session using the rate service.
     */
    private function getScheduleRate(Schedule $schedule): ?float
    {
        try {
            $durationMinutes = $schedule->durationMinutes();
            $result = $this->rateService->calculateDualBilling(
                $schedule->therapist_id,
                $schedule->school_id,
                $schedule->service_id,
                $schedule->schedule_date->toDateString(),
                $durationMinutes,
            );

            return $result['school']['invoice_amount'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('Rate calculation failed for schedule', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
