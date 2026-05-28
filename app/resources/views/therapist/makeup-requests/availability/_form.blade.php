{{-- @var array{availability_date: string, start_time: string, end_time: string} $formDefaults --}}
<form method="POST" action="{{ route('therapist.makeup-requests.availability.store') }}" class="space-y-6">
    @csrf

    <x-ui::card class="p-6 space-y-6">
        <h3 class="text-lg font-semibold text-foreground">Window details</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-input-label for="availability_date" value="Date *" />
                <p class="mt-1 text-xs text-foreground/60" id="availability_date_help">
                    Calendar date this window applies to (your timezone).
                </p>
                <x-ui::input
                    id="availability_date"
                    name="availability_date"
                    type="date"
                    class="mt-1 block w-full"
                    :value="old('availability_date', $formDefaults['availability_date'])"
                    aria-describedby="availability_date_help"
                    required />
                <x-input-error :messages="$errors->get('availability_date')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="start_time" value="Start time *" />
                <p class="mt-1 text-xs text-foreground/60" id="start_time_help">
                    When the window opens (your timezone).
                </p>
                <x-ui::input
                    id="start_time"
                    name="start_time"
                    type="time"
                    class="mt-1 block w-full"
                    :value="old('start_time', $formDefaults['start_time'])"
                    aria-describedby="start_time_help"
                    required />
                <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="end_time" value="End time *" />
                <p class="mt-1 text-xs text-foreground/60" id="end_time_help">
                    When the window closes (your timezone).
                </p>
                <x-ui::input
                    id="end_time"
                    name="end_time"
                    type="time"
                    class="mt-1 block w-full"
                    :value="old('end_time', $formDefaults['end_time'])"
                    aria-describedby="end_time_help"
                    required />
                <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="notes" value="Notes (optional)" />
            <p class="mt-1 text-xs text-foreground/60" id="notes_help">
                Optional context for your own reference; not shown to parents.
            </p>
            <textarea
                id="notes"
                name="notes"
                rows="4"
                class="mt-1 block w-full border border-border rounded-md px-3 py-2 text-sm text-foreground placeholder:text-foreground/40 focus:outline-none focus:ring-2 focus:ring-ring"
                placeholder="e.g. Available after school hours"
                aria-describedby="notes_help">{{ old('notes') }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>

        <div class="flex flex-wrap justify-end gap-3">
            <a href="{{ route('therapist.makeup-requests.availability.index') }}">
                <x-ui::button type="button" variant="secondary"
                    class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Cancel
                </x-ui::button>
            </a>
            <x-ui::button type="submit"
                class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                Add window
            </x-ui::button>
        </div>
    </x-ui::card>
</form>
