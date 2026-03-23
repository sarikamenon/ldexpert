<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Cancelled</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f5f7fb; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; color: #0f172a; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 480px; width: 100%; padding: 40px 28px; text-align: center; }
        .icon { width: 64px; height: 64px; background: #fef3c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .icon svg { width: 32px; height: 32px; color: #d97706; }
        h1 { font-size: 22px; font-weight: 600; margin-bottom: 8px; }
        .subtitle { color: #64748b; font-size: 14px; margin-bottom: 24px; line-height: 1.6; }
        .btn { display: inline-block; padding: 12px 28px; background: #5563b8; color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.2s; }
        .btn:hover { background: #4451a0; }
        .footer { color: #94a3b8; font-size: 12px; margin-top: 24px; }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
        </div>
        <h1>Payment Cancelled</h1>
        <p class="subtitle">
            Your payment was not processed. No charges have been made.<br>
            You can try again using the link below.
        </p>

        <a href="{{ route('payment.show', $invoice->payment_token) }}" class="btn">Try Again</a>

        <p class="footer">
            Invoice {{ $invoice->invoice_number }} &middot; {{ $invoice->company_name }}
        </p>
    </div>
</body>

</html>
