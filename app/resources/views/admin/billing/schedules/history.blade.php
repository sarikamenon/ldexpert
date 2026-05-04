<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>

    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">Run History</h1>
                <p class="text-sm text-foreground/60 mt-1">
                    {{ $schedule->schedule_type->label() }} —
                    {{ $schedule->schedulable?->display_name ?? $schedule->schedulable?->name ?? 'Entity #'.$schedule->schedulable_id }}
                </p>
            </div>
            <a href="{{ route('admin.billing.schedules.index') }}"
                class="inline-flex items-center gap-2 text-sm text-foreground/60 hover:text-foreground transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                Back to Schedules
            </a>
        </div>
    </div>

    <x-ui::card class="p-6">
        <div class="overflow-x-auto">
            <table
                id="billingScheduleHistoryTable"
                class="w-full display"
                data-datatable-url="{{ $datatableUrl }}"
            >
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Generated</th>
                        <th>Sessions</th>
                        @if ($schedule->isAdvanceMode())
                            <th>Adjustments</th>
                            <th>Carry Fwd</th>
                        @else
                            <th>Late Entries</th>
                        @endif
                        <th>Total</th>
                        <th>Status</th>
                        <th>Document</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-billing-schedule-history.js'])
    </x-slot>
</x-admin.layouts.app>
