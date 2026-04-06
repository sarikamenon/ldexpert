<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\LedgerAccountRowTransformer;
use App\DataTables\Transformers\LedgerEntryRowTransformer;
use App\Domain\Finance\Services\LedgerAccountService;
use App\DTOs\LedgerAccountsFilterDTO;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ledger\LedgerAccountsDataRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountsExportRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountsIndexRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountTransactionsDataRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LedgerAccountController extends Controller
{
    use DataTablesResponse;

    private const SCHOOLS_ORDER_WHITELIST = [
        0 => 'schools.display_name',
        1 => 'schools.contact_email',
        2 => 'schools.created_at',
    ];

    private const THERAPISTS_ORDER_WHITELIST = [
        0 => 'users.name',
        1 => 'users.email',
        2 => 'users.created_at',
    ];

    private const LEDGER_ENTRIES_ORDER_WHITELIST = [
        0 => 'ledger_entries.created_at',
        6 => 'ledger_entries.notes',
    ];

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

        $summary = [
            'total_accounts' => $accounts->count(),
            'total_invoiced_or_billed' => $accountType === 'schools'
                ? $accounts->sum('total_invoiced')
                : $accounts->sum('total_billed'),
            'total_paid' => $accounts->sum('total_paid'),
            'total_outstanding' => $accounts->sum('outstanding'),
        ];

        return view('admin.ledger.accounts.index', [
            'accounts' => collect(),
            'accountType' => $accountType,
            'summary' => $summary,
            'datatableUrl' => route('admin.ledger.accounts.data'),
        ]);
    }

    public function data(LedgerAccountsDataRequest $request): JsonResponse
    {
        $accountType = $request->input('filter_type', 'schools');
        $filters = LedgerAccountsFilterDTO::fromArray([
            'type' => $accountType,
            'search' => $request->input('filter_search'),
        ]);
        $whitelist = $accountType === 'schools' ? self::SCHOOLS_ORDER_WHITELIST : self::THERAPISTS_ORDER_WHITELIST;
        $params = DataTablesRequest::fromRequest($request, $whitelist);

        if ($accountType === 'schools') {
            $result = $this->ledgerAccountService->listSchoolAccountsForDataTables($filters, $params);
            $rows = $result['rows'];
        } else {
            $result = $this->ledgerAccountService->listTherapistAccountsForDataTables($filters, $params);
            $rows = $result['rows'];
        }

        $transform = static function ($account) use ($accountType): array {
            return LedgerAccountRowTransformer::transform($account, $accountType);
        };

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $rows,
            $transform,
        );
    }

    public function transactionsData(LedgerAccountTransactionsDataRequest $request): JsonResponse
    {
        $type = $request->input('filter_type');
        $id = (int) $request->input('filter_id');
        $params = DataTablesRequest::fromRequest($request, self::LEDGER_ENTRIES_ORDER_WHITELIST);
        $result = $this->ledgerAccountService->listEntriesForDataTables($type, $id, $params);

        return $this->dataTablesResponse(
            $params,
            $result['recordsTotal'],
            $result['recordsFiltered'],
            $result['rows'],
            static fn ($entry) => LedgerEntryRowTransformer::transform($entry),
        );
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
            if ($handle === false) {
                throw new \RuntimeException('Unable to open output stream.');
            }

            fputcsv($handle, [
                $accountType === 'schools' ? 'School/Family' : 'Therapist',
                'Email',
                'Phone',
                $accountType === 'schools' ? 'Total Invoiced' : 'Total Billed',
                'Total Paid',
                'Outstanding',
                'Current Balance',
                'Transactions',
            ]);

            foreach ($accounts as $account) {
                $name = $account instanceof School
                    ? ($account->display_name ?? $account->full_name ?? ('School/Family #'.$account->id))
                    : $account->name;

                fputcsv($handle, [
                    $name,
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
            $accountName = $account->display_name ?? $account->full_name ?? ('School/Family #'.$account->id);
            $accountType = 'School/Family';
        } else {
            $account = User::where('role', Role::THERAPIST)->findOrFail($id);
            $accountName = $account->name;
            $accountType = 'Therapist';
        }

        $stats = $this->ledgerAccountService->calculateAccountStats($account, $type);

        return view('admin.ledger.accounts.show', [
            'account' => $account,
            'accountName' => $accountName,
            'accountType' => $accountType,
            'ledgerEntries' => collect(),
            'stats' => $stats,
            'type' => $type,
            'id' => $id,
            'datatableUrl' => route('admin.ledger.accounts.transactions.data'),
            'datatableFilterType' => $type,
            'datatableFilterId' => $id,
        ]);
    }
}
