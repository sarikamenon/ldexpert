<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\RateType;

final class UpdateSessionLogDTO
{
    public function __construct(
        public readonly ?int $studentId,
        public readonly ?int $ssaId,
        public readonly ?int $serviceId,
        public readonly ?string $sessionDate,
        public readonly ?string $startTime,
        public readonly ?string $endTime,
        public readonly ?int $durationMinutes,
        public readonly ?int $thoMinutes,
        public readonly ?string $notes,
        public readonly ?bool $isBillableTherapist,
        public readonly ?int $therapistContractId,
        public readonly ?RateType $therapistRateType,
        public readonly ?float $therapistRateAmount,
        public readonly ?float $therapistBillableAmount,
        public readonly ?bool $isBillableSchool,
        public readonly ?int $schoolContractId,
        public readonly ?RateType $schoolRateType,
        public readonly ?float $schoolRateAmount,
        public readonly ?float $schoolInvoiceAmount,
        public readonly ?bool $isRateOverride,
        public readonly ?string $overrideReason,
        public readonly ?string $cancellationReason,
    ) {}

    public static function fromArray(array $data): self
    {
        $therapistRateType = isset($data['therapist_rate_type']) && $data['therapist_rate_type'] !== ''
            ? ($data['therapist_rate_type'] instanceof RateType
                ? $data['therapist_rate_type']
                : RateType::from($data['therapist_rate_type']))
            : null;

        $schoolRateType = isset($data['school_rate_type']) && $data['school_rate_type'] !== ''
            ? ($data['school_rate_type'] instanceof RateType
                ? $data['school_rate_type']
                : RateType::from($data['school_rate_type']))
            : null;

        return new self(
            studentId: isset($data['student_id']) && $data['student_id'] !== ''
                ? (int) $data['student_id']
                : null,
            ssaId: isset($data['ssa_id']) && $data['ssa_id'] !== ''
                ? (int) $data['ssa_id']
                : null,
            serviceId: isset($data['service_id']) && $data['service_id'] !== ''
                ? (int) $data['service_id']
                : null,
            sessionDate: $data['session_date'] ?? null,
            startTime: $data['start_time'] ?? null,
            endTime: $data['end_time'] ?? null,
            durationMinutes: isset($data['duration_minutes']) && $data['duration_minutes'] !== ''
                ? (int) $data['duration_minutes']
                : null,
            thoMinutes: isset($data['tho_minutes']) && $data['tho_minutes'] !== ''
                ? (int) $data['tho_minutes']
                : null,
            notes: $data['notes'] ?? null,
            isBillableTherapist: isset($data['is_billable_therapist'])
                ? (bool) $data['is_billable_therapist']
                : null,
            therapistContractId: isset($data['therapist_contract_id']) && $data['therapist_contract_id'] !== ''
                ? (int) $data['therapist_contract_id']
                : null,
            therapistRateType: $therapistRateType,
            therapistRateAmount: isset($data['therapist_rate_amount']) && $data['therapist_rate_amount'] !== ''
                ? (float) $data['therapist_rate_amount']
                : null,
            therapistBillableAmount: isset($data['therapist_billable_amount']) && $data['therapist_billable_amount'] !== ''
                ? (float) $data['therapist_billable_amount']
                : null,
            isBillableSchool: isset($data['is_billable_school']) !== null
                ? (bool) $data['is_billable_school']
                : null,
            schoolContractId: isset($data['school_contract_id']) && $data['school_contract_id'] !== ''
                ? (int) $data['school_contract_id']
                : null,
            schoolRateType: $schoolRateType,
            schoolRateAmount: isset($data['school_rate_amount']) && $data['school_rate_amount'] !== ''
                ? (float) $data['school_rate_amount']
                : null,
            schoolInvoiceAmount: isset($data['school_invoice_amount']) && $data['school_invoice_amount'] !== ''
                ? (float) $data['school_invoice_amount']
                : null,
            isRateOverride: isset($data['is_rate_override'])
                ? (bool) $data['is_rate_override']
                : null,
            overrideReason: isset($data['override_reason']) && $data['override_reason'] !== ''
                ? $data['override_reason']
                : null,
            cancellationReason: isset($data['cancellation_reason']) && $data['cancellation_reason'] !== ''
                ? $data['cancellation_reason']
                : null,
        );
    }

    public function toArray(): array
    {
        $array = [];

        if ($this->studentId !== null) {
            $array['student_id'] = $this->studentId;
        }
        if ($this->ssaId !== null) {
            $array['ssa_id'] = $this->ssaId;
        }
        if ($this->serviceId !== null) {
            $array['service_id'] = $this->serviceId;
        }
        if ($this->sessionDate !== null) {
            $array['session_date'] = $this->sessionDate;
        }
        if ($this->startTime !== null) {
            $array['start_time'] = $this->startTime;
        }
        if ($this->endTime !== null) {
            $array['end_time'] = $this->endTime;
        }
        if ($this->durationMinutes !== null) {
            $array['duration_minutes'] = $this->durationMinutes;
        }
        if ($this->thoMinutes !== null) {
            $array['tho_minutes'] = $this->thoMinutes;
        }
        if ($this->notes !== null) {
            $array['notes'] = $this->notes;
        }
        if ($this->isBillableTherapist !== null) {
            $array['is_billable_therapist'] = $this->isBillableTherapist;
        }
        if ($this->therapistContractId !== null) {
            $array['therapist_contract_id'] = $this->therapistContractId;
        }
        if ($this->therapistRateType !== null) {
            $array['therapist_rate_type'] = $this->therapistRateType->value;
        }
        if ($this->therapistRateAmount !== null) {
            $array['therapist_rate_amount'] = $this->therapistRateAmount;
        }
        if ($this->therapistBillableAmount !== null) {
            $array['therapist_billable_amount'] = $this->therapistBillableAmount;
        }
        if ($this->isBillableSchool !== null) {
            $array['is_billable_school'] = $this->isBillableSchool;
        }
        if ($this->schoolContractId !== null) {
            $array['school_contract_id'] = $this->schoolContractId;
        }
        if ($this->schoolRateType !== null) {
            $array['school_rate_type'] = $this->schoolRateType->value;
        }
        if ($this->schoolRateAmount !== null) {
            $array['school_rate_amount'] = $this->schoolRateAmount;
        }
        if ($this->schoolInvoiceAmount !== null) {
            $array['school_invoice_amount'] = $this->schoolInvoiceAmount;
        }
        if ($this->isRateOverride !== null) {
            $array['is_rate_override'] = $this->isRateOverride;
        }
        if ($this->overrideReason !== null) {
            $array['override_reason'] = $this->overrideReason;
        }
        if ($this->cancellationReason !== null) {
            $array['cancellation_reason'] = $this->cancellationReason;
        }

        return $array;
    }
}
