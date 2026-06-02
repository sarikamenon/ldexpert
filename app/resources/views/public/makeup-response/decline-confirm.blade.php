<x-public.makeup-result-card
    title="Decline Make-Up?"
    subtitle="Confirm that you don't need a make-up for the session(s) below. This cannot be undone."
    variant="warning">
    <x-slot:icon>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
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
            <span class="label">Therapist</span>
            <span class="value">{{ $therapistName }}</span>
        </div>
    </div>

    <form method="POST" action="{{ $submitUrl }}">
        @csrf
        <button type="submit" class="btn btn-danger">Yes, decline make-up</button>
    </form>

    <a href="{{ route('login') }}" class="btn btn-secondary">No, keep my make-up</a>

    <p class="footer">Having trouble? Contact your therapist directly.</p>
</x-public.makeup-result-card>
