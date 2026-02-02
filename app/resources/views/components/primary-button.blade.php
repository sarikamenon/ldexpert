<button
    {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-primary/90 active:bg-primary/80 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 disabled:pointer-events-none transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
