@props([
    'title' => 'Schedule',
    'addButtonUrl' => '#',
    'addButtonText' => '+ ADD NEW SCHEDULE',
    'showAddButton' => true,
])

<div class="flex items-center justify-between mb-6" {{ $attributes }}>
    <h1 class="text-2xl font-semibold text-foreground">{{ $title }}</h1>
    @if ($showAddButton)
        <a href="{{ $addButtonUrl }}"
            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ $addButtonText }}
        </a>
    @endif
</div>
