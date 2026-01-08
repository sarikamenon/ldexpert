<button
    {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-background text-foreground border border-border rounded-base font-semibold text-xs uppercase tracking-widest shadow-sm hover:bg-background/subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
