<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
    <button type="button" role="menuitem" @click="open = !open"
        class="inline-flex items-center gap-1 rounded px-3 py-1.5 text-sm text-foreground hover:bg-gray-50 focus:outline-none">
        {{ $trigger ?? $slot }}
        <svg class="h-3 w-3 text-foreground/70" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
                clip-rule="evenodd" />
        </svg>
    </button>

    <div x-cloak x-show="open" @click.outside="open = false"
        class="absolute left-0 z-50 mt-1 w-max min-w-[10rem] whitespace-nowrap rounded-md border border-border bg-white shadow-lg">
        {{ $content ?? '' }}
    </div>
</div>
