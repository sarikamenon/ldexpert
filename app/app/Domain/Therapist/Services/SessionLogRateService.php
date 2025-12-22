<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Contract\Repositories\SchoolContractRepositoryInterface;
use App\Domain\Contract\Repositories\TherapistContractRepositoryInterface;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\Enums\RateType;

class SessionLogRateService
{
    public function __construct(
        private readonly TherapistRepositoryInterface $therapistRepository,
        private readonly TherapistContractRepositoryInterface $therapistContractRepository,
        private readonly SchoolContractRepositoryInterface $schoolContractRepository,
    ) {}

    /**
     * Get therapist rate for a service on a specific date
     *
     * @param  int  $therapistUserId  User ID of the therapist
     * @return array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null}
     */
    public function getTherapistRate(int $therapistUserId, int $serviceId, string $sessionDate): array
    {
        $therapistProfile = $this->therapistRepository->findProfileByUserId($therapistUserId);

        if (! $therapistProfile) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        $contract = $this->therapistContractRepository->findActiveContractForDate($therapistProfile->id, $sessionDate);

        if (! $contract) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        $serviceRate = $this->therapistContractRepository->getServiceRate($contract->id, $serviceId);

        if (! $serviceRate) {
            return [
                'contract_id' => $contract->id,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        return [
            'contract_id' => $contract->id,
            'rate_type' => $serviceRate['rate_type'],
            'rate_amount' => $serviceRate['rate_amount'],
        ];
    }

    /**
     * Get school rate for a service on a specific date
     *
     * @return array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null}
     */
    public function getSchoolRate(int $schoolId, int $serviceId, string $sessionDate): array
    {
        $contract = $this->schoolContractRepository->findActiveContractForDate($schoolId, $sessionDate);

        if (! $contract) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        $serviceRate = $this->schoolContractRepository->getServiceRate($contract->id, $serviceId);

        if (! $serviceRate) {
            return [
                'contract_id' => $contract->id,
                'rate_type' => null,
                'rate_amount' => null,
            ];
        }

        return [
            'contract_id' => $contract->id,
            'rate_type' => $serviceRate['rate_type'],
            'rate_amount' => $serviceRate['rate_amount'],
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
     * @param  int  $therapistUserId  User ID of the therapist
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
