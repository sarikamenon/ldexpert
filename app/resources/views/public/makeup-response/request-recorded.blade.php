<x-public.makeup-result-card
    title="Make-Up Requested"
    subtitle="Thanks — your therapist has been notified and will be in touch with available make-up times."
    variant="success">
    <x-slot:icon>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
    </x-slot:icon>

    <div class="detail">
        <div class="row">
            <span class="label">{{ $batch->count() === 1 ? 'Session to reschedule' : 'Sessions to reschedule' }}</span>
            <span class="value">
                @foreach ($sessionLabels as $label)
                    {{ $label }}@if (! $loop->last)<br>@endif
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
