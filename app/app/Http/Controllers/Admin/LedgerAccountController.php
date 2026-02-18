<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Services\LedgerAccountService;
use App\DTOs\LedgerAccountsFilterDTO;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ledger\LedgerAccountsIndexRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\Ledger\LedgerAccountsExportRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LedgerAccountController extends Controller
{
    public function __construct(
        private readonly LedgerAccountService $ledgerAccountService,
    ) {}

    public function index(LedgerAccountsIndexRequest $request): View
    {
        $filters = LedgerAccountsFilterDTO::fromArray($request->validated());
        $accountType = $filters->type;

        if ($accountType === 'schools') {
            $accounts = $this->ledgerAccountService->listSchoolAccounts($filters);
        } else {
            $accounts = $this->ledgerAccountService->listTherapistAccounts($filters);
        }

        return view('admin.ledger.accounts.index', compact('accounts', 'accountType'));
    }

    public function export(LedgerAccountsExportRequest $request): StreamedResponse
    {
        $filters = LedgerAccountsFilterDTO::fromArray($request->validated());
        $accountType = $filters->type;

        if ($accountType === 'schools') {
            $accounts = $this->ledgerAccountService->listSchoolAccounts($filters);
            $filename = sprintf('school-ledger-accounts-%s.csv', now()->format('Ymd_His'));
        } else {
            $accounts = $this->ledgerAccountService->listTherapistAccounts($filters);
            $filename = sprintf('therapist-ledger-accounts-%s.csv', now()->format('Ymd_His'));
        }

        return response()->streamDownload(function () use ($accounts, $accountType): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                $accountType === 'schools' ? 'School' : 'Therapist',
                'Email',
                'Phone',
                $accountType === 'schools' ? 'Total Invoiced' : 'Total Billed',
                'Total Paid',
                'Outstanding',
                'Current Balance',
                'Transactions',
            ]);

            foreach ($accounts as $account) {
                fputcsv($handle, [
                    $account->name,
                    $account->email ?? null,
                    $account->phone ?? null,
                    $accountType === 'schools' ? $account->total_invoiced : $account->total_billed,
                    $account->total_paid,
                    $account->outstanding,
                    $account->current_balance,
                    $account->transaction_count,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function show(Request $request, string $type, int $id): View
    {
        if ($type === 'school') {
            $account = School::findOrFail($id);
            $accountName = $account->name;
            $accountType = 'School';
        } else {
            $account = User::where('role', Role::THERAPIST)->findOrFail($id);
            $accountName = $account->name;
            $accountType = 'Therapist';
        }

        // Get ledger entries
        $ledgerEntries = $account->ledgerEntries()
            ->with(['reference', 'recordedBy'])
            ->orderByDesc('created_at')
            ->paginate(50);

        $stats = $this->ledgerAccountService->calculateAccountStats($account, $type);

        return view('admin.ledger.accounts.show', compact(
            'account',
            'accountName',
            'accountType',
            'ledgerEntries',
            'stats',
            'type',
            'id'
        ));
    }
}
