@props([
    'name' => 'scheduleDetailsModal',
])

<x-modal :name="$name" max-width="5xl">
    <div class="flex flex-col max-h-[calc(100vh-4rem)] bg-background">
        {{-- Header: title + JS-populated action slot (e.g. Join Session) + close button --}}
        <div id="scheduleDetailsHeader"
            class="flex items-center justify-between gap-3 px-6 py-4 border-b border-border shrink-0">
            <div id="scheduleDetailsHeaderInner" class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold text-foreground">Schedule Details</h3>
            </div>
            <div id="scheduleDetailsHeaderActions" class="shrink-0 flex items-center gap-3"></div>
            <button type="button" x-on:click="$dispatch('close-modal', '{{ $name }}')"
                class="shrink-0 text-foreground/50 hover:text-foreground rounded-md p-1 -m-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Content (JS renders a flex split: left sidebar + right main with own padding) --}}
        <div id="scheduleDetailsContent" class="flex-1 overflow-y-auto">
            <div class="text-center py-12">
                <p class="text-foreground/70">Loading schedule details...</p>
            </div>
        </div>

        {{-- Footer (populated by JS) --}}
        <div id="scheduleDetailsFooter" class="px-6 py-4 border-t border-border shrink-0 hidden"></div>
    </div>
</x-modal>
