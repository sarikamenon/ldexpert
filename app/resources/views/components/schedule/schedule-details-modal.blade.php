@props([
    'name' => 'scheduleDetailsModal',
])

<x-modal :name="$name" max-width="5xl">
    <div class="p-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-foreground">Schedule Details</h3>
            <button type="button" x-on:click="$dispatch('close-modal', '{{ $name }}')"
                class="text-foreground/60 hover:text-foreground transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div id="scheduleDetailsContent" class="max-h-[calc(100vh-12rem)] overflow-y-auto">
            <div class="text-center py-12">
                <p class="text-foreground/70">Loading schedule details...</p>
            </div>
        </div>
    </div>
</x-modal>
