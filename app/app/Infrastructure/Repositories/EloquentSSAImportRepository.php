<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\SSA\Repositories\SSAImportRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\Models\SSAImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentSSAImportRepository implements SSAImportRepositoryInterface
{
    public function listForDataTables(DataTablesParamsDTO $params): array
    {
        $baseQuery = SSAImport::query()->with('user')->orderByDesc('ssa_imports.created_at');

        $recordsTotal = (clone $baseQuery)->count('ssa_imports.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $baseQuery->where(function (Builder $q) use ($search) {
                $q->where('ssa_imports.id', 'like', '%'.$search.'%')
                    ->orWhere('ssa_imports.file_name', 'like', '%'.$search.'%')
                    ->orWhere('ssa_imports.type', 'like', '%'.$search.'%')
                    ->orWhere('ssa_imports.status', 'like', '%'.$search.'%')
                    ->orWhereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', '%'.$search.'%'); // @phpstan-ignore argument.type
                    });
            });
        }
        $recordsFiltered = (clone $baseQuery)->count('ssa_imports.id');

        $orderColumn = $params->orderColumn ?? 'ssa_imports.created_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, SSAImport> $rows */
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
