@props([
    'name' => 'scheduleDetailsModal',
])

<x-modal :name="$name" max-width="5xl">
    <div class="flex flex-col max-h-[calc(100vh-4rem)]">
        {{-- Header (populated by JS) --}}
        <div id="scheduleDetailsHeader"
            class="flex items-center justify-between px-6 py-4 border-b border-border shrink-0">
            <h3 class="text-lg font-semibold text-foreground">Schedule Details</h3>
            <button type="button" x-on:click="$dispatch('close-modal', '{{ $name }}')"
                class="text-foreground/60 hover:text-foreground transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Content --}}
        <div id="scheduleDetailsContent" class="flex-1 overflow-y-auto px-6 py-4">
            <div class="text-center py-12">
                <p class="text-foreground/70">Loading schedule details...</p>
            </div>
        </div>

        {{-- Footer (populated by JS) --}}
        <div id="scheduleDetailsFooter" class="px-6 py-4 border-t border-border shrink-0 hidden"></div>
    </div>
</x-modal>
