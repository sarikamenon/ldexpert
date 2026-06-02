@props([
    'title' => '',
    'subtitle' => '',
    'variant' => 'success', // success | info | warning | error
])

@php
    $palette = match ($variant) {
        'success' => ['bg' => '#dcfce7', 'fg' => '#16a34a'],
        'info' => ['bg' => '#dbeafe', 'fg' => '#2563eb'],
        'warning' => ['bg' => '#fef3c7', 'fg' => '#d97706'],
        default => ['bg' => '#fee2e2', 'fg' => '#dc2626'],
    };
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f5f7fb; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; color: #0f172a; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 480px; width: 100%; padding: 40px 28px; text-align: center; }
        .icon { width: 64px; height: 64px; background: {{ $palette['bg'] }}; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .icon svg { width: 32px; height: 32px; color: {{ $palette['fg'] }}; }
        h1 { font-size: 22px; font-weight: 600; margin-bottom: 8px; }
        .subtitle { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .detail { background: #f8fafc; border-radius: 10px; padding: 16px; margin-bottom: 24px; text-align: left; }
        .detail .row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
        .detail .row .label { color: #64748b; }
        .detail .row .value { font-weight: 600; }
        .footer { color: #94a3b8; font-size: 12px; margin-top: 16px; }
        .btn { display: block; width: 100%; padding: 12px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; transition: background 0.15s; }
        .btn + .btn { margin-top: 10px; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-secondary { background: #f1f5f9; color: #0f172a; }
        .btn-secondary:hover { background: #e2e8f0; }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon">
            {{ $icon }}
        </div>
        <h1>{{ $title }}</h1>
        @if (! empty($subtitle))
            <p class="subtitle">{{ $subtitle }}</p>
        @endif

        {{ $slot }}
    </div>
</body>

</html>
