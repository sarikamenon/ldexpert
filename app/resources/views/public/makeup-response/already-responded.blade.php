<x-public.makeup-result-card
    title="Response Already Recorded"
    subtitle="We already have your response on file for this session. To change it, please contact your therapist directly."
    variant="info">
    <x-slot:icon>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
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
            <span class="label">Your response</span>
            <span class="value">{{ $batch->first()->status->label() }}</span>
        </div>
        @if ($batch->first()->responded_at)
            <div class="row">
                <span class="label">Recorded on</span>
                <span class="value">{{ $batch->first()->responded_at->format(config('display.datetime')) }}</span>
            </div>
        @endif
    </div>

    <p class="footer">You can close this page.</p>
</x-public.makeup-result-card>
