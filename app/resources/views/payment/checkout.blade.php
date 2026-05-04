<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pay Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f5f7fb; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; color: #0f172a; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 480px; width: 100%; overflow: hidden; }
        .header { background: linear-gradient(135deg, #5563b8, #6c7ce0); padding: 32px 28px; color: #fff; text-align: center; }
        .header h1 { font-size: 20px; font-weight: 600; margin-bottom: 4px; }
        .header p { font-size: 14px; opacity: 0.9; }
        .body { padding: 28px; }
        .row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .row:last-child { border-bottom: none; }
        .row .label { color: #64748b; }
        .row .value { font-weight: 600; }
        .total-row { background: #f0f9ff; border-radius: 10px; padding: 16px; margin: 20px 0; display: flex; justify-content: space-between; align-items: center; }
        .total-row .label { font-size: 14px; color: #0369a1; font-weight: 600; }
        .total-row .value { font-size: 24px; color: #0369a1; font-weight: 700; }
        .btn { display: block; width: 100%; padding: 14px; background: #5563b8; color: #fff; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; transition: background 0.2s; }
        .btn:hover { background: #4451a0; }
        .footer { padding: 16px 28px; text-align: center; color: #94a3b8; font-size: 12px; }
        .alert { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .secure { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 12px; color: #64748b; font-size: 12px; }
    </style>
</head>

<body>
    <div class="card">
        <div class="header">
            <h1>{{ $invoice->company_name }}</h1>
            <p>Invoice Payment</p>
        </div>
        <div class="body">
            @if (session('error'))
                <div class="alert">{{ session('error') }}</div>
            @endif

            <div class="row">
                <span class="label">Invoice Number</span>
                <span class="value">{{ $invoice->invoice_number }}</span>
            </div>
            <div class="row">
                <span class="label">Billing Period</span>
                <span class="value">{{ $invoice->billing_period_start?->format('M d') }} - {{ $invoice->billing_period_end?->format('M d, Y') }}</span>
            </div>
            <div class="row">
                <span class="label">Due Date</span>
                <span class="value">{{ $invoice->due_date?->format('M d, Y') }}</span>
            </div>
            <div class="row">
                <span class="label">School/Family</span>
                <span class="value">{{ $invoice->school_display_name }}</span>
            </div>

            <div class="total-row">
                <span class="label">Amount Due</span>
                <span class="value">${{ number_format((float) $invoice->total, 2) }}</span>
            </div>

            <form method="POST" action="{{ route('payment.checkout', $invoice->payment_token) }}">
                @csrf
                <button type="submit" class="btn">Pay Now</button>
            </form>

            <div class="secure">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                Secure payment powered by Stripe
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ $invoice->company_name }}
        </div>
    </div>
</body>

</html>
