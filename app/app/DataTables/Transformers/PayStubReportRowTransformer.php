<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;

final class PayStubReportRowTransformer
{
    /**
     * @param  array<string, mixed>  $row  Therapist aggregate from PayStubReportService
     * @param  int  $year  The selected calendar year (for building the download URL)
     * @return array<int, string> 4 cell HTML strings: Name, Payment Count, Total Amount, Download
     */
    public static function transform(array $row, int $year): array
    {
        $fmt = static fn ($n) => number_format((float) $n, 2);
        $therapistId = (int) ($row['therapist_id'] ?? 0);
        $downloadUrl = route('admin.finance.pay-stub-report.download', [
            'therapist_id' => $therapistId,
            'year' => $year,
        ]);

        return [
            e((string) ($row['therapist_name'] ?? '')),
            (string) ($row['payment_count'] ?? 0),
            '$'.$fmt($row['total_amount'] ?? 0),
            ActionButtons::download($downloadUrl),
        ];
    }
}
