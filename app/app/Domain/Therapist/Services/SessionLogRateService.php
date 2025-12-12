<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Models\SchoolContract;
use App\Models\SchoolContractService;
use App\Models\TherapistContract;
use App\Models\TherapistContractService;
use App\Models\TherapistProfile;
use App\Models\User;
use Carbon\Carbon;

final class SessionLogRateService
{
    /**
     * Get therapist rate for a service on a specific date
     *
     * @param int $therapistUserId User ID of the therapist
     * @return array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null}
     */
    public function getTherapistRate(int $therapistUserId, int $serviceId, string $sessionDate): array
    {
        $date = Carbon::parse($sessionDate);

        // Get therapist profile ID from user ID
        $therapistProfile = TherapistProfile::query()
            ->where('user_id', $therapistUserId)
            ->first();

        if (! $therapistProfile) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        // Find active therapist contract that covers the session date
        // Note: therapist_contracts.therapist_id references therapist_profiles.id, not users.id
        $contract = TherapistContract::query()
            ->where('therapist_id', $therapistProfile->id)
            ->where('status', ContractStatus::ACTIVE)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if (! $contract) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        // Find service rate in contract
        $contractService = TherapistContractService::query()
            ->where('therapist_contract_id', $contract->id)
            ->where('service_id', $serviceId)
            ->first();

        if (! $contractService) {
            return [
                'contract_id' => $contract->id,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        return [
            'contract_id' => $contract->id,
            'rate_type' => $contractService->rate_type,
            'rate_amount' => (float) $contractService->rate,
        ];
    }

    /**
     * Get school rate for a service on a specific date
     *
     * @return array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null}
     */
    public function getSchoolRate(int $schoolId, int $serviceId, string $sessionDate): array
    {
        $date = Carbon::parse($sessionDate);

        // Find active school contract that covers the session date
        $contract = SchoolContract::query()
            ->where('school_id', $schoolId)
            ->where('status', ContractStatus::ACTIVE)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        if (! $contract) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        // Find service rate in contract
        $contractService = SchoolContractService::query()
            ->where('school_contract_id', $contract->id)
            ->where('service_id', $serviceId)
            ->first();

        if (! $contractService) {
            return [
                'contract_id' => $contract->id,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        return [
            'contract_id' => $contract->id,
            'rate_type' => $contractService->rate_type,
            'rate_amount' => (float) $contractService->rate,
        ];
    }

    /**
     * Calculate billable amount based on rate type and duration
     */
    public function calculateBillableAmount(RateType $rateType, float $rateAmount, int $durationMinutes): float
    {
        return match ($rateType) {
            RateType::HOURLY => round($rateAmount * ($durationMinutes / 60), 2),
            RateType::FLAT => round($rateAmount, 2),
        };
    }

    /**
     * Calculate both therapist and school billing amounts
     *
     * @param int $therapistUserId User ID of the therapist
     * @return array{
     *     therapist: array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null, billable_amount: float|null},
     *     school: array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null, invoice_amount: float|null}
     * }
     */
    public function calculateDualBilling(
        int $therapistUserId,
        int $schoolId,
        int $serviceId,
        string $sessionDate,
        int $durationMinutes
    ): array {
        $therapistRate = $this->getTherapistRate($therapistUserId, $serviceId, $sessionDate);
        $schoolRate = $this->getSchoolRate($schoolId, $serviceId, $sessionDate);

        $therapistBillableAmount = null;
        if ($therapistRate['rate_type'] && $therapistRate['rate_amount']) {
            $therapistBillableAmount = $this->calculateBillableAmount(
                $therapistRate['rate_type'],
                $therapistRate['rate_amount'],
                $durationMinutes
            );
        }

        $schoolInvoiceAmount = null;
        if ($schoolRate['rate_type'] && $schoolRate['rate_amount']) {
            $schoolInvoiceAmount = $this->calculateBillableAmount(
                $schoolRate['rate_type'],
                $schoolRate['rate_amount'],
                $durationMinutes
            );
        }

        return [
            'therapist' => [
                'contract_id' => $therapistRate['contract_id'],
                'rate_type' => $therapistRate['rate_type'],
                'rate_amount' => $therapistRate['rate_amount'],
                'billable_amount' => $therapistBillableAmount,
            ],
            'school' => [
                'contract_id' => $schoolRate['contract_id'],
                'rate_type' => $schoolRate['rate_type'],
                'rate_amount' => $schoolRate['rate_amount'],
                'invoice_amount' => $schoolInvoiceAmount,
            ],
        ];
    }
}
