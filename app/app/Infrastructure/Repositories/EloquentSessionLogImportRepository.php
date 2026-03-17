<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\SessionLog\Repositories\SessionLogImportRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\Models\SessionLogImport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EloquentSessionLogImportRepository implements SessionLogImportRepositoryInterface
{
    public function listForDataTables(DataTablesParamsDTO $params): array
    {
        $baseQuery = SessionLogImport::query()->with('user')->orderByDesc('session_log_imports.created_at');

        $recordsTotal = (clone $baseQuery)->count('session_log_imports.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $baseQuery->where(function (Builder $q) use ($search) {
                $q->where('session_log_imports.id', 'like', '%' . $search . '%')
                    ->orWhere('session_log_imports.file_name', 'like', '%' . $search . '%')
                    ->orWhere('session_log_imports.type', 'like', '%' . $search . '%')
                    ->orWhere('session_log_imports.status', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function (Builder $uq) use ($search) {
                        $uq->where('name', 'like', '%' . $search . '%'); // @phpstan-ignore argument.type
                    });
            });
        }
        $recordsFiltered = (clone $baseQuery)->count('session_log_imports.id');

        $orderColumn = $params->orderColumn ?? 'session_log_imports.created_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, SessionLogImport> $rows */
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
