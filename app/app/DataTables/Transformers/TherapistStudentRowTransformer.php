<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonInterface;

final class TherapistStudentRowTransformer
{
    /**
     * @return array<int, string> 9 cell HTML strings in column order (ID, Name, Username, Email, School, Grade, DOB, Status, Actions)
     */
    public static function transform(User $student): array
    {
        $profile = $student->studentProfile;
        $statusEnum = $student->status;
        $isActive = $statusEnum === UserStatus::ACTIVE;
        $statusLabel = ucfirst($statusEnum->value);
        $showUrl = route('therapist.students.show', $student);
        $school = $profile?->school;
        $schoolCell = $school ? e($school->display_name) : '—';
        $badgeClass = $isActive ? 'bg-success/10 text-success border border-success/20' : 'bg-danger/10 text-danger border border-danger/20';
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.$statusLabel.'</span>';

        $actions = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View Student'),
        );

        return [
            '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90" title="View Student" aria-label="View student '.e($student->name).'">'.(int) $student->id.'</a>',
            '<a href="'.e($showUrl).'" class="text-primary hover:underline font-medium">'.e($student->name).'</a>',
            e($student->username),
            e($student->email),
            $schoolCell,
            e($profile ? $profile->grade_level ?? '—' : '—'),
            self::formatDateOfBirth($profile?->date_of_birth),
            $statusBadge,
            $actions,
        ];
    }

    private static function formatDateOfBirth(mixed $date): string
    {
        if ($date instanceof CarbonInterface) {
            return $date->format('Y-m-d');
        }

        return $date !== null ? (string) $date : '—';
    }
}
