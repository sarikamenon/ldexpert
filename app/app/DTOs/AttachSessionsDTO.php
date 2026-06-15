<?php

declare(strict_types=1);

namespace App\DTOs;

final class AttachSessionsDTO
{
    /**
     * @param  array<int>  $sessionLogIds
     */
    public function __construct(
        public readonly array $sessionLogIds,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $ids = isset($data['session_log_ids']) && is_array($data['session_log_ids'])
            ? array_values(array_map(fn ($id) => (int) $id, $data['session_log_ids']))
            : [];

        return new self(sessionLogIds: $ids);
    }

    /**
     * @return array<string, mixed>
     */
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'session_log_ids' => $this->sessionLogIds,
        ];
    }
}
