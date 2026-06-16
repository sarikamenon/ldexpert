<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\User;

final class StudentRowTransformer
{
    /**
     * @return array<int, string> 10 cell HTML strings in column order
     */
    public static function transform(User $student): array
    {
        $profile = $student->studentProfile;
        $isActive = ($student->status->value ?? 'inactive') === 'active';
        $statusLabel = ucfirst($student->status->value ?? 'inactive');
        $showUrl = route('admin.students.show', $student);
        $editUrl = route('admin.students.edit', $student);
        $school = $profile?->school;
        $schoolName = $school ? e($school->display_name) : '—';
        $schoolCell = $school
            ? '<a href="'.e(route('admin.schools.show', $school)).'" class="text-primary hover:underline">'.$schoolName.'</a>'
            : '—';
        $badgeClass = $isActive ? 'bg-success/10 text-success border border-success/20' : 'bg-danger/10 text-danger border border-danger/20';
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.$statusLabel.'</span>';

        $toggleAttrs = ['data-student' => (int) $student->id, 'data-status' => e($student->status->value ?? 'inactive'), 'dusk' => 'student-status-toggle-'.(int) $student->id, 'class' => 'toggle-student-status'];
        $toggleBtn = $isActive
            ? ActionButtons::deactivate('Deactivate Student', $toggleAttrs)
            : ActionButtons::activate('Activate Student', $toggleAttrs);

        $actions = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View Student'),
            ActionButtons::edit($editUrl, 'Edit Student', ['dusk' => 'edit-student-'.(int) $student->id]),
            $toggleBtn,
        );

        $parentUser = $profile?->parent;
        $parentName = $parentUser
            ? e($parentUser->name)
            : e($profile !== null ? ($profile->parent_guardian_name ?? '—') : '—');

        return [
            '<input type="checkbox" class="student-select" value="'.(int) $student->id.'" aria-label="Select student '.e($student->name).'">',
            '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90" title="View Student" aria-label="View student '.e($student->name).'">'.(int) $student->id.'</a>',
            '<a href="'.e($showUrl).'" class="text-primary hover:underline font-medium">'.e($student->name).'</a>',
            $parentName,
            e($student->email),
            $schoolCell,
            e($profile->grade_level ?? '—'),
            $profile?->date_of_birth?->format('Y-m-d') ?? '—',
            $statusBadge,
            $actions,
        ];
    }
}
