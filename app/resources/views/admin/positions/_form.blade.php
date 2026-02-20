@php
    $isEdit = isset($position);
@endphp

<form method="POST"
    action="{{ $isEdit ? route('admin.positions.update', $position) : route('admin.positions.store') }}"
    class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Position Information</h3>
        <p class="text-sm text-foreground/60 mb-4">Define the position name and associated services</p>

        <div class="grid grid-cols-1 gap-4">
            <div>
                <x-input-label for="name" value="Position Name *" />
                <p class="mt-1 text-xs text-foreground/60">A short abbreviation or name for this position (e.g., SLP,
                    OT, PT).</p>
                <x-ui::input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $position->name ?? '')"
                    required placeholder="Enter position name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label value="Services *" />
                <p class="mt-1 text-xs text-foreground/60">Select the services this position can provide.</p>
                <x-ui::select name="service_ids[]" :multiple="true" placeholder="Select services"
                    class="mt-2">
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}"
                            @selected(
                                is_array(old('service_ids'))
                                    ? in_array($service->id, old('service_ids'))
                                    : ($isEdit && $position->services->contains($service->id))
                            )>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('service_ids')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.positions.index') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Position' : 'Create Position' }}
        </x-ui::button>
    </div>
</form>
