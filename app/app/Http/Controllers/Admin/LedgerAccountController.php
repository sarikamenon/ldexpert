<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\LedgerAccountRowTransformer;
use App\DataTables\Transformers\LedgerEntryRowTransformer;
use App\DataTables\Transformers\TransactionRowTransformer;
use App\Domain\Finance\Services\LedgerAccountService;
use App\DTOs\AllTransactionsFilterDTO;
use App\DTOs\LedgerAccountsFilterDTO;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ledger\LedgerAccountsDataRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountsExportRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountsIndexRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountTransactionsDataRequest;
use App\Http\Requests\Admin\Ledger\LedgerAllTransactionsDataRequest;
use App\Http\Requests\Admin\Ledger\LedgerAllTransactionsExportRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\School;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        0 => 'ledger_entries.recorded_at',
        6 => 'ledger_entries.notes',
    ];

    private const ALL_TRANSACTIONS_ORDER_WHITELIST = [
        0 => 'ledger_entries.recorded_at',
        6 => 'ledger_entries.notes',
    ];

    public function __construct(
        private readonly LedgerAccountService $ledgerAccountService,
    ) {}

    public function index(LedgerAccountsIndexRequest $request): View
    {
        $filters = LedgerAccountsFilterDTO::fromArray($request->validated());
        $accountType = $filters->type;

        $summary = [];
        if ($accountType !== 'all-transactions') {
            try {
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
            } catch (\Throwable $e) {
                Log::error('Failed to load ledger account summary', [
                    'error' => $e->getMessage(),
                    'type' => $accountType,
                ]);
            }
        }

        $schools = School::orderBy('display_name')->get(['id', 'full_name', 'display_name']);
        $therapists = User::byRole(Role::THERAPIST)->orderBy('name')->get(['id', 'name']);

        return view('admin.ledger.accounts.index', [
            'accounts' => collect(),
            'accountType' => $accountType,
            'summary' => $summary,
            'datatableUrl' => route('admin.ledger.accounts.data'),
            'allTransactionsDatatableUrl' => route('admin.ledger.accounts.all-transactions.data'),
            'schools' => $schools,
            'therapists' => $therapists,
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
            $account = User::byRole(Role::THERAPIST)->findOrFail($id);
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

    public function statsData(Request $request, string $type, int $id): JsonResponse
    {
        if ($type === 'school') {
            $account = School::findOrFail($id);
        } elseif ($type === 'therapist') {
            $account = User::byRole(Role::THERAPIST)->findOrFail($id);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported account type.',
            ], 422);
        }

        try {
            $stats = $this->ledgerAccountService->calculateAccountStats($account, $type);

            $html = view('admin.ledger.accounts._stats', [
                'stats' => $stats,
                'type' => $type,
            ])->render();

            return response()->json([
                'success' => true,
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load ledger account stats', [
                'error' => $e->getMessage(),
                'type' => $type,
                'account_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not refresh stats.',
            ], 500);
        }
    }

    public function allTransactionsData(LedgerAllTransactionsDataRequest $request): JsonResponse
    {
        $filters = AllTransactionsFilterDTO::fromArray($request->validated());
        $params = DataTablesRequest::fromRequest($request, self::ALL_TRANSACTIONS_ORDER_WHITELIST);

        try {
            $result = $this->ledgerAccountService->listAllEntriesForDataTables($filters, $params);

            return $this->dataTablesResponse(
                $params,
                $result['recordsTotal'],
                $result['recordsFiltered'],
                $result['rows'],
                static fn ($entry) => TransactionRowTransformer::transform($entry),
            );
        } catch (\Throwable $e) {
            Log::error('Failed to load all transactions data', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Could not load transactions.'], 500);
        }
    }

    public function allTransactionsExport(LedgerAllTransactionsExportRequest $request): StreamedResponse
    {
        $filters = AllTransactionsFilterDTO::fromArray($request->validated());
        $limit = \App\Domain\Finance\Services\LedgerAccountService::EXPORT_ROW_LIMIT;

        $entries = $this->ledgerAccountService->listAllEntriesForExport($filters);
        $filename = sprintf('all-transactions-%s.csv', now()->format('Ymd_His'));

        if ($entries->count() >= $limit) {
            abort(422, "Export limited to {$limit} rows. Please narrow your filters.");
        }

        return response()->streamDownload(function () use ($entries): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                throw new \RuntimeException('Unable to open output stream.');
            }

            fputcsv($handle, [
                'Date',
                'Direction',
                'Type',
                'Account',
                'Account Type',
                'Amount',
                'Notes',
                'Recorded By',
            ]);

            foreach ($entries as $entry) {
                $direction = $entry->transaction_type->cashDirection()?->label() ?? 'Accrual';
                $accountName = \App\Domain\Finance\Support\LedgerAccountPresenter::displayName($entry);
                $accountType = \App\Domain\Finance\Support\LedgerAccountPresenter::accountType($entry);

                fputcsv($handle, [
                    $entry->recorded_at->format('Y-m-d'),
                    $direction,
                    $entry->transaction_type->label(),
                    $accountName,
                    $accountType,
                    number_format(abs((float) $entry->amount), 2, '.', ''),
                    $entry->notes ?? '',
                    $entry->recordedBy->name ?? 'System',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
