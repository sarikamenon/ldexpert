<x-public.makeup-result-card
    title="Make-Up Declined"
    subtitle="We've recorded that you don't need a make-up for this session. No further action is required."
    variant="info">
    <x-slot:icon>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line></svg>
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
