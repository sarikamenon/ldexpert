@props([
    'tabs' => [],
    'activeTab' => null,
])

@php
    $defaultTab = $tabs[0]['key'] ?? null;
    $currentTab = $activeTab ?? $defaultTab;
@endphp

<div class="border-b border-border mb-6">
    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['href'] }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($currentTab ?? '') === ($tab['key'] ?? '') ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
