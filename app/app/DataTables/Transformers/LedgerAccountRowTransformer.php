<?php

declare(strict_types=1);

namespace App\DataTables\Transformers;

use App\DataTables\ActionButtons;
use App\Models\School;
use App\Models\User;

final class LedgerAccountRowTransformer
{
    /**
     * @param  School|User  $account
     * @return array<int, string> 7 cell HTML strings
     */
    public static function transform(object $account, string $accountType): array
    {
        $isSchool = $accountType === 'schools';
        $name = $account->name ?? '';
        $createdAt = $account->created_at?->format('M Y') ?? '';

        if ($account instanceof School) {
            $showUrl = route('admin.schools.show', $account);
        } else {
            $showUrl = route('admin.therapists.show', $account);
        }
        $ledgerShowUrl = route('admin.ledger.accounts.show', [
            'type' => $isSchool ? 'school' : 'therapist',
            'id' => $account->id,
        ]);

        $nameCell = '<div class="font-medium">'
            .'<a href="'.e($showUrl).'" class="text-primary hover:underline">'.e($name).'</a></div>'
            .'<div class="text-xs text-foreground/60">Member since '.e($createdAt).'</div>';

        $email = $account instanceof School ? ($account->contact_email ?? null) : ($account->email ?? null);
        $phone = $account instanceof School ? ($account->contact_phone ?? null) : null;
        $contactCell = '';
        if ($email) {
            $contactCell .= '<div class="text-foreground">'.e($email).'</div>';
        }
        if ($phone) {
            $contactCell .= '<div class="text-foreground/80 text-xs">'.e($phone).'</div>';
        }
        if ($contactCell === '') {
            $contactCell = '—';
        }

        $invoicedOrBilled = $isSchool ? ($account->total_invoiced ?? 0) : ($account->total_billed ?? 0);
        $invoiceCount = $isSchool ? ($account->invoices_count ?? 0) : ($account->bills_count ?? 0);
        $invoicedLabel = $isSchool ? 'invoice(s)' : 'bill(s)';
        $invoicedCell = '<div class="font-semibold '.($isSchool ? 'text-success-600' : 'text-danger-600').'">'
            .'$'.number_format((float) $invoicedOrBilled, 2).'</div>'
            .'<div class="text-xs text-foreground/60">'.(int) $invoiceCount.' '.$invoicedLabel.'</div>';

        $totalPaid = $account->total_paid ?? 0;
        $paidCell = '<span class="font-semibold text-info-600">$'.number_format((float) $totalPaid, 2).'</span>';

        $balance = $account->current_balance ?? 0;
        $balanceCell = '<span class="font-semibold '.($balance >= 0 ? 'text-success-600' : 'text-danger-600').'">'
            .'$'.number_format(abs((float) $balance), 2).($balance < 0 ? ' DR' : ' CR').'</span>';

        $txCount = $account->transaction_count ?? 0;
        $transactionsCell = '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-secondary/10 text-secondary-700">'
            .(int) $txCount.'</span>';

        $actionsCell = ActionButtons::wrap(
            ActionButtons::view($ledgerShowUrl, 'View Ledger'),
        );

        return [
            $nameCell,
            $contactCell,
            $invoicedCell,
            $paidCell,
            $balanceCell,
            $transactionsCell,
            $actionsCell,
        ];
    }
}
