<?php

declare(strict_types=1);

namespace App\Domain\Student\Services;

use App\DTOs\Student\Duplicate\DuplicateCandidateDTO;
use App\DTOs\Student\Duplicate\DuplicateMatchDTO;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * Finds existing students that may be duplicates of one being created or edited.
 *
 * The trigger is the name gate only (first AND last name match). Email, DOB, school, and
 * grade are never part of the trigger — see ADR 0002 and docs/context/students.md.
 */
final class DuplicateStudentService
{
    /**
     * @param  int|null  $excludeUserId  the student being edited, so it never matches itself
     * @return Collection<int, DuplicateMatchDTO>
     */
    public function findMatches(DuplicateCandidateDTO $candidate, ?int $excludeUserId = null): Collection
    {
        if ($candidate->firstName === '' || $candidate->lastName === '') {
            return collect();
        }

        return StudentProfile::query()
            ->matchingName($candidate->firstName, $candidate->lastName)
            ->when(
                $excludeUserId !== null,
                static fn ($query) => $query->where('user_id', '!=', $excludeUserId),
            )
            ->with(['user', 'school'])
            ->get()
            ->map(static fn (StudentProfile $profile): DuplicateMatchDTO => DuplicateMatchDTO::fromProfile($profile))
            ->values();
    }
}
