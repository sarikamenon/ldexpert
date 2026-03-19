<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Successful</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f5f7fb; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; color: #0f172a; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 480px; width: 100%; padding: 40px 28px; text-align: center; }
        .icon { width: 64px; height: 64px; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .icon svg { width: 32px; height: 32px; color: #16a34a; }
        h1 { font-size: 22px; font-weight: 600; margin-bottom: 8px; }
        .subtitle { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .detail { background: #f8fafc; border-radius: 10px; padding: 16px; margin-bottom: 24px; text-align: left; }
        .detail .row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
        .detail .row .label { color: #64748b; }
        .detail .row .value { font-weight: 600; }
        .footer { color: #94a3b8; font-size: 12px; margin-top: 16px; }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
        </div>
        <h1>Payment Successful!</h1>
        <p class="subtitle">Your payment has been processed successfully.</p>

        <div class="detail">
            <div class="row">
                <span class="label">Invoice</span>
                <span class="value">{{ $invoice->invoice_number }}</span>
            </div>
            <div class="row">
                <span class="label">Amount Paid</span>
                <span class="value">${{ number_format((float) $payment->amount, 2) }}</span>
            </div>
            <div class="row">
                <span class="label">Payment Method</span>
                <span class="value">{{ $payment->method->label() }}</span>
            </div>
            <div class="row">
                <span class="label">Date</span>
                <span class="value">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <p class="footer">A confirmation has been recorded. You may close this page.</p>
    </div>
</body>

</html>
