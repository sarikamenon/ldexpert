<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill {{ $bill->bill_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }

        .company-info {
            flex: 1;
        }

        .bill-info {
            text-align: right;
        }

        .bill-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .bill-to {
            margin-bottom: 30px;
        }

        .bill-to h3,
        .from h3 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table th {
            background-color: #f5f5f5;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid #333;
            font-weight: bold;
        }

        table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
        }

        table tfoot td {
            border-top: 2px solid #333;
            font-weight: bold;
            padding-top: 10px;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            background-color: #f5f5f5;
            font-size: 14px;
        }

        .notes {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-info">
            <div class="bill-title">BILL</div>
            <div>
                <strong>{{ $bill->company_name }}</strong><br>
                @if ($bill->company_address)
                    {{ $bill->company_address }}<br>
                @endif
                @if ($bill->company_phone)
                    Phone: {{ $bill->company_phone }}<br>
                @endif
                @if ($bill->company_email)
                    Email: {{ $bill->company_email }}<br>
                @endif
                @if ($bill->company_tax_id)
                    Tax ID: {{ $bill->company_tax_id }}
                @endif
            </div>
        </div>
        <div class="bill-info">
            <div><strong>Bill #:</strong> {{ $bill->bill_number }}</div>
            <div><strong>Date:</strong> {{ $bill->bill_date->format('M d, Y') }}</div>
            <div><strong>Due Date:</strong> {{ $bill->due_date->format('M d, Y') }}</div>
            <div><strong>Billing Period:</strong> {{ $bill->billing_period_start->format('M d') }} -
                {{ $bill->billing_period_end->format('M d, Y') }}</div>
        </div>
    </div>

    <div class="bill-to">
        <h3>Bill To:</h3>
        <div>
            <strong>{{ $bill->therapist_name }}</strong><br>
            @if ($bill->therapist_address)
                {{ $bill->therapist_address }}<br>
            @endif
            @if ($bill->therapist_phone)
                Phone: {{ $bill->therapist_phone }}<br>
            @endif
            @if ($bill->therapist_email)
                {{ $bill->therapist_email }}
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Service</th>
                <th>School/Family</th>
                <th class="text-right">Duration</th>
                <th class="text-right">Rate</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bill->sessionLogs as $log)
                <tr>
                    <td>{{ $log->session_date->format('M d, Y') }}</td>
                    <td>{{ $log->student->name ?? '—' }}</td>
                    <td>{{ $log->service->name ?? '—' }}</td>
                    <td>{{ $log->school->display_name ?? '—' }}</td>
                    <td class="text-right">{{ $log->duration_minutes }} min</td>
                    <td class="text-right">${{ number_format($log->therapist_rate_amount ?? 0, 2) }}</td>
                    <td class="text-right">${{ number_format($log->therapist_billable_amount ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right"><strong>Subtotal:</strong></td>
                <td class="text-right"><strong>${{ number_format($bill->subtotal, 2) }}</strong></td>
            </tr>
            <tr>
                <td colspan="6" class="text-right"><strong>Adjustments:</strong></td>
                <td class="text-right"><strong>${{ number_format($bill->adjustments_total, 2) }}</strong></td>
            </tr>
            <tr class="total-row">
                <td colspan="6" class="text-right"><strong>Total Due:</strong></td>
                <td class="text-right"><strong>${{ number_format($bill->total_due, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    @if ($bill->notes)
        <div class="notes">
            <h3>Notes:</h3>
            <p>{{ $bill->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p>Thank you for your service!</p>
        <p>Payment terms: Net 30 days</p>
    </div>
</body>

</html>
