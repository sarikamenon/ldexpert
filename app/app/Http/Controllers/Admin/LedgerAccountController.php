<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DataTables\Transformers\LedgerAccountRowTransformer;
use App\DataTables\Transformers\LedgerEntryRowTransformer;
use App\Domain\Finance\Services\LedgerAccountService;
use App\Domain\Finance\Services\LedgerService;
use App\DTOs\CreateLedgerAdjustmentDTO;
use App\DTOs\LedgerAccountsFilterDTO;
use App\DTOs\UpdateLedgerAdjustmentDTO;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ledger\CreateLedgerAdjustmentRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountsDataRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountsExportRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountsIndexRequest;
use App\Http\Requests\Admin\Ledger\LedgerAccountTransactionsDataRequest;
use App\Http\Requests\Admin\Ledger\UpdateLedgerAdjustmentRequest;
use App\Http\Support\DataTablesRequest;
use App\Http\Support\DataTablesResponse;
use App\Models\LedgerEntry;
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

    public function __construct(
        private readonly LedgerAccountService $ledgerAccountService,
        private readonly LedgerService $ledgerService,
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

    public function statsData(Request $request, string $type, int $id): JsonResponse
    {
        if ($type === 'school') {
            $account = School::findOrFail($id);
        } elseif ($type === 'therapist') {
            $account = User::where('role', Role::THERAPIST)->findOrFail($id);
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

    public function storeAdjustment(CreateLedgerAdjustmentRequest $request, string $type, int $id): JsonResponse
    {
        $validated = $request->validated();
        $validated['type'] = $type;
        $validated['account_id'] = $id;

        $dto = CreateLedgerAdjustmentDTO::fromArray($validated);
        /** @var \App\Models\User $admin */
        $admin = $request->user();
        $recordedById = (int) $admin->id;

        $recordedAt = LedgerService::resolveDateOnlyRecordedAt($dto->recordedAt);

        try {
            $entry = match ([$dto->type, $dto->transactionType]) {
                ['school', TransactionType::CREDIT_NOTE] => $this->ledgerService->createCreditNoteForSchool(
                    $dto->accountId,
                    $dto->amount,
                    $dto->notes,
                    $recordedById,
                    $recordedAt,
                ),
                ['school', TransactionType::REFUND] => $this->ledgerService->createRefundForSchool(
                    $dto->accountId,
                    $dto->amount,
                    $dto->notes,
                    $recordedById,
                    $recordedAt,
                ),
                ['therapist', TransactionType::CREDIT_NOTE] => $this->ledgerService->createCreditNoteForTherapist(
                    $dto->accountId,
                    $dto->amount,
                    $dto->notes,
                    $recordedById,
                    $recordedAt,
                ),
                ['therapist', TransactionType::REFUND] => $this->ledgerService->createRefundForTherapist(
                    $dto->accountId,
                    $dto->amount,
                    $dto->notes,
                    $recordedById,
                    $recordedAt,
                ),
                default => throw new \InvalidArgumentException('Unsupported adjustment type combination.'),
            };

            $message = $dto->transactionType === TransactionType::CREDIT_NOTE
                ? 'Credit note recorded successfully.'
                : 'Refund recorded successfully.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'entry_id' => $entry->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to create ledger adjustment', [
                'error' => $e->getMessage(),
                'type' => $type,
                'account_id' => $id,
                'transaction_type' => $dto->transactionType->value,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while recording the adjustment.',
            ], 500);
        }
    }

    public function showAdjustment(LedgerEntry $entry): JsonResponse
    {
        if (! $this->isAdjustmentRow($entry)) {
            return response()->json([
                'success' => false,
                'message' => 'Only credit notes and refunds can be edited from the ledger.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'entry' => [
                'id' => $entry->id,
                'transaction_type' => $entry->transaction_type->value,
                'amount' => (float) $entry->amount,
                'recorded_at' => $entry->recorded_at->toDateString(),
                'notes' => $entry->notes,
            ],
        ]);
    }

    public function updateAdjustment(UpdateLedgerAdjustmentRequest $request, LedgerEntry $entry): JsonResponse
    {
        if (! $this->isAdjustmentRow($entry)) {
            return response()->json([
                'success' => false,
                'message' => 'Only credit notes and refunds can be edited from the ledger.',
            ], 403);
        }

        $dto = UpdateLedgerAdjustmentDTO::fromArray($request->validated());

        try {
            $this->ledgerService->editAdjustment($entry, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Adjustment updated successfully.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to update ledger adjustment', [
                'error' => $e->getMessage(),
                'entry_id' => $entry->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the adjustment.',
            ], 500);
        }
    }

    public function destroyAdjustment(LedgerEntry $entry): JsonResponse
    {
        if (! $this->isAdjustmentRow($entry)) {
            return response()->json([
                'success' => false,
                'message' => 'Only credit notes and refunds can be deleted from the ledger.',
            ], 403);
        }

        try {
            $this->ledgerService->deleteAdjustment($entry);

            return response()->json([
                'success' => true,
                'message' => 'Adjustment deleted successfully.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to delete ledger adjustment', [
                'error' => $e->getMessage(),
                'entry_id' => $entry->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the adjustment.',
            ], 500);
        }
    }

    private function isAdjustmentRow(LedgerEntry $entry): bool
    {
        return $entry->transaction_type === TransactionType::CREDIT_NOTE
            || $entry->transaction_type === TransactionType::REFUND;
    }
}
