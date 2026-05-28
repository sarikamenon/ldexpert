<?php

declare(strict_types=1);

namespace App\DTOs\Schedule\Makeup;

use App\Http\Requests\Therapist\StoreMakeupAvailabilityRequest;

final class StoreMakeupAvailabilityDTO
{
    public function __construct(
        public readonly string $date,
        public readonly string $startTime,
        public readonly string $endTime,
        public readonly ?string $notes,
    ) {}

    public static function fromRequest(StoreMakeupAvailabilityRequest $request): self
    {
        /** @var array{availability_date: string, start_time: string, end_time: string, notes: ?string} $data */
        $data = $request->validated();

        return new self(
            date: $data['availability_date'],
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            notes: $data['notes'] ?? null,
        );
    }
}
