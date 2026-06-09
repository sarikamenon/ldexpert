<?php

declare(strict_types=1);

namespace App\DTOs\Student\Duplicate;

/**
 * The student being created or edited, expressed as the fields the duplicate check needs.
 *
 * firstName + lastName drive the name gate (the only trigger). The remaining fields are
 * display-only context surfaced in the warning so the admin can adjudicate.
 */
final class DuplicateCandidateDTO
{
    public function __construct(
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $email,
        public readonly ?int $schoolId,
        public readonly ?string $dateOfBirth,
        public readonly ?string $gradeLevel,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: trim((string) ($data['first_name'] ?? '')),
            lastName: trim((string) ($data['last_name'] ?? '')),
            email: ($data['email'] ?? null) ?: null,
            schoolId: isset($data['school_id']) ? (int) $data['school_id'] : null,
            dateOfBirth: ($data['date_of_birth'] ?? null) ?: null,
            gradeLevel: ($data['grade_level'] ?? null) ?: null,
        );
    }
}
