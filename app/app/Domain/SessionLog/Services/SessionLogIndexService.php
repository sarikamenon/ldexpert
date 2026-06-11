<?php

declare(strict_types=1);

namespace App\Domain\SessionLog\Services;

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Support\Collection;

final class SessionLogIndexService
{
    public function __construct(
        private readonly SessionLogRepositoryInterface $repository,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SessionLog>}
     */
    public function listForDataTables(array $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SessionLog>}
     */
    public function listForDataTablesForTherapist(User $therapist, array $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTablesForTherapist($therapist, $filters, $params);
    }
}
