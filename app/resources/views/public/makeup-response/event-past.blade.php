<x-public.makeup-result-card
    title="Session Date Has Passed"
    subtitle="The session this reminder was for has already occurred, so a response can no longer be recorded. If you have questions, please reach out to your therapist."
    variant="warning">
    <x-slot:icon>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
    </x-slot:icon>

    <div class="detail">
        <div class="row">
            <span class="label">{{ $batch->count() === 1 ? 'Missed session' : 'Missed sessions' }}</span>
            <span class="value">
                @foreach ($batch as $row)
                    {{ $row->event_date->format(config('display.date')) }}@if (! $loop->last), @endif
                @endforeach
            </span>
        </div>
        <div class="row">
            <span class="label">Therapist</span>
            <span class="value">{{ $batch->first()->therapist?->name ?? '—' }}</span>
        </div>
    </div>

    <p class="footer">You can close this page.</p>
</x-public.makeup-result-card>
