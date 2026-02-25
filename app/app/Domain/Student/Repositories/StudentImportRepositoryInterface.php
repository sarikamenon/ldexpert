<?php

declare(strict_types=1);

namespace App\Domain\Student\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\Models\StudentImport;
use Illuminate\Support\Collection;

interface StudentImportRepositoryInterface
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, StudentImport>}
     */
    public function listForDataTables(DataTablesParamsDTO $params): array;
}
