<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Models\ServiceSupportAgreement;

final class SSACaseloadUnassignedRowTransformer
{
    /**
     * @return array<int, string>
     */
    public static function transform(ServiceSupportAgreement $ssa): array
    {
        $student = e($ssa->student->name ?? '—');
        $school = e($ssa->student?->studentProfile?->school->display_name ?? '—');
        $service = e($ssa->primaryService->name ?? '—');
        $tho = number_format(($ssa->tho_minutes ?? 0) / 60, 2);
        $actions = '<a href="'.e(route('admin.ssas.show', $ssa)).'" class="text-primary hover:underline text-sm">View SSA</a>';

        return [
            $student,
            $school,
            $service,
            $tho,
            $actions,
        ];
    }
}
