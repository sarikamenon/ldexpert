<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">My Pay Stubs</h1>
                <p class="text-sm text-foreground/60 mt-1">View and download your pay stubs by calendar year</p>
            </div>

            <x-ui::card class="p-6">
                @if (count($yearRows) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-border">
                                    <th class="text-left py-3 px-4 text-xs font-medium text-foreground/70 uppercase tracking-wider">Year</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium text-foreground/70 uppercase tracking-wider">Payments</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium text-foreground/70 uppercase tracking-wider">Total Amount</th>
                                    <th class="text-left py-3 px-4 text-xs font-medium text-foreground/70 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($yearRows as $row)
                                    <tr class="hover:bg-muted/30 transition-colors">
                                        <td class="py-3 px-4 text-sm font-medium text-foreground">{{ $row['year'] }}</td>
                                        <td class="py-3 px-4 text-sm text-foreground">{{ $row['payment_count'] }}</td>
                                        <td class="py-3 px-4 text-sm text-foreground">${{ number_format($row['total_amount'], 2) }}</td>
                                        <td class="py-3 px-4">
                                            <a href="{{ route('therapist.finance.pay-stub.download', ['year' => $row['year']]) }}"
                                                class="inline-flex items-center justify-center w-8 h-8 bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                title="Download {{ $row['year'] }} Pay Stub PDF"
                                                aria-label="Download {{ $row['year'] }} pay stub as PDF">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                                </svg>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-ui::empty-state
                        title="No pay stubs found"
                        description="No payments have been recorded for your account yet." />
                @endif
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
