<x-public.makeup-result-card
    title="Make-Up Session Scheduled"
    subtitle="Your make-up session has been booked successfully."
    variant="success">
    <x-slot:icon>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
    </x-slot:icon>

    <div class="detail">
        <div class="row">
            <span class="label">{{ count($bookedLabels) === 1 ? 'Scheduled for' : 'Scheduled sessions' }}</span>
            <span class="value">
                @foreach ($bookedLabels as $label)
                    {{ $label }}@if (! $loop->last)<br>@endif
                @endforeach
            </span>
        </div>
        <div class="row">
            <span class="label">Therapist</span>
            <span class="value">{{ $therapistName }}</span>
        </div>
    </div>

    @if ($remaining->isNotEmpty())
        <p class="footer" style="color: #64748b; margin-bottom: 8px;">
            {{ $remaining->count() }} session{{ $remaining->count() === 1 ? '' : 's' }} {{ $remaining->count() === 1 ? 'has' : 'have' }} no available slots — your therapist will be in touch.
        </p>
    @endif

    <p class="footer">You can close this page.</p>
</x-public.makeup-result-card>
