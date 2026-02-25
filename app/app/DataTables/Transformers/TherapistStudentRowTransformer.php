<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Enums\UserStatus;
use App\Models\User;
use Carbon\CarbonInterface;

final class TherapistStudentRowTransformer
{
    /**
     * @return array<int, string> 8 cell HTML strings in column order (ID, Name, Email, School, Grade, DOB, Status, Actions)
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
        $iconView = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        $actions = '<div class="flex space-x-1">'
            .'<a href="'.e($showUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors" title="View Student">'.$iconView.'</a></div>';

        return [
            '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90" title="View Student" aria-label="View student '.e($student->name).'">'.(int) $student->id.'</a>',
            '<a href="'.e($showUrl).'" class="text-primary hover:underline font-medium">'.e($student->name).'</a>',
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
