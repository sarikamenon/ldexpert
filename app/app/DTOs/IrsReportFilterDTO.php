<?php

declare(strict_types=1);

namespace App\DTOs;

final class IrsReportFilterDTO
{
    /**
     * @param  array<int>|null  $therapistIds  Null or empty = all therapists
     */
    public function __construct(
        public readonly ?array $therapistIds,
        public readonly ?string $dateFrom,
        public readonly ?string $dateTo,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $therapistIds = null;
        if (isset($data['therapist_ids']) && is_array($data['therapist_ids']) && $data['therapist_ids'] !== []) {
            $therapistIds = array_map('intval', $data['therapist_ids']);
        }

        return new self(
            therapistIds: $therapistIds,
            dateFrom: isset($data['date_from']) && $data['date_from'] !== '' ? (string) $data['date_from'] : null,
            dateTo: isset($data['date_to']) && $data['date_to'] !== '' ? (string) $data['date_to'] : null,
        );
    }
}
