<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Repositories\LedgerEntryRepositoryInterface;
use App\DTOs\AllTransactionsFilterDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\LedgerAccountsFilterDTO;
use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Collection;

final class LedgerAccountService
{
    public function __construct(
        private readonly LedgerEntryRepositoryInterface $ledgerEntries,
    ) {}

    /**
     * @return Collection<int, mixed>
     */
    public function listSchoolAccounts(LedgerAccountsFilterDTO $filters): Collection
    {
        $query = School::query()
            ->select([
                'schools.id',
                'schools.full_name',
                'schools.display_name',
                'schools.contact_email',
                'schools.contact_phone',
                'schools.created_at',
            ])
            ->withCount('invoices')
            ->leftJoin('invoices', 'schools.id', '=', 'invoices.school_id')
            ->groupBy(
                'schools.id',
                'schools.full_name',
                'schools.display_name',
                'schools.contact_email',
                'schools.contact_phone',
                'schools.created_at'
            );

        if ($filters->search) {
            $search = $filters->search;

            $query->where(function ($q) use ($search) {
                $q->where('schools.full_name', 'like', "%{$search}%")
                    ->orWhere('schools.display_name', 'like', "%{$search}%")
                    ->orWhere('schools.contact_email', 'like', "%{$search}%");
            });
        }

        $accounts = $query->get()->map(function (School $school) {
            $stats = $this->ledgerEntries->getSchoolStats($school->id);

            $school->setAttribute('total_invoiced', $stats['total_invoiced']);
            $school->setAttribute('total_paid', $stats['total_paid']);
            $school->setAttribute('outstanding', $stats['outstanding']);
            $school->setAttribute('current_balance', $stats['current_balance']);
            $school->setAttribute('transaction_count', $stats['transaction_count']);

            return $school;
        });

        return $accounts->sortByDesc('outstanding')->values();
    }

    /**
     * @return Collection<int, mixed>
     */
    public function listTherapistAccounts(LedgerAccountsFilterDTO $filters): Collection
    {
        $query = User::query()
            ->where('role', Role::THERAPIST)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.created_at',
            ]);

        if ($filters->search) {
            $search = $filters->search;

            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $accounts = $query->get()->map(function (User $therapist) {
            $stats = $this->ledgerEntries->getTherapistStats($therapist->id);

            $therapist->setAttribute('total_billed', $stats['total_billed']);
            $therapist->setAttribute('total_paid', $stats['total_paid']);
            $therapist->setAttribute('outstanding', $stats['outstanding']);
            $therapist->setAttribute('current_balance', $stats['current_balance']);
            $therapist->setAttribute('transaction_count', $stats['transaction_count']);
            $therapist->setAttribute('bills_count', $therapist->therapistBills()->count());

            return $therapist;
        });

        return $accounts->sortByDesc('outstanding')->values();
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, School>}
     */
    public function listSchoolAccountsForDataTables(LedgerAccountsFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $query = School::query()
            ->select([
                'schools.id',
                'schools.full_name',
                'schools.display_name',
                'schools.contact_email',
                'schools.contact_phone',
                'schools.created_at',
            ])
            ->withCount('invoices');

        if ($filters->search) {
            $search = $filters->search;
            $query->where(function ($q) use ($search) {
                $q->where('schools.full_name', 'like', "%{$search}%")
                    ->orWhere('schools.display_name', 'like', "%{$search}%")
                    ->orWhere('schools.contact_email', 'like', "%{$search}%");
            });
        }

        $recordsTotal = (clone $query)->count();

        if ($params->searchValue) {
            $sv = $params->searchValue;
            $query->where(function ($q) use ($sv) {
                $q->where('schools.full_name', 'like', "%{$sv}%")
                    ->orWhere('schools.display_name', 'like', "%{$sv}%")
                    ->orWhere('schools.contact_email', 'like', "%{$sv}%")
                    ->orWhere('schools.contact_phone', 'like', "%{$sv}%");
            });
        }
        $recordsFiltered = (clone $query)->count();

        $orderColumn = $params->orderColumn ?? 'schools.display_name';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $query->orderBy($orderColumn, $orderDir);

        $rows = $query->skip($params->start)->take($params->length)->get();

        foreach ($rows as $school) {
            $stats = $this->ledgerEntries->getSchoolStats($school->id);
            $school->setAttribute('total_invoiced', $stats['total_invoiced']);
            $school->setAttribute('total_paid', $stats['total_paid']);
            $school->setAttribute('outstanding', $stats['outstanding']);
            $school->setAttribute('current_balance', $stats['current_balance']);
            $school->setAttribute('transaction_count', $stats['transaction_count']);
        }

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, User>}
     */
    public function listTherapistAccountsForDataTables(LedgerAccountsFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $query = User::query()
            ->where('role', Role::THERAPIST)
            ->select(['users.id', 'users.name', 'users.email', 'users.created_at']);

        if ($filters->search) {
            $search = $filters->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $recordsTotal = (clone $query)->count();

        if ($params->searchValue) {
            $sv = $params->searchValue;
            $query->where(function ($q) use ($sv) {
                $q->where('users.name', 'like', "%{$sv}%")
                    ->orWhere('users.email', 'like', "%{$sv}%");
            });
        }
        $recordsFiltered = (clone $query)->count();

        $orderColumn = $params->orderColumn ?? 'users.name';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $query->orderBy($orderColumn, $orderDir);

        $rows = $query->skip($params->start)->take($params->length)->get();

        foreach ($rows as $user) {
            $stats = $this->ledgerEntries->getTherapistStats($user->id);
            $user->setAttribute('total_billed', $stats['total_billed']);
            $user->setAttribute('total_paid', $stats['total_paid']);
            $user->setAttribute('outstanding', $stats['outstanding']);
            $user->setAttribute('current_balance', $stats['current_balance']);
            $user->setAttribute('transaction_count', $stats['transaction_count']);
            $user->setAttribute('bills_count', $user->therapistBills()->count());
        }

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: \Illuminate\Support\Collection<int, \App\Models\LedgerEntry>}
     */
    public function listEntriesForDataTables(string $type, int $id, DataTablesParamsDTO $params): array
    {
        $ledgerableType = $type === 'school' ? School::class : User::class;

        return $this->ledgerEntries->listForDataTables($ledgerableType, $id, $params);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: \Illuminate\Support\Collection<int, \App\Models\LedgerEntry>}
     */
    public function listAllEntriesForDataTables(AllTransactionsFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->ledgerEntries->listAllForDataTables($filters, $params);
    }

    public const EXPORT_ROW_LIMIT = 50000;

    /** @return \Illuminate\Support\Collection<int, \App\Models\LedgerEntry> */
    public function listAllEntriesForExport(AllTransactionsFilterDTO $filters): \Illuminate\Support\Collection
    {
        return $this->ledgerEntries->listAllForExport($filters, self::EXPORT_ROW_LIMIT);
    }

    /** @return array<string, mixed> */
    public function calculateAccountStats(object $account, string $type): array
    {
        if ($type === 'school' && $account instanceof School) {
            return $this->ledgerEntries->getSchoolStats($account->id);
        }

        if ($type === 'therapist' && $account instanceof User) {
            return $this->ledgerEntries->getTherapistStats($account->id);
        }

        throw new \InvalidArgumentException('Unsupported account type for ledger stats calculation.');
    }
}
