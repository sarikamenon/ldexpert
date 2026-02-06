@props([
    'formId' => null,
    'formAction' => null,
])

<div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
    {{-- Left side: Filters --}}
    <div class="flex gap-3">
        <form method="GET" class="flex items-end gap-2" @if($formId) id="{{ $formId }}" @endif @if($formAction) action="{{ $formAction }}" @endif>
            {{ $filters }}
            <x-ui::button type="submit">Filter</x-ui::button>
        </form>
    </div>

    {{-- Right side: Action buttons (optional) --}}
    @if(isset($actions) && $actions->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
