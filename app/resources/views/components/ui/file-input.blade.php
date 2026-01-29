@props(['accept' => null, 'disabled' => false])

<input type="file"
    @if($accept) accept="{{ $accept }}" @endif
    @disabled($disabled)
    {{ $attributes->merge(['class' => 'block w-full text-sm text-foreground file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary file:text-white hover:file:bg-primary/90']) }} />
