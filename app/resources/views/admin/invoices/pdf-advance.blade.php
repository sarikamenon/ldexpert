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
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }

        .company-info {
            flex: 1;
        }

        .invoice-info {
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

        .bill-to h3 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .section-header {
            background-color: #e8e8e8;
            padding: 8px 10px;
            font-weight: bold;
            font-size: 13px;
            margin-top: 20px;
            margin-bottom: 0;
            border-bottom: 2px solid #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        table th {
            background-color: #f5f5f5;
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #999;
            font-weight: bold;
            font-size: 11px;
        }

        table td {
            padding: 6px 10px;
            border-bottom: 1px solid #ddd;
        }

        .subtotal-row td {
            border-top: 1px solid #999;
            font-weight: bold;
            padding-top: 8px;
            background-color: #fafafa;
        }

        .text-right {
            text-align: right;
        }

        .credit {
            color: #c0392b;
        }

        .charge {
            color: #333;
        }

        .summary {
            margin-top: 20px;
            border-top: 2px solid #333;
            padding-top: 10px;
        }

        .summary table {
            width: 300px;
            margin-left: auto;
        }

        .summary table td {
            border: none;
            padding: 4px 10px;
        }

        .summary .total-row {
            background-color: #f5f5f5;
            font-size: 14px;
            font-weight: bold;
        }

        .summary .total-row td {
            padding: 10px;
            border-top: 2px solid #333;
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

        .carry-forward-note {
            margin-top: 10px;
            padding: 8px 12px;
            background-color: #fff8e1;
            border: 1px solid #ffd54f;
            font-size: 11px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-info">
            <div class="invoice-title">INVOICE</div>
            <div>
                <strong>{{ $invoice->company_name }}</strong><br>
                @if ($invoice->company_address)
                    {{ $invoice->company_address }}<br>
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
        </div>
        <div class="invoice-info">
            <div><strong>Invoice #:</strong> {{ $invoice->invoice_number }}</div>
            <div><strong>Date:</strong> {{ $invoice->created_at->format('M d, Y') }}</div>
            <div><strong>Due Date:</strong> {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : '—' }}</div>
            <div><strong>Period:</strong> {{ $invoice->billing_period_start->format('M d') }} -
                {{ $invoice->billing_period_end->format('M d, Y') }}</div>
        </div>
    </div>

    <div class="bill-to">
        <h3>Bill To:</h3>
        <div>
            <strong>{{ $invoice->school_display_name ?? $invoice->school_name ?? '—' }}</strong><br>
            @if ($invoice->school_address)
                {{ $invoice->school_address }}<br>
            @endif
            @if ($invoice->school_state)
                {{ $invoice->school_state }}<br>
            @endif
            @if ($invoice->school_contact_email)
                {{ $invoice->school_contact_email }}
            @endif
        </div>
    </div>

    {{-- ADJUSTMENTS SECTION --}}
    @if ($adjustmentLines->isNotEmpty())
        <div class="section-header">
            Adjustments from Previous Period
        </div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($adjustmentLines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td class="text-right {{ $line->total < 0 ? 'credit' : 'charge' }}">
                            {{ $line->total < 0 ? '-' : '' }}${{ number_format(abs((float) $line->total), 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="subtotal-row">
                    <td class="text-right">Adjustment Subtotal:</td>
                    <td class="text-right {{ $adjustmentSubtotal < 0 ? 'credit' : '' }}">
                        {{ $adjustmentSubtotal < 0 ? '-' : '' }}${{ number_format(abs((float) $adjustmentSubtotal), 2) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- ADVANCE CHARGES SECTION --}}
    @if ($advanceLines->isNotEmpty())
        <div class="section-header">
            Advance Charges for Upcoming Period ({{ $advanceLines->first()->billing_period_start->format('M d') }} – {{ $advanceLines->first()->billing_period_end->format('M d, Y') }})
        </div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($advanceLines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td class="text-right">${{ number_format((float) $line->unit_price, 2) }}</td>
                        <td class="text-right">${{ number_format((float) $line->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="subtotal-row">
                    <td colspan="2" class="text-right">Advance Subtotal:</td>
                    <td class="text-right">${{ number_format((float) $advanceSubtotal, 2) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- STANDARD SESSION CHARGES (if any mixed in) --}}
    @if ($standardLines->isNotEmpty())
        <div class="section-header">Session Charges</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($standardLines as $line)
                    <tr>
                        <td>{{ $line->description }}</td>
                        <td class="text-right">${{ number_format((float) $line->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- TOTALS SUMMARY --}}
    <div class="summary">
        <table>
            @if ($adjustmentLines->isNotEmpty())
                <tr>
                    <td class="text-right">Adjustments:</td>
                    <td class="text-right {{ $adjustmentSubtotal < 0 ? 'credit' : '' }}">
                        {{ $adjustmentSubtotal < 0 ? '-' : '' }}${{ number_format(abs((float) $adjustmentSubtotal), 2) }}
                    </td>
                </tr>
            @endif
            @if ($advanceLines->isNotEmpty())
                <tr>
                    <td class="text-right">Advance Charges:</td>
                    <td class="text-right">${{ number_format((float) $advanceSubtotal, 2) }}</td>
                </tr>
            @endif
            <tr class="total-row">
                <td class="text-right">Total Due:</td>
                <td class="text-right">${{ number_format((float) $invoice->total, 2) }}</td>
            </tr>
        </table>
    </div>

    @if ((float) $invoice->carry_forward_balance > 0)
        <div class="carry-forward-note">
            <strong>Credit Balance:</strong> ${{ number_format((float) $invoice->carry_forward_balance, 2) }}
            will be applied to your next invoice.
        </div>
    @endif

    @if ($invoice->notes)
        <div class="notes">
            <h3>Notes:</h3>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>Payment terms: Net {{ $invoice->due_date && $invoice->created_at ? $invoice->due_date->diffInDays($invoice->created_at) : 30 }} days</p>
    </div>
</body>

</html>
