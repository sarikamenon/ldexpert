<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice Already Paid</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f5f7fb; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; color: #0f172a; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 480px; width: 100%; padding: 40px 28px; text-align: center; }
        .icon { width: 64px; height: 64px; background: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .icon svg { width: 32px; height: 32px; color: #2563eb; }
        h1 { font-size: 22px; font-weight: 600; margin-bottom: 8px; }
        .subtitle { color: #64748b; font-size: 14px; margin-bottom: 24px; line-height: 1.6; }
        .detail { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 16px; font-size: 14px; color: #166534; }
        .footer { color: #94a3b8; font-size: 12px; margin-top: 24px; }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
        </div>
        <h1>Already Paid</h1>
        <p class="subtitle">
            This invoice has already been paid. No further action is required.
        </p>

        <div class="detail">
            Invoice <strong>{{ $invoice->invoice_number }}</strong> was paid on
            <strong>{{ $invoice->paid_at?->format('M d, Y') ?? 'record' }}</strong>.
        </div>

        <p class="footer">
            {{ $invoice->company_name }} &middot; If you believe this is an error, please contact us.
        </p>
    </div>
</body>

</html>
