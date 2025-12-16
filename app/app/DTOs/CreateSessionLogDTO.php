<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\RateType;
use App\Enums\SessionLogStatus;

final class CreateSessionLogDTO
{
    public function __construct(
        public readonly int $therapistId,
        public readonly int $studentId,
        public readonly int $ssaId,
        public readonly int $serviceId,
        public readonly ?int $scheduleId,
        public readonly ?int $schoolId,
        public readonly string $sessionDate,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly int $durationMinutes,
        public readonly int $thoMinutes,
        public readonly string $notes,
        public readonly bool $isBillableTherapist,
        public readonly ?int $therapistContractId,
        public readonly ?RateType $therapistRateType,
        public readonly ?float $therapistRateAmount,
        public readonly ?float $therapistBillableAmount,
        public readonly bool $isBillableSchool,
        public readonly ?int $schoolContractId,
        public readonly ?RateType $schoolRateType,
        public readonly ?float $schoolRateAmount,
        public readonly ?float $schoolInvoiceAmount,
        public readonly bool $isRateOverride,
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

        $thoMinutes = isset($data['tho_minutes']) && $data['tho_minutes'] !== ''
            ? (int) $data['tho_minutes']
            : 0;

        return new self(
            therapistId: (int) $data['therapist_id'],
            studentId: (int) $data['student_id'],
            ssaId: (int) $data['ssa_id'],
            serviceId: (int) $data['service_id'],
            scheduleId: isset($data['schedule_id']) && $data['schedule_id'] !== ''
                ? (int) $data['schedule_id']
                : null,
            schoolId: isset($data['school_id']) && $data['school_id'] !== ''
                ? (int) $data['school_id']
                : null,
            sessionDate: $data['session_date'],
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            durationMinutes: (int) $data['duration_minutes'],
            thoMinutes: $thoMinutes,
            notes: $data['notes'],
            isBillableTherapist: isset($data['is_billable_therapist'])
                ? (bool) $data['is_billable_therapist']
                : true,
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
            isBillableSchool: isset($data['is_billable_school'])
                ? (bool) $data['is_billable_school']
                : true,
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
            isRateOverride: isset($data['is_rate_override']) ? (bool) $data['is_rate_override'] : false,
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
        return [
            'therapist_id' => $this->therapistId,
            'student_id' => $this->studentId,
            'ssa_id' => $this->ssaId,
            'service_id' => $this->serviceId,
            'schedule_id' => $this->scheduleId,
            'school_id' => $this->schoolId,
            'session_date' => $this->sessionDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_minutes' => $this->durationMinutes,
            'tho_minutes' => $this->thoMinutes,
            'notes' => $this->notes,
            'delivery_mode' => 'virtual',
            'is_group' => false,
            'is_billable_therapist' => $this->isBillableTherapist,
            'therapist_contract_id' => $this->therapistContractId,
            'therapist_rate_type' => $this->therapistRateType?->value,
            'therapist_rate_amount' => $this->therapistRateAmount,
            'therapist_billable_amount' => $this->therapistBillableAmount,
            'is_billable_school' => $this->isBillableSchool,
            'school_contract_id' => $this->schoolContractId,
            'school_rate_type' => $this->schoolRateType?->value,
            'school_rate_amount' => $this->schoolRateAmount,
            'school_invoice_amount' => $this->schoolInvoiceAmount,
            'is_rate_override' => $this->isRateOverride,
            'override_reason' => $this->overrideReason,
            'cancellation_reason' => $this->cancellationReason,
            'status' => SessionLogStatus::DRAFT->value,
        ];
    }
}
