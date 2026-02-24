<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\User;

final class StudentRowTransformer
{
    /**
     * @return array<int, string> 8 cell HTML strings in column order
     */
    public static function transform(User $student): array
    {
        $profile = $student->studentProfile;
        $isActive = ($student->status?->value ?? 'inactive') === 'active';
        $statusLabel = ucfirst($student->status?->value ?? 'inactive');
        $showUrl = route('admin.students.show', $student);
        $editUrl = route('admin.students.edit', $student);
        $school = $profile?->school;
        $schoolName = $school ? e($school->display_name) : '—';
        $schoolCell = $school
            ? '<a href="'.e(route('admin.schools.show', $school)).'" class="text-primary hover:underline">'.$schoolName.'</a>'
            : '—';
        $badgeClass = $isActive ? 'bg-success/10 text-success border border-success/20' : 'bg-danger/10 text-danger border border-danger/20';
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.$statusLabel.'</span>';
        $toggleTitle = $isActive ? 'Deactivate Student' : 'Activate Student';
        $toggleClass = $isActive ? 'bg-danger text-danger-foreground hover:bg-danger/90' : 'bg-success text-success-foreground hover:bg-success/90';
        $iconView = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        $iconEdit = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        $iconDeactivate = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        $iconActivate = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>';
        $toggleIcon = $isActive ? $iconDeactivate : $iconActivate;
        $actions = '<div class="flex space-x-1">'
            .'<a href="'.e($showUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-secondary text-white rounded hover:bg-secondary/90 transition-colors" title="View Student">'.$iconView.'</a>'
            .'<a href="'.e($editUrl).'" class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors" title="Edit Student" dusk="edit-student-'.(int) $student->id.'">'.$iconEdit.'</a>'
            .'<button type="button" data-student="'.(int) $student->id.'" data-status="'.e($student->status?->value ?? 'inactive').'" dusk="student-status-toggle-'.(int) $student->id.'" class="toggle-student-status inline-flex items-center justify-center w-8 h-8 rounded transition-colors '.$toggleClass.'" title="'.e($toggleTitle).'">'.$toggleIcon.'</button></div>';

        return [
            '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90" title="View Student" aria-label="View student '.e($student->name).'">'.(int) $student->id.'</a>',
            '<a href="'.e($showUrl).'" class="text-primary hover:underline font-medium">'.e($student->name).'</a>',
            e($student->email),
            $schoolCell,
            e($profile?->grade_level ?? '—'),
            $profile?->date_of_birth?->format('Y-m-d') ?? '—',
            $statusBadge,
            $actions,
        ];
    }
}
