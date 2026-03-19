<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\DTOs\BillingScheduleFilterDTO;
use App\DTOs\DataTablesParamsDTO;
use App\Models\BillingSchedule;
use App\Models\BillingScheduleRun;
use Illuminate\Support\Collection;

interface BillingScheduleRepositoryInterface
{
    public function find(int $id): ?BillingSchedule;

    /**
     * @param  string  $type  'school_invoice'|'private_student_invoice'|'therapist_bill'|'all'
     * @return Collection<int, BillingSchedule>
     */
    public function getDueSchedules(string $type = 'all'): Collection;

    public function getForEntity(string $schedulableType, int $schedulableId, string $scheduleType): ?BillingSchedule;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BillingSchedule;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BillingSchedule $schedule, array $data): BillingSchedule;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, BillingSchedule>}
     */
    public function listForDataTables(BillingScheduleFilterDTO $filters, DataTablesParamsDTO $params): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function logRun(array $data): BillingScheduleRun;

    /**
     * @return Collection<int, BillingScheduleRun>
     */
    public function getRunHistory(int $scheduleId, int $limit = 20): Collection;
}
