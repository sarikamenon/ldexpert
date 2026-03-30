<?php

declare(strict_types=1);

namespace App\Domain\Contract\Services;

use App\Domain\Contract\Repositories\TherapistContractRepositoryInterface;
use App\DTOs\ChangeContractStatusDTO;
use App\DTOs\CreateTherapistContractDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\TherapistContractFilterDTO;
use App\DTOs\UpdateTherapistContractDTO;
use App\Enums\ContractStatus;
use App\Exceptions\ContractOverlapException;
use App\Models\TherapistContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TherapistContractService
{
    public function __construct(
        private readonly TherapistContractRepositoryInterface $repository,
        private readonly ContractDocumentService $documentService,
    ) {}

    /** @return LengthAwarePaginator<int, TherapistContract> */
    public function paginate(TherapistContractFilterDTO $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, TherapistContract>}
     */
    public function listForDataTables(TherapistContractFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /** @return array{total: int, active: int, inactive: int} */
    public function metrics(): array
    {
        return $this->repository->metrics();
    }

    public function create(CreateTherapistContractDTO $dto): TherapistContract
    {
        $this->guardAgainstOverlap($dto->therapistId, $dto->startDate->toDateString(), $dto->endDate->toDateString());

        return DB::transaction(function () use ($dto) {
            $documentData = $dto->document ? $this->documentService->store($dto->document) : [];
            $contract = $this->repository->create($dto, $documentData);
            $this->repository->syncServices($contract, $dto->services);

            return $contract->load(['therapist.user', 'services.service']);
        });
    }

    public function update(TherapistContract $contract, UpdateTherapistContractDTO $dto): TherapistContract
    {
        if ($dto->status === ContractStatus::ACTIVE) {
            $this->guardAgainstOverlap(
                $contract->therapist_id,
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
                $contract->id,
            );
        }

        return DB::transaction(function () use ($contract, $dto) {
            $documentData = $this->documentService->resolveForUpdate(
                $contract->document_path,
                $dto->document,
                $dto->removeDocument,
            );
            $updated = $this->repository->update($contract, $dto, $documentData);
            $this->repository->syncServices($updated, $dto->services);

            return $updated->load(['therapist.user', 'services.service']);
        });
    }

    public function changeStatus(TherapistContract $contract, ChangeContractStatusDTO $dto): TherapistContract
    {
        if ($dto->status === ContractStatus::ACTIVE) {
            $this->guardAgainstOverlap(
                $contract->therapist_id,
                $contract->start_date->toDateString(),
                $contract->end_date->toDateString(),
                $contract->id,
            );
        }

        return $this->repository->changeStatus($contract, $dto->status);
    }

    public function downloadDocument(TherapistContract $contract): StreamedResponse
    {
        if (empty($contract->document_path) || ! $this->documentService->exists($contract->document_path)) {
            abort(404, 'Document not found');
        }

        return $this->documentService->download($contract->document_path, $contract->document_name ?? 'document');
    }

    private function guardAgainstOverlap(int $therapistId, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        if ($this->repository->hasOverlap($therapistId, $startDate, $endDate, $ignoreId)) {
            throw new ContractOverlapException('An active contract already exists for this therapist in the selected period.');
        }
    }
}
