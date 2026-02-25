<?php

declare(strict_types=1);

namespace App\Domain\Contract\Repositories;

use App\DTOs\ContractServiceRateDTO;
use App\DTOs\CreateSchoolContractDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SchoolContractFilterDTO;
use App\DTOs\UpdateSchoolContractDTO;
use App\Enums\ContractStatus;
use App\Enums\RateType;
use App\Models\SchoolContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SchoolContractRepositoryInterface
{
    public function paginate(SchoolContractFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SchoolContract>}
     */
    public function listForDataTables(SchoolContractFilterDTO $filters, DataTablesParamsDTO $params): array;

    public function create(CreateSchoolContractDTO $dto): SchoolContract;

    public function update(SchoolContract $contract, UpdateSchoolContractDTO $dto): SchoolContract;

    /**
     * @param  array<int, ContractServiceRateDTO>  $services
     */
    public function syncServices(SchoolContract $contract, array $services): void;

    public function changeStatus(SchoolContract $contract, ContractStatus $status): SchoolContract;

    public function hasOverlap(int $schoolId, string $startDate, string $endDate, ?int $ignoreId = null): bool;

    public function metrics(): array;

    public function findActiveContractForDate(int $schoolId, string $date): ?SchoolContract;

    /**
     * @return array{rate_type: RateType, rate_amount: float, no_show_rate: float|null, no_show_rate_type: RateType|null}|null
     */
    public function getServiceRate(int $contractId, int $serviceId): ?array;
}
