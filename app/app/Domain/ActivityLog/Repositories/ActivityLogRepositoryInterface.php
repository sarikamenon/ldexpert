<?php

declare(strict_types=1);

namespace App\Domain\ActivityLog\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ActivityLogRepositoryInterface
{
    public function create(array $attributes): ActivityLog;

    public function recent(int $limit = 5): Collection;

    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, ActivityLog>}
     */
    public function listForDataTables(array $filters, DataTablesParamsDTO $params): array;

    public function all(array $filters): Collection;

    public function distinctActions(): Collection;

    public function distinctModelTypes(): Collection;
}
