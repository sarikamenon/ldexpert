<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Stub - {{ $therapistName }} - {{ $year }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }

        .page-header h1 {
            font-size: 20px;
            font-weight: bold;
            margin: 0 0 4px 0;
        }

        .page-header p {
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        .payment-block {
            page-break-inside: avoid;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            padding: 12px;
        }

        .company-name {
            font-weight: bold;
            text-align: center;
            font-size: 13px;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #ddd;
        }

        .info-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 3px 6px;
            font-size: 11px;
            vertical-align: top;
        }

        .info-table .label {
            font-weight: bold;
            width: 18%;
            color: #555;
            text-transform: uppercase;
            font-size: 10px;
        }

        .info-table .value {
            width: 32%;
        }

        .detail-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .detail-table th {
            background-color: #f5f5f5;
            padding: 5px 6px;
            text-align: left;
            border-bottom: 2px solid #333;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .detail-table td {
            padding: 3px 6px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
        }

        .detail-table .amount {
            text-align: right;
        }

        .detail-table .category-label {
            width: 14%;
        }

        .detail-table .category-amount {
            width: 14%;
        }

        .detail-table .spacer {
            width: 2%;
        }

        .totals-row td {
            font-weight: bold;
            border-top: 2px solid #333;
            padding-top: 6px;
            background-color: #f9f9f9;
        }

        .empty-message {
            text-align: center;
            color: #999;
            padding: 40px 0;
            font-size: 14px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 9px;
            color: #999;
        }
    </style>
</head>

<body>
    <div class="page-header">
        <h1>PAY STUB</h1>
        <p>{{ $therapistName }} &mdash; Calendar Year {{ $year }}</p>
    </div>

    @forelse ($rows as $row)
        <div class="payment-block">
            <div class="company-name">{{ $row['company_name'] }}</div>

            <table class="info-table">
                <tr>
                    <td class="label">Recipient</td>
                    <td class="value">{{ $row['recipient'] }}</td>
                    <td class="label">Tax Status</td>
                    <td class="value">{{ $row['tax_status'] }}</td>
                </tr>
                <tr>
                    <td class="label">Payment Date</td>
                    <td class="value">{{ $row['payment_date_display'] }}</td>
                    <td class="label">Payroll Period</td>
                    <td class="value">{{ $row['payroll_period'] }}</td>
                </tr>
                <tr>
                    <td class="label">Payment Method</td>
                    <td class="value">{{ $row['payment_method'] }}</td>
                    <td class="label">Additional %</td>
                    <td class="value">{{ number_format($row['additional_percentage'], 2) }}%</td>
                </tr>
                <tr>
                    <td class="label">Hourly Pay Rate</td>
                    <td class="value">${{ number_format($row['hourly_rate'], 2) }}</td>
                    <td class="label">Additional Amount</td>
                    <td class="value">${{ number_format($row['additional_amount'], 2) }}</td>
                </tr>
            </table>

            <table class="detail-table">
                <thead>
                    <tr>
                        <th class="category-label">Payments</th>
                        <th class="category-amount"></th>
                        <th class="spacer"></th>
                        <th class="category-label">Deductions</th>
                        <th class="category-amount"></th>
                        <th class="spacer"></th>
                        <th class="category-label">Year-to-Date</th>
                        <th class="category-amount"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Regular Pay</td>
                        <td class="amount">${{ number_format($row['regular_pay'], 2) }}</td>
                        <td></td>
                        <td>Federal Tax</td>
                        <td class="amount">${{ number_format($row['federal_tax'], 2) }}</td>
                        <td></td>
                        <td>Regular Pay</td>
                        <td class="amount">${{ number_format($row['ytd_regular_pay'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Additional Pay</td>
                        <td class="amount">${{ number_format($row['additional_pay'], 2) }}</td>
                        <td></td>
                        <td>Fed Med Tax</td>
                        <td class="amount">${{ number_format($row['federal_med_tax'], 2) }}</td>
                        <td></td>
                        <td>Additional Pay</td>
                        <td class="amount">${{ number_format($row['ytd_additional_pay'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Other</td>
                        <td class="amount">${{ number_format($row['other_pay_1'], 2) }}</td>
                        <td></td>
                        <td>Other</td>
                        <td class="amount">${{ number_format($row['other_deduction_1'], 2) }}</td>
                        <td></td>
                        <td>Federal Tax</td>
                        <td class="amount">${{ number_format($row['ytd_federal_tax'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Other</td>
                        <td class="amount">${{ number_format($row['other_pay_2'], 2) }}</td>
                        <td></td>
                        <td>Other</td>
                        <td class="amount">${{ number_format($row['other_deduction_2'], 2) }}</td>
                        <td></td>
                        <td>Fed Med Tax</td>
                        <td class="amount">${{ number_format($row['ytd_federal_med_tax'], 2) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="totals-row">
                        <td>Total Gross Pay</td>
                        <td class="amount">${{ number_format($row['total_gross'], 2) }}</td>
                        <td></td>
                        <td>Total Deductions</td>
                        <td class="amount">${{ number_format($row['total_deductions'], 2) }}</td>
                        <td></td>
                        <td>Total Net Pay</td>
                        <td class="amount">${{ number_format($row['ytd_total_net'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @empty
        <p class="empty-message">No payments found for this therapist in {{ $year }}.</p>
    @endforelse

    <div class="footer">
        <p>Generated {{ now()->format('M d, Y \a\t '.config('display.time')) }}</p>
    </div>
</body>

</html>
