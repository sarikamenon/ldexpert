<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\QGlobRequestStatus;

final class RespondQGlobRequestDTO
{
    public function __construct(
        public readonly QGlobRequestStatus $status,
        public readonly ?string $adminResponse,
        public readonly int $respondedById,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: QGlobRequestStatus::from((string) $data['status']),
            adminResponse: isset($data['admin_response']) ? (string) $data['admin_response'] : null,
            respondedById: (int) $data['responded_by_id'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'admin_response' => $this->adminResponse,
            'responded_by_id' => $this->respondedById,
        ];
    }
}
