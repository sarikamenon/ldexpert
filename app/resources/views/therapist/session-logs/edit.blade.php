<x-app-layout>
    <x-slot name="title">
        Edit Session Log
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 lg:px-8 space-y-6">
            <div>
                <p class="text-sm text-foreground/60">Therapist · Session Logs</p>
                <h1 class="text-2xl font-semibold text-foreground">Edit Session Log</h1>
            </div>

            @if ($errors->any())
                <x-ui::alert variant="danger" class="mb-4">
                    <p class="font-semibold mb-2">Please fix the highlighted errors and try again.</p>
                    <ul class="list-disc pl-5 space-y-1 text-sm">
                        @foreach (array_unique($errors->all()) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui::alert>
            @endif

            @include('therapist.session-logs._form', [
                'sessionLog' => $sessionLog,
                'schedule' => null,
                'students' => [$sessionLog->student],
                'ssas' => [$sessionLog->ssa],
                // Ensure the service dropdown is populated and pre-selected when editing
                // Include both primary service and additional services
                'services' => $sessionLog->ssa
                    ? collect([$sessionLog->ssa->primaryService])->merge($sessionLog->ssa->services ?? [])->filter()
                    : collect(),
                'sessionOutcomes' => $sessionOutcomes ?? [],
            ])
        </div>
    </div>
</x-app-layout>
