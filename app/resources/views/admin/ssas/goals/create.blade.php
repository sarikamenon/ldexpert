<x-admin.layouts.app>
    <x-page-title :title="'Add Goal — ' . $ssa->student->name . ' · ' . $ssa->primaryService->name" />

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            Please fix the highlighted errors and try again.
        </x-ui::alert>
    @endif

    <x-ui::card class="p-6">
        <x-ssa.goal-form
            :action="$formAction"
            :goal="$goal"
            :cancel-url="$cancelUrl" />
    </x-ui::card>
</x-admin.layouts.app>
