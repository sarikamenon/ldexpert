<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\SSAImportType;
use Illuminate\Http\UploadedFile;

final class StoreSSAImportDTO
{
    public function __construct(
        public readonly UploadedFile $file,
        public readonly int $userId,
        public readonly SSAImportType $type,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            file: $data['file'],
            userId: (int) $data['user_id'],
            type: SSAImportType::from($data['type'] ?? SSAImportType::NOVA->value),
        );
    }
}
