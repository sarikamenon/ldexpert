<?php

declare(strict_types=1);

namespace App\Domain\SessionLog\Services;

use App\Domain\SessionLog\Repositories\SessionLogImportRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use Illuminate\Support\Collection;

final class SessionLogImportListService
{
    public function __construct(
        private readonly SessionLogImportRepositoryInterface $repository,
    ) {}

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, \App\Models\SessionLogImport>}
     */
    public function listForDataTables(DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($params);
    }
}
