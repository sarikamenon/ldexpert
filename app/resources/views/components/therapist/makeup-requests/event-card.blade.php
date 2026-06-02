@props([
    'tone' => 'neutral', // neutral|warning
    'icon',
    'title',
    'sub',
    'date' => null,
    'rel' => null,
])

<div @class([
    'rounded-lg border p-4',
    'bg-warning/10 border-warning/15' => $tone === 'warning',
    'bg-muted/40 border-border' => $tone !== 'warning',
])>
    <div class="flex items-start gap-3">
        <span class="text-foreground/70 mt-0.5">
            <x-therapist.makeup-requests.icon :name="$icon" class="h-5 w-5 shrink-0" />
        </span>
        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-foreground">{{ $title }}</p>
                <p class="text-sm font-semibold text-foreground text-right shrink-0">{{ $date ?? '' }}</p>
            </div>
            <div class="flex items-start justify-between gap-3 mt-0.5">
                <p class="text-xs text-foreground/60">{{ $sub }}</p>
                @if ($rel)
                    <p class="text-xs text-foreground/60 text-right shrink-0">{{ $rel }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
