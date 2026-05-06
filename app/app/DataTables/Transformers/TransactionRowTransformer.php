<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\Domain\Finance\Support\LedgerAccountPresenter;
use App\Enums\TransactionType;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LedgerEntry;
use App\Models\School;
use App\Models\TherapistBill;
use App\Models\TherapistBillPayment;
use App\Models\User;
use Illuminate\Support\Str;

final class TransactionRowTransformer
{
    /**
     * @return array<int, string> 8 cell HTML strings
     */
    public static function transform(LedgerEntry $entry): array
    {
        $dateCell = '<div class="whitespace-nowrap">'.$entry->recorded_at->format('M d, Y').'</div>';

        $variant = match ($entry->transaction_type) {
            TransactionType::INVOICE_GENERATED, TransactionType::BILL_GENERATED => 'primary',
            TransactionType::PAYMENT_RECEIVED, TransactionType::CREDIT_NOTE => 'success',
            TransactionType::PAYMENT_MADE, TransactionType::REFUND, TransactionType::EXPENSE => 'danger',
        };
        $badgeClass = match ($variant) {
            'primary' => 'bg-primary/10 text-primary border border-primary/20',
            'success' => 'bg-success/10 text-success border border-success/20',
            'danger' => 'bg-danger/10 text-danger border border-danger/20',
        };
        $typeCell = '<span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium whitespace-nowrap '.$badgeClass.'">'
            .e($entry->transaction_type->label()).'</span>';

        $accountName = LedgerAccountPresenter::displayName($entry);
        $accountType = LedgerAccountPresenter::accountType($entry);
        $ledgerable = $entry->ledgerable;

        if ($ledgerable instanceof School) {
            $accountLink = '<a href="'.e(route('admin.schools.show', $ledgerable->id)).'" class="font-medium text-sm text-primary hover:underline">'.e($accountName).'</a>';
        } elseif ($ledgerable instanceof User && $accountType === 'Therapist') {
            $accountLink = '<a href="'.e(route('admin.therapists.show', $ledgerable->id)).'" class="font-medium text-sm text-primary hover:underline">'.e($accountName).'</a>';
        } else {
            $accountLink = '<span class="font-medium text-sm">'.e($accountName).'</span>';
        }

        $accountCell = $accountLink.'<div class="text-xs text-foreground/60">'.e($accountType).'</div>';

        $refCell = self::renderReferenceCell($entry);

        $isDebit = in_array($entry->transaction_type->value, ['invoice_generated', 'bill_generated', 'refund', 'payment_made', 'expense'], true);

        $debitCell = $isDebit
            ? '<span class="font-semibold text-danger-600">$'.number_format(abs((float) $entry->amount), 2).'</span>'
            : '<span class="text-foreground/30">—</span>';

        $creditCell = ! $isDebit
            ? '<span class="font-semibold text-success-600">$'.number_format(abs((float) $entry->amount), 2).'</span>'
            : '<span class="text-foreground/30">—</span>';

        $notesCell = $entry->notes
            ? '<span class="text-foreground/80">'.e(Str::limit($entry->notes, 60)).'</span>'
            : '<span class="text-foreground/30">—</span>';

        $recordedByCell = e($entry->recordedBy->name ?? 'System');

        return [
            $dateCell,
            $typeCell,
            $accountCell,
            $refCell,
            $debitCell,
            $creditCell,
            $notesCell,
            $recordedByCell,
        ];
    }

    private static function renderReferenceCell(LedgerEntry $entry): string
    {
        $ref = $entry->reference;
        if ($ref === null) {
            return '<span class="text-foreground/40">—</span>';
        }

        $refType = $entry->reference_type;

        if ($ref instanceof Invoice && $refType === Invoice::class) {
            return '<a href="'.e(route('admin.invoices.show', ['invoice' => $ref->id])).'" class="text-primary hover:underline">Invoice #'
                .e($ref->invoice_number ?? (string) $ref->id).'</a>';
        }

        if ($ref instanceof TherapistBill && $refType === TherapistBill::class) {
            return '<a href="'.e(route('admin.billing.therapist-bills.show', ['bill' => $ref->id])).'" class="text-primary hover:underline">Bill #'
                .e($ref->bill_number ?? (string) $ref->id).'</a>';
        }

        if ($ref instanceof InvoicePayment && $refType === InvoicePayment::class) {
            $invoiceId = $ref->invoice?->id;

            return $invoiceId
                ? '<a href="'.e(route('admin.invoices.show', ['invoice' => $invoiceId])).'" class="text-primary hover:underline">Payment #'.$ref->id.'</a>'
                : 'Payment #'.($entry->reference_id ?? 'N/A');
        }

        if ($ref instanceof TherapistBillPayment && $refType === TherapistBillPayment::class) {
            $billId = $ref->therapistBill?->id;

            return $billId
                ? '<a href="'.e(route('admin.billing.therapist-bills.show', ['bill' => $billId])).'" class="text-primary hover:underline">Payment #'.$ref->id.'</a>'
                : 'Payment #'.($entry->reference_id ?? 'N/A');
        }

        return e(class_basename($refType ?? '')).' #'.($entry->reference_id ?? '');
    }
}
