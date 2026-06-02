<x-public.makeup-result-card
    title="Response Deadline Passed"
    subtitle="The window to respond to this make-up reminder has closed. If you still need a make-up, please reach out to your therapist directly."
    variant="warning">
    <x-slot:icon>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
    </x-slot:icon>

    <div class="detail">
        <div class="row">
            <span class="label">{{ $batch->count() === 1 ? 'Missed session' : 'Missed sessions' }}</span>
            <span class="value">
                @foreach ($sessionLabels as $label)
                    {{ $label }}@if (! $loop->last)<br>@endif
                @endforeach
            </span>
        </div>
        <div class="row">
            <span class="label">Response was due by</span>
            <span class="value">{{ $responseByDate ?? '—' }}</span>
        </div>
        <div class="row">
            <span class="label">Therapist</span>
            <span class="value">{{ $batch->first()->therapist?->name ?? '—' }}</span>
        </div>
    </div>

    <p class="footer">You can close this page.</p>
</x-public.makeup-result-card>
