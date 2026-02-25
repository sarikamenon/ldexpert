<x-admin.layouts.app>
    <x-slot name="styles">
        @vite(['resources/css/common/datatables.css'])
    </x-slot>
    <x-page-title title="IRS Report" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Filters --}}
    <x-ui::card class="p-6 mb-6">
        <form id="irsReportFiltersForm" method="GET" action="{{ route('admin.finance.irs-report.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <x-input-label for="date_from" value="Date From" />
                    <p class="mt-1 text-xs text-foreground/60" id="date_from_help">
                        Filter payments by payment date. Leave blank for no start limit.
                    </p>
                    <x-ui::input type="date" id="date_from" name="date_from"
                        value="{{ $filters['date_from'] ?? '' }}" class="mt-1 block w-full"
                        aria-describedby="date_from_help" />
                </div>
                <div>
                    <x-input-label for="date_to" value="Date To" />
                    <p class="mt-1 text-xs text-foreground/60" id="date_to_help">
                        Filter payments by payment date. Leave blank for no end limit.
                    </p>
                    <x-ui::input type="date" id="date_to" name="date_to"
                        value="{{ $filters['date_to'] ?? '' }}" class="mt-1 block w-full"
                        aria-describedby="date_to_help" />
                </div>
                <div>
                    <x-input-label for="therapist_ids" value="Therapists" />
                    <p class="mt-1 text-xs text-foreground/60" id="therapist_ids_help">
                        Select one or more therapists. Leave empty for all therapists.
                    </p>
                    <x-ui::select id="therapist_ids" name="therapist_ids[]" multiple searchable
                        placeholder="All Therapists" class="mt-1" aria-describedby="therapist_ids_help">
                        @foreach ($therapists as $therapist)
                            <option value="{{ $therapist->id }}" @selected(in_array($therapist->id, $filters['therapist_ids'] ?? []))>
                                {{ $therapist->name }}
                            </option>
                        @endforeach
                    </x-ui::select>
                </div>
            </div>
            <div class="flex gap-2">
                <x-ui::button type="submit">Apply Filters</x-ui::button>
                <x-ui::button type="button" variant="secondary"
                    onclick="window.location.href='{{ route('admin.finance.irs-report.index') }}'">
                    Reset
                </x-ui::button>
                <x-ui::button type="submit" variant="secondary"
                    formaction="{{ route('admin.finance.irs-report.export') }}">
                    Export CSV
                </x-ui::button>
            </div>
        </form>
    </x-ui::card>

    @if (isset($datatableUrl))
        <x-ui::card class="p-6 space-y-4">
            <div class="overflow-x-auto">
                <table id="irsReportTable" class="w-full display" data-datatable-url="{{ $datatableUrl }}">
                    <thead>
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Recipient</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Payment Date</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Payment Method</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Hourly Pay Rate</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Tax Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Payroll Period</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Regular Pay</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Additional Pay</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Deductions</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">YTD Regular Pay</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Total Gross Pay</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Total Net Pay</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                    </tbody>
                </table>
            </div>
        </x-ui::card>
    @elseif (isset($rows) && count($rows) > 0)
        <x-ui::card class="p-6 space-y-4">
            <div class="overflow-x-auto">
                <table id="irsReportTable" class="w-full display">
                    <thead>
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Recipient</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Payment Date</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Payment Method</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Hourly Pay Rate</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Tax Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-foreground/70 uppercase tracking-wider">Payroll Period</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Regular Pay</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Additional Pay</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Deductions</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">YTD Regular Pay</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Total Gross Pay</th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-foreground/70 uppercase tracking-wider">Total Net Pay</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground">{{ $row['recipient'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground">{{ $row['payment_date_display'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground">{{ $row['payment_method'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground text-right">${{ number_format($row['hourly_rate'], 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground">{{ $row['tax_status'] }}</td>
                                <td class="px-4 py-3 text-sm text-foreground">{{ $row['payroll_period'] }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground text-right">${{ number_format($row['regular_pay'], 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground text-right">${{ number_format($row['additional_pay'], 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground text-right">${{ number_format($row['total_deductions'], 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground text-right">${{ number_format($row['ytd_regular_pay'], 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground text-right">${{ number_format($row['total_gross'], 2) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-foreground text-right">${{ number_format($row['total_net'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui::card>
    @else
        <x-ui::card class="p-6">
            <x-ui::empty-state
                title="No payment records found"
                description="No therapist bill payments match your filters. Select a date range and optionally one or more therapists, then click Apply Filters." />
        </x-ui::card>
    @endif

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-finance-irs-report-index.js'])
    </x-slot>
</x-admin.layouts.app>
