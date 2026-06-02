<x-app-layout>
    @isset($styles)
        <x-slot name="styles">
            {{ $styles }}
        </x-slot>
    @endisset

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            {{ $slot }}
        </div>
    </div>

    @isset($scripts)
        <x-slot name="scripts">
            {{ $scripts }}
        </x-slot>
    @endisset
</x-app-layout>
