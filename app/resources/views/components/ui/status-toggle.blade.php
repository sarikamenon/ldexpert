@props([
    'status',
    'activeLabel' => 'Deactivate',
    'inactiveLabel' => 'Activate',
    'activeStatus' => 'inactive',
    'inactiveStatus' => 'active',
])

@if (($status ?? 'inactive') === 'active')
    <x-ui::button variant="danger" data-status="{{ $activeStatus }}"
        {{ $attributes->merge(['class' => 'change-status-btn']) }}>
        {{ $activeLabel }}
    </x-ui::button>
@else
    <x-ui::button variant="success" data-status="{{ $inactiveStatus }}"
        {{ $attributes->merge(['class' => 'change-status-btn']) }}>
        {{ $inactiveLabel }}
    </x-ui::button>
@endif
