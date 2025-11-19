<x-admin.layouts.app>
    <x-page-title title="School Contract #{{ $contract->id }}" />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <x-ui::card class="p-6 space-y-2">
            <p class="text-sm text-foreground/70">School</p>
            <p class="text-lg font-semibold">{{ $contract->school?->display_name ?? '—' }}</p>
        </x-ui::card>

        <x-ui::card class="p-6 space-y-2">
            <p class="text-sm text-foreground/70">Status</p>
            <x-ui::badge :variant="$contract->status === \App\Enums\ContractStatus::ACTIVE ? 'success' : 'secondary'">
                {{ $contract->status->label() }}
            </x-ui::badge>
        </x-ui::card>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-ui::card class="p-6 space-y-2">
            <p class="text-sm text-foreground/70">Start Date</p>
            <p class="text-lg font-semibold">{{ $contract->start_date?->format('M d, Y') }}</p>
        </x-ui::card>
        <x-ui::card class="p-6 space-y-2">
            <p class="text-sm text-foreground/70">End Date</p>
            <p class="text-lg font-semibold">{{ $contract->end_date?->format('M d, Y') }}</p>
        </x-ui::card>
        <x-ui::card class="p-6 space-y-2">
            <p class="text-sm text-foreground/70">Services</p>
            <p class="text-lg font-semibold">{{ $contract->services->count() }}</p>
        </x-ui::card>
    </div>

    <x-ui::card class="p-6 space-y-4 mb-6">
        <h3 class="text-lg font-semibold text-foreground">Notes</h3>
        <p class="text-sm text-foreground/80">{{ $contract->notes ?: 'No notes provided.' }}</p>
    </x-ui::card>

    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Service Rates</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-foreground/70">
                        <th class="py-2 px-3">Service</th>
                        <th class="py-2 px-3">Rate</th>
                        <th class="py-2 px-3">Rate Type</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contract->services as $service)
                        <tr class="border-b border-border/60 last:border-0">
                            <td class="py-2 px-3">{{ $service->service?->name ?? '—' }}</td>
                            <td class="py-2 px-3">${{ number_format((float) $service->rate, 2) }}</td>
                            <td class="py-2 px-3">{{ $service->rate_type->label() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <div class="flex justify-end mt-6">
        <a href="{{ route('admin.contracts.schools.index') }}"
            class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
            Back to list
        </a>
    </div>
</x-admin.layouts.app>
