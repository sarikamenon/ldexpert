@props(['disabled' => false, 'label' => null, 'checked' => false])

@if($label)
<label class="inline-flex items-center gap-2">
    <input type="checkbox" @disabled($disabled) @checked($checked)
        {{ $attributes->merge(['class' => 'rounded border-input text-primary focus:ring-ring']) }} />
    <span class="text-sm">{{ $label }}</span>
</label>
@else
<input type="checkbox" @disabled($disabled) @checked($checked)
    {{ $attributes->merge(['class' => 'rounded border-input text-primary focus:ring-ring']) }} />
@endif
