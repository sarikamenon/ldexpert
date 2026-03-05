@php
    $isEdit = isset($alias);
@endphp

<form method="POST"
    action="{{ $isEdit ? route('admin.service-aliases.update', $alias) : route('admin.service-aliases.store') }}"
    class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Service Alias Information</h3>
        <p class="text-sm text-foreground/60 mb-4">Map an external service name to a system service</p>

        <div class="grid grid-cols-1 gap-4">
            <div>
                <x-input-label for="source" value="Source *" />
                <p class="mt-1 text-xs text-foreground/60" id="source_help">
                    The external system this service name comes from.
                </p>
                <x-ui::select id="source" name="source" :searchable="false" class="mt-1 block w-full"
                    aria-describedby="source_help">
                    <option value="">Select source</option>
                    @foreach ($sources as $source)
                        @if ($source->value !== 'NOVA')
                            <option value="{{ $source->value }}" @selected(old('source', $isEdit ? $alias->source : '') === $source->value)>
                                {{ $source->value }}
                            </option>
                        @endif
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('source')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="service_id" value="System Service *" />
                <p class="mt-1 text-xs text-foreground/60" id="service_id_help">
                    The system service this external name maps to.
                </p>
                <x-ui::select id="service_id" name="service_id" class="mt-1 block w-full"
                    aria-describedby="service_id_help">
                    <option value="">Select service</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected(old('service_id', $isEdit ? $alias->service_id : '') == $service->id)>
                            {{ $service->name }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="external_name" value="External Name *" />
                <p class="mt-1 text-xs text-foreground/60" id="external_name_help">
                    The service name as it appears in the external system (e.g. "Speech Therapy Online"). Matching is case-sensitive.
                </p>
                <x-ui::input id="external_name" name="external_name" type="text" class="mt-1 block w-full"
                    :value="old('external_name', $isEdit ? $alias->external_name : '')"
                    required placeholder="e.g. Speech Therapy Online"
                    aria-describedby="external_name_help" />
                <x-input-error :messages="$errors->get('external_name')" class="mt-2" />
            </div>
        </div>
    </x-ui::card>

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.service-aliases.index') }}">
            <x-ui::button variant="secondary">
                Cancel
            </x-ui::button>
        </a>
        <x-ui::button type="submit">
            {{ $isEdit ? 'Update Alias' : 'Create Alias' }}
        </x-ui::button>
    </div>
</form>
