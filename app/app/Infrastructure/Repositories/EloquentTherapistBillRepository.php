<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Billing\Repositories\TherapistBillRepositoryInterface;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\TherapistBillFilterDTO;
use App\Enums\SessionLogStatus;
use App\Enums\TherapistBillStatus;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentTherapistBillRepository implements TherapistBillRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): TherapistBill
    {
        return TherapistBill::create($data);
    }

    public function find(int $id): ?TherapistBill
    {
        return TherapistBill::with(['sessionLogs.student', 'sessionLogs.service', 'sessionLogs.therapist', 'therapist', 'sentBy'])
            ->find($id);
    }

    public function list(TherapistBillFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TherapistBill::query()
            ->with(['therapist', 'sessionLogs']);

        if ($filters->therapistId !== null) {
            $query->where('therapist_id', $filters->therapistId);
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->dateFrom !== null) {
            $query->whereDate('billing_period_start', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->whereDate('billing_period_end', '<=', $filters->dateTo);
        }

        if ($filters->billNumber !== null) {
            $query->where('bill_number', 'like', '%'.$filters->billNumber.'%');
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, TherapistBill>}
     */
    public function listForDataTables(TherapistBillFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = TherapistBill::query()->with(['therapist', 'sessionLogs']);

        if ($filters->therapistId !== null) {
            $baseQuery->where('therapist_id', $filters->therapistId);
        }
        if ($filters->status !== null) {
            $baseQuery->where('status', $filters->status->value);
        }
        if ($filters->dateFrom !== null) {
            $baseQuery->whereDate('billing_period_start', '>=', $filters->dateFrom);
        }
        if ($filters->dateTo !== null) {
            $baseQuery->whereDate('billing_period_end', '<=', $filters->dateTo);
        }
        if ($filters->billNumber !== null) {
            $baseQuery->where('bill_number', 'like', '%'.$filters->billNumber.'%');
        }

        $recordsTotal = (clone $baseQuery)->count('therapist_bills.id');

        if ($params->searchValue) {
            $search = $params->searchValue;
            $baseQuery->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', '%'.$search.'%')
                    ->orWhere('therapist_name', 'like', '%'.$search.'%');
            });
        }
        $recordsFiltered = (clone $baseQuery)->count('therapist_bills.id');

        $orderColumn = $params->orderColumn ?? 'created_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        /** @var Collection<int, TherapistBill> $rows */
        $rows = (clone $baseQuery)
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<int>  $sessionLogIds
     * @return Collection<int, SessionLog>
     */
    public function getApprovedSessionLogsForBilling(array $sessionLogIds): Collection
    {
        return SessionLog::query()
            ->whereIn('id', $sessionLogIds)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_therapist', true)
            ->whereNull('therapist_bill_id')
            ->with(['student', 'service', 'therapist', 'school'])
            ->get();
    }

    /**
     * @param  array<int>  $sessionLogIds
     */
    public function linkSessionLogs(TherapistBill $bill, array $sessionLogIds): void
    {
        SessionLog::whereIn('id', $sessionLogIds)
            ->update(['therapist_bill_id' => $bill->id]);
    }

    public function unlinkAllSessionsForTherapistBill(TherapistBill $bill): void
    {
        SessionLog::where('therapist_bill_id', $bill->id)
            ->update(['therapist_bill_id' => null]);
    }

    /**
     * @param  array<int>  $sessionLogIds
     * @return Collection<int, SessionLog>
     */
    public function getSessionLogsForTherapistBillUpdate(TherapistBill $bill, array $sessionLogIds): Collection
    {
        if (empty($sessionLogIds)) {
            return collect();
        }

        return SessionLog::query()
            ->whereIn('id', $sessionLogIds)
            ->where('therapist_id', $bill->therapist_id)
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_therapist', true)
            ->where(function ($q) use ($bill): void {
                $q->whereNull('therapist_bill_id')
                    ->orWhere('therapist_bill_id', $bill->id);
            })
            ->with(['student', 'service', 'therapist', 'school'])
            ->get();
    }

    public function updateTotals(TherapistBill $bill, float $subtotal, float $adjustmentsTotal, float $totalDue): TherapistBill
    {
        $bill->update([
            'subtotal' => $subtotal,
            'adjustments_total' => $adjustmentsTotal,
            'total_due' => $totalDue,
        ]);

        return $bill->refresh();
    }

    public function markAsSent(TherapistBill $bill, int $sentById): TherapistBill
    {
        $bill->update([
            'status' => TherapistBillStatus::SENT->value,
            'sent_at' => now(),
            'sent_by_id' => $sentById,
        ]);

        return $bill->refresh();
    }

    public function generateBillNumber(): string
    {
        $date = now()->format('Ymd');
        $lastBill = TherapistBill::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        if ($lastBill && preg_match('/BILL-(\d{8})-(\d{3})/', $lastBill->bill_number, $matches)) {
            $sequence = (int) $matches[2] + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('BILL-%s-%03d', $date, $sequence);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, SessionLog>
     */
    public function getAvailableSessionLogsForBillingCreation(array $filters): Collection
    {
        $query = SessionLog::query()
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_therapist', true)
            ->whereNull('therapist_bill_id')
            ->with(['student', 'service', 'therapist', 'school']);

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('session_date', [$filters['date_from'], $filters['date_to']]);
        }

        if (isset($filters['therapist_id']) && $filters['therapist_id']) {
            $query->where('therapist_id', $filters['therapist_id']);
        }

        if (isset($filters['student_id']) && $filters['student_id']) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['service_id']) && $filters['service_id']) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['school_id']) && $filters['school_id']) {
            $query->where('school_id', $filters['school_id']);
        }

        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($subQ) use ($search) {
                    $subQ->where('name', 'like', "%{$search}%"); // @phpstan-ignore argument.type
                })
                    ->orWhereHas('service', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%"); // @phpstan-ignore argument.type
                    })
                    ->orWhereHas('therapist', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%"); // @phpstan-ignore argument.type
                    });
            });
        }

        return $query->orderBy('session_date', 'desc')->get();
    }

    public function getAvailableTherapistIdsForBillingCreation(array $filters): Collection
    {
        $query = SessionLog::query()
            ->where('status', SessionLogStatus::APPROVED->value)
            ->where('is_billable_therapist', true)
            ->whereNull('therapist_bill_id');

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('session_date', [$filters['date_from'], $filters['date_to']]);
        }

        return $query->distinct()->pluck('therapist_id');
    }

    public function getBillsByTherapist(int $therapistId, TherapistBillFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = TherapistBill::query()
            ->where('therapist_id', $therapistId)
            ->with(['sessionLogs']);

        if ($filters->status !== null) {
            $query->where('status', $filters->status->value);
        }

        if ($filters->dateFrom !== null) {
            $query->whereDate('billing_period_start', '>=', $filters->dateFrom);
        }

        if ($filters->dateTo !== null) {
            $query->whereDate('billing_period_end', '<=', $filters->dateTo);
        }

        if ($filters->billNumber !== null) {
            $query->where('bill_number', 'like', '%'.$filters->billNumber.'%');
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
