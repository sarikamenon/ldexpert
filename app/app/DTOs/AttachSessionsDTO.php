<?php

declare(strict_types=1);

namespace App\DTOs;

final class AttachSessionsDTO
{
    /**
     * @param  array<int>  $sessionLogIds
     * @param  array<int>  $scheduleIds  Selected schedules for the advance branch (§6).
     */
    public function __construct(
        public readonly array $sessionLogIds,
        public readonly array $scheduleIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $sessionLogIds = isset($data['session_log_ids']) && is_array($data['session_log_ids'])
            ? array_values(array_map(fn ($id) => (int) $id, $data['session_log_ids']))
            : [];

        $scheduleIds = isset($data['schedule_ids']) && is_array($data['schedule_ids'])
            ? array_values(array_map(fn ($id) => (int) $id, $data['schedule_ids']))
            : [];

        return new self(sessionLogIds: $sessionLogIds, scheduleIds: $scheduleIds);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_log_ids' => $this->sessionLogIds,
            'schedule_ids' => $this->scheduleIds,
        ];
    }
}
