<?php

declare(strict_types=1);

namespace App\Domain\ActivityLog\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\Models\ActivityLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ActivityLogRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ActivityLog;

    /** @return Collection<int, ActivityLog> */
    public function recent(int $limit = 5): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, ActivityLog>}
     */
    public function listForDataTables(array $filters, DataTablesParamsDTO $params): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ActivityLog>
     */
    public function all(array $filters): Collection;

    /** @return Collection<int, string> */
    public function distinctActions(): Collection;

    /** @return Collection<int, string> */
    public function distinctModelTypes(): Collection;
}
