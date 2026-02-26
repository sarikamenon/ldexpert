<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

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
            '<a href="'.e($downloadUrl).'" class="inline-flex items-center gap-1 text-primary hover:text-primary/80 font-medium text-sm" title="Download Pay Stub PDF">'
                .'<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">'
                .'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />'
                .'</svg>'
                .'Download PDF'
                .'</a>',
        ];
    }
}
