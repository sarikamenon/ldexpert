<?php

declare(strict_types=1);

namespace App\DTOs\Student\Duplicate;

use App\Models\StudentProfile;
use Illuminate\Support\Facades\Config;

/**
 * One existing student that passed the name gate, shaped for display in the warning.
 *
 * No match percentage — the warning is boolean. Every field other than the name is
 * decision context for the admin.
 */
final class DuplicateMatchDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly ?string $username,
        public readonly ?string $schoolName,
        public readonly ?string $email,
        public readonly ?string $dateOfBirth,
        public readonly ?string $gradeLevel,
        public readonly string $showUrl,
    ) {}

    public static function fromProfile(StudentProfile $profile): self
    {
        $name = trim(($profile->first_name ?? '').' '.($profile->last_name ?? ''));
        $dateFormat = (string) Config::get('display.date', 'M d, Y');

        return new self(
            userId: $profile->user_id,
            name: $name,
            username: $profile->user?->username,
            schoolName: $profile->school?->display_name,
            email: $profile->user?->email,
            dateOfBirth: $profile->date_of_birth?->format($dateFormat),
            gradeLevel: $profile->grade_level,
            showUrl: route('admin.students.show', $profile->user_id),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->name,
            'username' => $this->username,
            'school_name' => $this->schoolName,
            'email' => $this->email,
            'date_of_birth' => $this->dateOfBirth,
            'grade_level' => $this->gradeLevel,
            'show_url' => $this->showUrl,
        ];
    }
}
