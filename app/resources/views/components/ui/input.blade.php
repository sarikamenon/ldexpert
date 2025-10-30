@props(['type' => 'text'])
<input type="{{ $type }}"
    {{ $attributes->merge(['class' => 'block w-full rounded-base border border-input bg-white px-3 py-2 text-foreground placeholder:text-foreground/50 focus:ring-2 focus:ring-ring focus:border-ring']) }} />
