<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\User;

final class TherapistRowTransformer
{
    /**
     * @return array<int, string> 8 cell HTML strings in column order
     */
    public static function transform(User $therapist): array
    {
        $profile = $therapist->therapistProfile;
        $isActive = ($therapist->status->value ?? 'inactive') === 'active';
        $statusLabel = ucfirst($therapist->status->value ?? 'inactive');
        $showUrl = route('admin.therapists.show', $therapist);
        $editUrl = route('admin.therapists.edit', $therapist);
        $managerName = $profile?->manager->name ?? '—';
        $positionName = $profile?->position->name ?? '—';
        $maxHours = $profile->max_weekly_hours ?? '—';
        $badgeClass = $isActive ? 'bg-success/10 text-success border border-success/20' : 'bg-danger/10 text-danger border border-danger/20';
        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.$badgeClass.'">'.$statusLabel.'</span>';

        $toggleAttrs = ['data-therapist' => (int) $therapist->id, 'data-status' => e($therapist->status->value ?? 'inactive'), 'dusk' => 'status-toggle-'.(int) $therapist->id, 'class' => 'toggle-status-button'];
        $toggleBtn = $isActive
            ? ActionButtons::deactivate('Deactivate Therapist', $toggleAttrs)
            : ActionButtons::activate('Activate Therapist', $toggleAttrs);

        $actions = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View Therapist', ['dusk' => 'view-therapist-'.(int) $therapist->id]),
            ActionButtons::edit($editUrl, 'Edit Therapist', ['dusk' => 'edit-therapist-'.(int) $therapist->id]),
            $toggleBtn,
        );

        return [
            '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90" title="View Therapist">'.(int) $therapist->id.'</a>',
            '<a href="'.e($showUrl).'" class="text-primary hover:underline font-medium">'.e($therapist->name).'</a>',
            e($therapist->email),
            e($managerName),
            e($positionName),
            $maxHours === '—' ? '—' : (string) $maxHours,
            $statusBadge,
            $actions,
        ];
    }
}
