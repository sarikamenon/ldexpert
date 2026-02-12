@props(['content'])

<span
    class="group relative inline-flex shrink-0 cursor-help focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 rounded"
    tabindex="0"
    role="img"
    aria-label="{{ __('Info') }}"
    {{ $attributes->except('content') }}
>
    <svg
        class="w-4 h-4 text-foreground/50 hover:text-foreground/80 focus:outline-none focus:ring-2 focus:ring-ring rounded"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
        aria-hidden="true"
    >
        <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
        />
    </svg>
    <span
        role="tooltip"
        class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1.5 px-2.5 py-1.5 text-xs font-normal text-background bg-foreground rounded shadow-lg whitespace-normal min-w-[200px] max-w-[320px] opacity-0 pointer-events-none invisible group-hover:opacity-100 group-hover:visible group-focus:opacity-100 group-focus:visible transition-none z-50"
    >
        {{ $content }}
    </span>
</span>
