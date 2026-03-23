<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SessionLogImportType;
use Illuminate\Http\UploadedFile;

final class StoreSessionLogImportDTO
{
    public function __construct(
        public readonly UploadedFile $file,
        public readonly int $userId,
        public readonly SessionLogImportType $type,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            file: $data['file'],
            userId: (int) $data['user_id'],
            type: SessionLogImportType::from($data['type'] ?? SessionLogImportType::RSM->value),
        );
    }
}
