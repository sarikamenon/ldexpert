<?php

declare(strict_types=1);

namespace App\Domain\Contract\Repositories;

use App\DTOs\ContractServiceRateDTO;
use App\DTOs\CreateTherapistContractDTO;
use App\DTOs\TherapistContractFilterDTO;
use App\DTOs\UpdateTherapistContractDTO;
use App\Enums\ContractStatus;
use App\Models\TherapistContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TherapistContractRepositoryInterface
{
    public function paginate(TherapistContractFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    public function create(CreateTherapistContractDTO $dto): TherapistContract;

    public function update(TherapistContract $contract, UpdateTherapistContractDTO $dto): TherapistContract;

    /**
     * @param  array<int, ContractServiceRateDTO>  $services
     */
    public function syncServices(TherapistContract $contract, array $services): void;

    public function changeStatus(TherapistContract $contract, ContractStatus $status): TherapistContract;

    public function hasOverlap(int $therapistId, string $startDate, string $endDate, ?int $ignoreId = null): bool;

    public function metrics(): array;

    public function findActiveContractForDate(int $therapistId, string $date): ?TherapistContract;

    public function getServiceRate(int $contractId, int $serviceId): ?array;
}
