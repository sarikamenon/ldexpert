<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Student\Repositories\StudentImportRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\Models\StudentImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentStudentImportRepository implements StudentImportRepositoryInterface
{
    public function listForDataTables(DataTablesParamsDTO $params): array
    {
        $baseQuery = StudentImport::query()->with('user')->orderByDesc('student_imports.created_at');

        $recordsTotal = (clone $baseQuery)->count('student_imports.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $baseQuery->where(function (Builder $q) use ($search) {
                $q->where('student_imports.id', 'like', '%'.$search.'%')
                    ->orWhere('student_imports.file_name', 'like', '%'.$search.'%')
                    ->orWhere('student_imports.type', 'like', '%'.$search.'%')
                    ->orWhere('student_imports.status', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', '%'.$search.'%');
                    });
            });
        }
        $recordsFiltered = (clone $baseQuery)->count('student_imports.id');

        $orderColumn = $params->orderColumn ?? 'student_imports.created_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, StudentImport> $rows */
        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }
}
