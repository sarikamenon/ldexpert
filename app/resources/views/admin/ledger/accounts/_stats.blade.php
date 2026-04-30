@php
    $balance = (float) ($stats['current_balance'] ?? 0);
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
    @if ($type === 'school')
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Invoiced</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format($stats['total_invoiced'], 2) }}
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['invoice_count'] }} invoice(s)
            </p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Paid</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format($stats['total_paid'], 2) }}
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['payment_count'] }} payment(s)
            </p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Credit Notes</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format($stats['total_credit_notes'] ?? 0, 2) }}
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['credit_note_count'] ?? 0 }} credit note(s)
            </p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Refunds</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format($stats['total_refunds'] ?? 0, 2) }}
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['refund_count'] ?? 0 }} refund(s)
            </p>
        </x-ui::card>
    @else
        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Billed</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format($stats['total_billed'], 2) }}
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['bill_count'] }} bill(s)
            </p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Paid</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format($stats['total_paid'], 2) }}
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['payment_count'] }} payment(s)
            </p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Credit Notes</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format($stats['total_credit_notes'] ?? 0, 2) }}
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['credit_note_count'] ?? 0 }} credit note(s)
            </p>
        </x-ui::card>

        <x-ui::card class="p-4">
            <p class="text-sm text-foreground/70">Total Refunds</p>
            <p class="text-2xl font-bold mt-1">
                ${{ number_format($stats['total_refunds'] ?? 0, 2) }}
            </p>
            <p class="text-xs text-foreground/60 mt-1">
                {{ $stats['refund_count'] ?? 0 }} refund(s)
            </p>
        </x-ui::card>
    @endif

    <x-ui::card class="p-4">
        <p class="text-sm text-foreground/70">Outstanding</p>
        <p class="text-2xl font-bold mt-1">
            ${{ number_format($stats['outstanding'], 2) }}
        </p>
        <p class="text-xs text-foreground/60 mt-1">
            {{ $stats['outstanding'] > 0 ? 'Balance due' : 'Paid in full' }}
        </p>
    </x-ui::card>

    <x-ui::card class="p-4">
        <p class="text-sm text-foreground/70">Current Balance</p>
        <p class="text-2xl font-bold mt-1">
            ${{ number_format(abs($balance), 2) }}
            <span class="text-sm font-normal">
                {{ $balance > 0 ? 'DR' : 'CR' }}
            </span>
        </p>
        <p class="text-xs text-foreground/60 mt-1">
            {{ $stats['transaction_count'] ?? 0 }} transaction(s)
        </p>
    </x-ui::card>
</div>
