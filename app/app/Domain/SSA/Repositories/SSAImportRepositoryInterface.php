<?php

declare(strict_types=1);

namespace App\Domain\SSA\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\Models\SSAImport;
use Illuminate\Support\Collection;

interface SSAImportRepositoryInterface
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SSAImport>}
     */
    public function listForDataTables(DataTablesParamsDTO $params): array;
}
