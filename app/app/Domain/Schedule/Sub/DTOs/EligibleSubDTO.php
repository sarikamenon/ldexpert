<?php

declare(strict_types=1);

namespace App\Domain\Schedule\Sub\DTOs;

/**
 * Picker-facing view of an eligible substitute therapist for a schedule.
 *
 * The repository used to mutate User Eloquent models with a transient
 * `invitee_status` property — that leaks into JSON serializers and pollutes
 * the model. We return this DTO instead so callers see a flat, stable shape.
 */
final class EligibleSubDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        /** 'selected' | 'declined' | 'none' */
        public readonly string $inviteeStatus,
    ) {}

    /** @return array{id: int, name: string, invitee_status: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'invitee_status' => $this->inviteeStatus,
        ];
    }
}
