<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            width: 100%;
            margin-bottom: 16px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }

        .header td {
            vertical-align: top;
            border: none;
            padding: 0;
        }

        .company-info {
            width: 60%;
        }

        .company-info > div {
            margin-bottom: 8px;
        }

        .invoice-info {
            width: 40%;
            text-align: right;
        }

        .invoice-title {
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
    <table class="header">
        <tr>
            <td class="company-info">
                <div class="invoice-title">INVOICE</div>
                <div>
                    <strong>{{ $invoice->company_name }}</strong><br>
                    @if ($invoice->company_address)
                        {!! nl2br(e($invoice->company_address)) !!}<br>
                    @endif
                    @if ($invoice->company_phone)
                        Phone: {{ $invoice->company_phone }}<br>
                    @endif
                    @if ($invoice->company_email)
                        Email: {{ $invoice->company_email }}<br>
                    @endif
                    @if ($invoice->company_tax_id)
                        Tax ID: {{ $invoice->company_tax_id }}
                    @endif
                </div>
            </td>
            <td class="invoice-info">
                <div><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</div>
                <div><strong>Date:</strong> {{ $invoice->invoice_date->format('M d, Y') }}</div>
                <div><strong>Due Date:</strong> {{ $invoice->due_date->format('M d, Y') }}</div>
                <div><strong>Billing Period:</strong> {{ $invoice->billing_period_start->format('M d') }} -
                    {{ $invoice->billing_period_end->format('M d, Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="bill-to">
        <h3>Bill To:</h3>
        <div>
            <strong>{{ $invoice->school_display_name ?? $invoice->school_name }}</strong><br>
            @if ($invoice->school_address)
                {{ $invoice->school_address }}<br>
            @endif
            @if ($invoice->school_state)
                {{ $invoice->school_state }}<br>
            @endif
            Email: {{ $invoice->school_invoice_email }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Service</th>
                <th>Therapist</th>
                <th class="text-right">Duration</th>
                <th class="text-right">Rate</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->sessionLogs as $log)
                <tr>
                    <td>{{ $log->session_date->format('M d, Y') }}</td>
                    <td>{{ $log->student->name ?? '—' }}</td>
                    <td>{{ $log->service->name ?? '—' }}</td>
                    <td>{{ $log->therapist->name ?? '—' }}</td>
                    <td class="text-right">{{ $log->duration_minutes }} min</td>
                    <td class="text-right">${{ number_format($log->school_rate_amount ?? 0, 2) }}</td>
                    <td class="text-right">${{ number_format($log->school_invoice_amount ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right"><strong>Subtotal:</strong></td>
                <td class="text-right"><strong>${{ number_format($invoice->subtotal, 2) }}</strong></td>
            </tr>
            <tr>
                <td colspan="6" class="text-right"><strong>Tax:</strong></td>
                <td class="text-right"><strong>${{ number_format($invoice->tax_total, 2) }}</strong></td>
            </tr>
            <tr class="total-row">
                <td colspan="6" class="text-right"><strong>Total:</strong></td>
                <td class="text-right"><strong>${{ number_format($invoice->total, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    @if ($invoice->notes)
        <div class="notes">
            <h3>Notes:</h3>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>Payment terms: Net 30 days</p>
    </div>
</body>

</html>
