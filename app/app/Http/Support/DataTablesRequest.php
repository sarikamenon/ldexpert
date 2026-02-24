<?php

declare(strict_types=1);

namespace App\Http\Support;

use App\DTOs\DataTablesParamsDTO;
use Illuminate\Http\Request;

final class DataTablesRequest
{
    /**
     * Parse DataTables server-side request params with column whitelist for ordering.
     *
     * @param  array<int, string>  $orderColumnWhitelist  Map of DataTables column index (int) to allowed DB/query column name
     */
    public static function fromRequest(Request $request, array $orderColumnWhitelist): DataTablesParamsDTO
    {
        $draw = (int) $request->input('draw', 0);
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $length = $length < 1 ? 10 : min($length, 100);

        $search = $request->input('search', []);
        $searchValue = isset($search['value']) && is_string($search['value'])
            ? trim($search['value'])
            : null;
        if ($searchValue === '') {
            $searchValue = null;
        }

        $orderColumn = null;
        $orderDir = 'asc';
        $order = $request->input('order', []);
        if (! empty($order) && isset($order[0]['column'], $order[0]['dir'])) {
            $columnIndex = (int) $order[0]['column'];
            $orderColumn = $orderColumnWhitelist[$columnIndex] ?? null;
            $dir = strtolower((string) $order[0]['dir']);
            $orderDir = $dir === 'desc' ? 'desc' : 'asc';
        }

        return new DataTablesParamsDTO(
            draw: $draw,
            start: $start,
            length: $length,
            searchValue: $searchValue,
            orderColumn: $orderColumn,
            orderDir: $orderDir,
        );
    }
}
