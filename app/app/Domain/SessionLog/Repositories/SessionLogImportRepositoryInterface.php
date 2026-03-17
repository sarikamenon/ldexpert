<?php

declare(strict_types=1);

namespace App\Domain\SessionLog\Repositories;

use App\DTOs\DataTablesParamsDTO;
use App\Models\SessionLogImport;
use Illuminate\Support\Collection;

interface SessionLogImportRepositoryInterface
{
    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SessionLogImport>}
     */
    public function listForDataTables(DataTablesParamsDTO $params): array;
}
