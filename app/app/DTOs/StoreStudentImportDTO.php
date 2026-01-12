<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\StudentImportType;
use Illuminate\Http\UploadedFile;

final class StoreStudentImportDTO
{
    public function __construct(
        public readonly UploadedFile $file,
        public readonly int $userId,
        public readonly StudentImportType $type,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            file: $data['file'],
            userId: (int) $data['user_id'],
            type: StudentImportType::from($data['type'] ?? StudentImportType::NOVA->value),
        );
    }
}
