{{-- @var array{availability_date: string, start_time: string, end_time: string} $formDefaults --}}
<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">Add Make-Up Availability</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Define a time window when you are available for make-up sessions.
                </p>
            </div>

            @if ($errors->any())
                <x-ui::alert variant="danger" class="mb-4">
                    @if ($errors->has('availability'))
                        {{ $errors->first('availability') }}
                    @else
                        Please fix the highlighted errors and try again.
                    @endif
                </x-ui::alert>
            @endif

            @include('therapist.makeup-requests.availability._form', ['formDefaults' => $formDefaults])
        </div>
    </div>
</x-app-layout>
