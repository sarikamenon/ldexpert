<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\QGlobRequestStatus;

final class QGlobRequestFilterDTO
{
    public function __construct(
        public readonly ?int $therapistId,
        public readonly ?QGlobRequestStatus $status,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $status = null;
        if (isset($data['status']) && $data['status'] !== '' && $data['status'] !== null) {
            $status = QGlobRequestStatus::from((string) $data['status']);
        }

        return new self(
            therapistId: isset($data['therapist_id']) && $data['therapist_id'] !== '' && $data['therapist_id'] !== null
                ? (int) $data['therapist_id']
                : null,
            status: $status,
            dateFrom: isset($data['date_from']) && $data['date_from'] !== '' ? (string) $data['date_from'] : null,
            dateTo: isset($data['date_to']) && $data['date_to'] !== '' ? (string) $data['date_to'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'therapist_id' => $this->therapistId,
            'status' => $this->status?->value,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }
}
