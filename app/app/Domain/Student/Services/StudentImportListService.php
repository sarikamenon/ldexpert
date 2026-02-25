<?php

declare(strict_types=1);

namespace App\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentImportRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use Illuminate\Support\Collection;

final class StudentImportListService
{
    public function __construct(
        private readonly StudentImportRepositoryInterface $repository,
    ) {}

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection}
     */
    public function listForDataTables(DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($params);
    }
}
