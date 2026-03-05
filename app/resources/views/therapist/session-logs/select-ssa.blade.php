<x-app-layout>
    <x-slot name="title">
        Select SSA
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 lg:px-8 space-y-6">
            <div>
                <p class="text-sm text-foreground/60">Therapist · Session Logs</p>
                <h1 class="text-2xl font-semibold text-foreground">Create Session Log</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    Select an active SSA to start a new session log.
                </p>
            </div>

            <x-ui::card class="p-6 space-y-4">
                <form method="GET" action="{{ route('therapist.session-logs.create') }}" class="space-y-4">
                    <div>
                        <label for="ssa_id" class="block text-sm font-medium text-gray-700">SSA</label>
                        <x-ui::select id="ssa_id" name="ssa_id" class="mt-1" placeholder="Select SSA" required>
                            <option value="">Select SSA</option>
                            @foreach ($ssas as $ssa)
                                <option value="{{ $ssa->id }}">
                                    {{ $ssa->student?->name }} (SSA #{{ $ssa->id }} -
                                    {{ $ssa->primaryService?->name }})
                                </option>
                            @endforeach
                        </x-ui::select>
                    </div>

                    <div class="flex items-center justify-end">
                        <x-ui::button type="submit" variant="primary">
                            Continue
                        </x-ui::button>
                    </div>
                </form>
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
