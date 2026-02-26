<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\Contract\Repositories\SchoolContractRepositoryInterface;
use App\Domain\Contract\Repositories\TherapistContractRepositoryInterface;
use App\Domain\School\Repositories\SchoolRepositoryInterface;
use App\Domain\Therapist\Repositories\TherapistRepositoryInterface;
use App\Enums\RateType;
use App\Enums\SessionOutcome;
use Illuminate\Validation\ValidationException;

class SessionLogRateService
{
    public function __construct(
        private readonly TherapistRepositoryInterface $therapistRepository,
        private readonly TherapistContractRepositoryInterface $therapistContractRepository,
        private readonly SchoolContractRepositoryInterface $schoolContractRepository,
        private readonly SchoolRepositoryInterface $schoolRepository,
    ) {}

    /**
     * Get therapist rate for a service on a specific date (includes no-show rate for outcome-based billing).
     *
     * @param  int  $therapistUserId  User ID of the therapist
     * @return array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null, no_show_rate: float|null, no_show_rate_type: RateType|null}
     */
    public function getTherapistRate(int $therapistUserId, int $serviceId, string $sessionDate): array
    {
        $therapistProfile = $this->therapistRepository->findProfileByUserId($therapistUserId);

        if (! $therapistProfile) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
                'no_show_rate' => null,
                'no_show_rate_type' => null,
            ];
        }

        $contract = $this->therapistContractRepository->findActiveContractForDate($therapistProfile->id, $sessionDate);

        if (! $contract) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
                'no_show_rate' => null,
                'no_show_rate_type' => null,
            ];
        }

        $serviceRate = $this->therapistContractRepository->getServiceRate($contract->id, $serviceId);

        if (! $serviceRate) {
            return [
                'contract_id' => $contract->id,
                'rate_type' => null,
                'rate_amount' => null,
                'no_show_rate' => null,
                'no_show_rate_type' => null,
            ];
        }

        return [
            'contract_id' => $contract->id,
            'rate_type' => $serviceRate['rate_type'],
            'rate_amount' => $serviceRate['rate_amount'],
            'no_show_rate' => $serviceRate['no_show_rate'] ?? null,
            'no_show_rate_type' => $serviceRate['no_show_rate_type'] ?? null,
        ];
    }

    /**
     * Get school rate for a service on a specific date (includes no-show rate for outcome-based billing).
     *
     * @return array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null, no_show_rate: float|null, no_show_rate_type: RateType|null}
     */
    public function getSchoolRate(?int $schoolId, int $serviceId, string $sessionDate): array
    {
        if ($schoolId === null) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
                'no_show_rate' => null,
                'no_show_rate_type' => null,
            ];
        }

        $contract = $this->schoolContractRepository->findActiveContractForDate($schoolId, $sessionDate);

        if (! $contract) {
            return [
                'contract_id' => null,
                'rate_type' => null,
                'rate_amount' => null,
                'no_show_rate' => null,
                'no_show_rate_type' => null,
            ];
        }

        $serviceRate = $this->schoolContractRepository->getServiceRate($contract->id, $serviceId);

        if (! $serviceRate) {
            return [
                'contract_id' => $contract->id,
                'rate_type' => null,
                'rate_amount' => null,
                'no_show_rate' => null,
                'no_show_rate_type' => null,
            ];
        }

        return [
            'contract_id' => $contract->id,
            'rate_type' => $serviceRate['rate_type'],
            'rate_amount' => $serviceRate['rate_amount'],
            'no_show_rate' => $serviceRate['no_show_rate'] ?? null,
            'no_show_rate_type' => $serviceRate['no_show_rate_type'] ?? null,
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
     * Calculate both therapist and school billing amounts based on outcome and private vs school student.
     * Private students: always use regular rate. School students: use no-show rate when outcome is NO_SHOW or BILLABLE_CANCELLATION.
     *
     * @param  int  $therapistUserId  User ID of the therapist
     * @return array{
     *     therapist: array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null, billable_amount: float|null},
     *     school: array{contract_id: int|null, rate_type: RateType|null, rate_amount: float|null, invoice_amount: float|null}
     * }
     */
    public function calculateDualBilling(
        int $therapistUserId,
        ?int $schoolId,
        int $serviceId,
        string $sessionDate,
        int $durationMinutes,
        SessionOutcome $outcome = SessionOutcome::SERVICES_ADMINISTERED
    ): array {
        $school = $schoolId ? $this->schoolRepository->find($schoolId) : null;
        $isPrivateStudent = $school->is_private_student ?? false;

        $therapistRate = $this->getTherapistRate($therapistUserId, $serviceId, $sessionDate);
        $schoolRate = $this->getSchoolRate($schoolId, $serviceId, $sessionDate);

        $useNoShowTherapist = false;
        $useNoShowSchool = false;
        if (! $isPrivateStudent && $outcome->paysNoShowRate()) {
            if ($therapistRate['no_show_rate_type'] === null || $therapistRate['no_show_rate'] === null) {
                throw ValidationException::withMessages([
                    'outcome' => [
                        'The selected outcome requires a no-show rate. Please add the therapist no-show rate for this service in the therapist contract (Admin → Contracts → Therapist Contracts).',
                    ],
                ]);
            }
            if ($schoolRate['no_show_rate_type'] === null || $schoolRate['no_show_rate'] === null) {
                throw ValidationException::withMessages([
                    'outcome' => [
                        'The selected outcome requires a no-show rate. Please add the school no-show rate for this service in the school contract (Admin → Contracts → School Contracts).',
                    ],
                ]);
            }
            $useNoShowTherapist = true;
            $useNoShowSchool = true;
        }

        $therapistRateType = $useNoShowTherapist ? $therapistRate['no_show_rate_type'] : $therapistRate['rate_type'];
        $therapistRateAmount = $useNoShowTherapist ? $therapistRate['no_show_rate'] : $therapistRate['rate_amount'];
        $schoolRateType = $useNoShowSchool ? $schoolRate['no_show_rate_type'] : $schoolRate['rate_type'];
        $schoolRateAmount = $useNoShowSchool ? $schoolRate['no_show_rate'] : $schoolRate['rate_amount'];

        if ($outcome->isBillableForTherapist() && ($therapistRateType === null || $therapistRateAmount === null)) {
            throw ValidationException::withMessages([
                'service_id' => [
                    'The therapist rate for this service is not set. Please configure the service rate in the therapist contract (Admin → Contracts → Therapist Contracts).',
                ],
            ]);
        }
        if ($outcome->isBillableForSchool() && ($schoolRateType === null || $schoolRateAmount === null)) {
            throw ValidationException::withMessages([
                'service_id' => [
                    'The school rate for this service is not set. Please configure the service rate in the school contract (Admin → Contracts → School Contracts).',
                ],
            ]);
        }

        $therapistBillableAmount = null;
        if ($outcome->isBillableForTherapist() && $therapistRateType !== null && $therapistRateAmount !== null) {
            $therapistBillableAmount = $this->calculateBillableAmount(
                $therapistRateType,
                $therapistRateAmount,
                $durationMinutes
            );
        } elseif (! $outcome->isBillableForTherapist()) {
            $therapistBillableAmount = 0.0;
        }

        $schoolInvoiceAmount = null;
        if ($outcome->isBillableForSchool() && $schoolRateType !== null && $schoolRateAmount !== null) {
            $schoolInvoiceAmount = $this->calculateBillableAmount(
                $schoolRateType,
                $schoolRateAmount,
                $durationMinutes
            );
        } elseif (! $outcome->isBillableForSchool()) {
            $schoolInvoiceAmount = 0.0;
        }

        return [
            'therapist' => [
                'contract_id' => $therapistRate['contract_id'],
                'rate_type' => $therapistRateType,
                'rate_amount' => $therapistRateAmount,
                'billable_amount' => $therapistBillableAmount,
            ],
            'school' => [
                'contract_id' => $schoolRate['contract_id'],
                'rate_type' => $schoolRateType,
                'rate_amount' => $schoolRateAmount,
                'invoice_amount' => $schoolInvoiceAmount,
            ],
        ];
    }
}
