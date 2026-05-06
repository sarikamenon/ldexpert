<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\CashDirection;

final class AllTransactionsFilterDTO
{
    public function __construct(
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
        public readonly ?CashDirection $direction,
        public readonly ?int $schoolId,
        public readonly ?int $therapistId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $direction = null;
        if (isset($data['filter_direction']) && $data['filter_direction'] !== '') {
            $direction = CashDirection::tryFrom((string) $data['filter_direction']);
        }

        return new self(
            dateFrom: isset($data['filter_date_from']) && $data['filter_date_from'] !== ''
                ? (string) $data['filter_date_from']
                : null,
            dateTo: isset($data['filter_date_to']) && $data['filter_date_to'] !== ''
                ? (string) $data['filter_date_to']
                : null,
            direction: $direction,
            schoolId: isset($data['filter_school_id']) && $data['filter_school_id'] !== ''
                ? (int) $data['filter_school_id']
                : null,
            therapistId: isset($data['filter_therapist_id']) && $data['filter_therapist_id'] !== ''
                ? (int) $data['filter_therapist_id']
                : null,
        );
    }
}
