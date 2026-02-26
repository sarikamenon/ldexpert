<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Constants\UsStates;
use App\DataTables\ActionButtons;
use App\Models\School;

final class SchoolRowTransformer
{
    /**
     * @return array<int, string> 7 cell HTML strings in column order
     */
    public static function transform(School $school): array
    {
        $showUrl = route('admin.schools.show', $school);
        $editUrl = route('admin.schools.edit', $school);
        $isActive = ($school->status->value ?? 'inactive') === 'active';
        $statusLabel = ucfirst($school->status->value ?? 'inactive');
        $badgeClass = $isActive
            ? 'bg-success/10 text-success border border-success/20'
            : 'bg-danger/10 text-danger border border-danger/20';

        $statusBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium '.
            $badgeClass.
            '">'.
            $statusLabel.
            '</span>';

        $toggleBtn = $isActive
            ? ActionButtons::deactivate('Deactivate School', ['data-school' => (int) $school->id, 'data-status' => e($school->status->value ?? 'inactive'), 'class' => 'toggle-status-button'])
            : ActionButtons::activate('Activate School', ['data-school' => (int) $school->id, 'data-status' => e($school->status->value ?? 'inactive'), 'class' => 'toggle-status-button']);

        $actions = ActionButtons::wrap(
            ActionButtons::view($showUrl, 'View School'),
            ActionButtons::edit($editUrl, 'Edit School'),
            $toggleBtn,
        );

        $state = $school->state ? UsStates::getStateName($school->state) : '—';

        return [
            '<a href="'.e($showUrl).'" class="inline-flex items-center justify-center px-3 py-1.5 bg-primary text-primary-foreground text-sm font-medium rounded-md hover:bg-primary/90 transition-colors" title="View School">'.(int) $school->id.'</a>',
            '<a href="'.e($showUrl).'" class="text-primary hover:underline font-medium">'.e($school->display_name).'</a>',
            e($school->manager->name ?? '—'),
            e($state),
            e($school->contact_email ?? '—'),
            $statusBadge,
            $actions,
        ];
    }
}
