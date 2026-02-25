<?php

declare(strict_types=1);

namespace App\Domain\SSA\Services;

use App\Domain\SSA\Repositories\SSAImportRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use Illuminate\Support\Collection;

final class SSAImportListService
{
    public function __construct(
        private readonly SSAImportRepositoryInterface $repository,
    ) {}

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection}
     */
    public function listForDataTables(DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($params);
    }
}
