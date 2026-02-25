<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

final class IrsReportRowTransformer
{
    /**
     * @param  array<string, mixed>  $row  One row from IrsReportService (recipient, payment_date_display, etc.)
     * @return array<int, string> 12 cell HTML strings
     */
    public static function transform(array $row): array
    {
        $fmt = static fn ($n) => number_format((float) $n, 2);

        return [
            e($row['recipient'] ?? ''),
            e($row['payment_date_display'] ?? ''),
            e($row['payment_method'] ?? ''),
            '$'.$fmt($row['hourly_rate'] ?? 0),
            e($row['tax_status'] ?? ''),
            e($row['payroll_period'] ?? ''),
            '$'.$fmt($row['regular_pay'] ?? 0),
            '$'.$fmt($row['additional_pay'] ?? 0),
            '$'.$fmt($row['total_deductions'] ?? 0),
            '$'.$fmt($row['ytd_regular_pay'] ?? 0),
            '$'.$fmt($row['total_gross'] ?? 0),
            '$'.$fmt($row['total_net'] ?? 0),
        ];
    }
}
