<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Select Make-Up Time</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; background: #f5f7fb; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; color: #0f172a; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 520px; width: 100%; padding: 40px 28px; }
        .icon { width: 64px; height: 64px; background: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
        .icon svg { width: 32px; height: 32px; color: #2563eb; }
        h1 { font-size: 22px; font-weight: 600; margin-bottom: 8px; text-align: center; }
        .subtitle { color: #64748b; font-size: 14px; margin-bottom: 24px; text-align: center; }
        .error-banner { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 14px; }
        .session-group { margin-bottom: 20px; }
        .session-label { font-size: 13px; color: #64748b; margin-bottom: 4px; }
        .session-missed { font-size: 14px; font-weight: 600; margin-bottom: 8px; }
        .slot-select { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; color: #0f172a; background: #fff; appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e"); background-position: right 8px center; background-repeat: no-repeat; background-size: 20px; cursor: pointer; }
        .slot-select:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }
        .no-slots { color: #94a3b8; font-size: 13px; font-style: italic; padding: 8px 0; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 16px 0; }
        .tz-note { color: #94a3b8; font-size: 12px; text-align: center; margin-bottom: 16px; }
        .btn { display: block; width: 100%; padding: 12px; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; text-align: center; transition: background 0.15s; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-primary:disabled { background: #93c5fd; cursor: not-allowed; }
        .detail { background: #f8fafc; border-radius: 10px; padding: 16px; margin-bottom: 20px; text-align: left; }
        .detail .row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .detail .row .label { color: #64748b; }
        .detail .row .value { font-weight: 600; }
        .footer { color: #94a3b8; font-size: 12px; margin-top: 16px; text-align: center; }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
                <polyline points="9 16 11 18 15 14"></polyline>
            </svg>
        </div>

        <h1>Select Make-Up Time</h1>
        <p class="subtitle">Pick an available time slot for {{ $batch->count() === 1 ? 'your missed session' : 'each missed session' }}.</p>

        @if ($error)
            <div class="error-banner">{{ $error }}</div>
        @endif

        <div class="detail">
            <div class="row">
                <span class="label">Therapist</span>
                <span class="value">{{ $therapistName }}</span>
            </div>
            <div class="row">
                <span class="label">Respond by</span>
                <span class="value">{{ $responseByDate ?? '—' }}</span>
            </div>
        </div>

        <form method="POST" action="{{ $submitUrl }}" id="slot-form">

            @foreach ($rows as $index => $row)
                @if ($index > 0)
                    <hr class="divider">
                @endif

                <div class="session-group">
                    <div class="session-label">Missed session</div>
                    <div class="session-missed">{{ $row['label'] }}</div>

                    @if (count($row['slots']) > 0)
                        <select name="slots[{{ $row['request']->id }}]" class="slot-select" required>
                            <option value="">Choose a time…</option>
                            @foreach ($row['slots'] as $slot)
                                <option value="{{ $slot['value'] }}">{{ $slot['label'] }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="no-slots">No available time slots for this date.</div>
                    @endif
                </div>
            @endforeach

            <p class="tz-note">All times shown in {{ $studentTimezone }}</p>

            <button type="submit" class="btn btn-primary">Confirm Make-Up Session</button>
        </form>

        <p class="footer">Having trouble? Contact your therapist directly.</p>
    </div>
</body>

</html>
