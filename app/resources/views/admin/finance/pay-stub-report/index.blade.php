<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>
    <x-page-title title="Pay Stub Report" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Year Filter --}}
    <x-ui::card class="p-6 mb-6">
        <form id="payStubFiltersForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="year" value="Calendar Year *" />
                    <p class="mt-1 text-xs text-foreground/60" id="year_help">
                        Select a calendar year to view therapists who received payments.
                    </p>
                    <x-ui::select id="year" name="year" class="mt-1" aria-describedby="year_help">
                        @foreach ($years as $yr)
                            <option value="{{ $yr }}" @selected($yr === $selectedYear)>
                                {{ $yr }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>
            </div>
        </form>
    </x-ui::card>

    {{-- Therapist List Table --}}
    <x-ui::card class="p-6 space-y-4">
        <div class="overflow-x-auto">
            <table id="payStubTable" class="w-full display" data-datatable-url="{{ $datatableUrl }}">
                <thead>
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Therapist</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Payments</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Total Amount</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-finance-pay-stub-report-index.js'])
    </x-slot>
</x-admin.layouts.app>
