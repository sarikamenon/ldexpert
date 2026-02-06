@props(['disabled' => false])

<input @disabled($disabled)
    {{ $attributes->merge(['class' => 'border border-input bg-white text-foreground rounded-base shadow-sm focus:ring-2 focus:ring-ring focus:border-ring']) }}>
