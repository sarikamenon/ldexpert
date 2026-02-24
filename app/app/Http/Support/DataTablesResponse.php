<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\DTOs\DataTablesParamsDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

trait DataTablesResponse
{
    /**
     * Build a standard DataTables JSON response.
     *
     * @param  Collection<int, mixed>  $rows
     * @param  callable  $rowTransformer  function(mixed $row): array<int, string>
     */
    protected function dataTablesResponse(
        DataTablesParamsDTO $params,
        int $recordsTotal,
        int $recordsFiltered,
        Collection $rows,
        callable $rowTransformer
    ): JsonResponse {
        $data = $rows->map(
            /**
             * @param  mixed  $row
             * @return array<int, string>
             */
            function ($row) use ($rowTransformer): array {
                /** @var array<int, string> $transformed */
                $transformed = $rowTransformer($row);

                return $transformed;
            }
        )->all();

        return response()->json([
            'draw' => $params->draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }
}
