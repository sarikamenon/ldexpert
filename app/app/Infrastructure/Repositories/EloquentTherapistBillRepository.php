<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Billing\Repositories\TherapistBillRepositoryInterface;
use App\DTOs\TherapistBillFilterDTO;
use App\Enums\SessionLogStatus;
use App\Enums\TherapistBillStatus;
use App\Models\SessionLog;
use App\Models\TherapistBill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentTherapistBillRepository implements TherapistBillRepositoryInterface
{
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
     * @param  array<int>  $sessionLogIds
     * @return Collection<SessionLog>
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
     * @return Collection<SessionLog>
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
                    $subQ->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('service', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('therapist', function ($subQ) use ($search) {
                        $subQ->where('name', 'like', "%{$search}%");
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
