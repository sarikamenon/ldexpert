@props([
    'title',
    'subtitle' => null,
    'backUrl' => null,
    'backLabel' => 'Back to list',
    'editUrl' => null,
    'editLabel' => null,
])

<x-ui::card class="p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <x-page-title :title="$title" />
            @if ($subtitle)
                <p class="text-sm text-foreground/60 mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            @isset($badge)
                {{ $badge }}
            @endisset

            @if ($backUrl)
                <a href="{{ $backUrl }}">
                    <x-ui::button variant="secondary">
                        {{ $backLabel }}
                    </x-ui::button>
                </a>
            @endif

            @if ($editUrl)
                <a href="{{ $editUrl }}">
                    <x-ui::button>
                        {{ $editLabel ?? 'Edit' }}
                    </x-ui::button>
                </a>
            @endif

            @isset($actions)
                {{ $actions }}
            @endisset
        </div>
    </div>
</x-ui::card>
