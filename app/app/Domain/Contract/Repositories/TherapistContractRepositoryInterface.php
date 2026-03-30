<?php

declare(strict_types=1);

namespace App\Domain\Contract\Repositories;

use App\DTOs\ContractServiceRateDTO;
use App\DTOs\CreateTherapistContractDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\TherapistContractFilterDTO;
use App\DTOs\UpdateTherapistContractDTO;
use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Models\TherapistContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TherapistContractRepositoryInterface
{
    /** @return LengthAwarePaginator<int, TherapistContract> */
    public function paginate(TherapistContractFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, TherapistContract>}
     */
    public function listForDataTables(TherapistContractFilterDTO $filters, DataTablesParamsDTO $params): array;

    /** @param array<string, mixed> $documentData */
    public function create(CreateTherapistContractDTO $dto, array $documentData = []): TherapistContract;

    /** @param array<string, mixed> $documentData */
    public function update(TherapistContract $contract, UpdateTherapistContractDTO $dto, array $documentData = []): TherapistContract;

    /**
     * @param  array<int, ContractServiceRateDTO>  $services
     */
    public function syncServices(TherapistContract $contract, array $services): void;

    public function changeStatus(TherapistContract $contract, ContractStatus $status): TherapistContract;

    public function hasOverlap(int $therapistId, string $startDate, string $endDate, ?int $ignoreId = null): bool;

    /** @return array{total: int, active: int, inactive: int} */
    public function metrics(): array;

    public function findActiveContractForDate(int $therapistId, string $date): ?TherapistContract;

    /**
     * @return array{rate_type: RateType, rate_amount: float, no_show_rate: float|null, no_show_rate_type: RateType|null}|null
     */
    public function getServiceRate(int $contractId, int $serviceId): ?array;
}
