<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 lg:px-8 space-y-6">
            <div>
                <p class="text-sm text-foreground/60">Therapist · {{ $ssa->student->name }} · {{ $ssa->primaryService->name }}</p>
                <h1 class="text-2xl font-semibold text-foreground">Edit Goal #{{ $goal->number }}</h1>
            </div>

            @if ($errors->any())
                <x-ui::alert variant="danger">
                    Please fix the highlighted errors and try again.
                </x-ui::alert>
            @endif

            <x-ui::card class="p-6">
                <x-ssa.goal-form
                    :action="$formAction"
                    :goal="$goal"
                    :cancel-url="$cancelUrl" />
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
