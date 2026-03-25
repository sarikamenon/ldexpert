<?php

declare(strict_types=1);

namespace App\Domain\Contract\Services;

use App\Domain\Contract\Repositories\SchoolContractRepositoryInterface;
use App\Domain\Storage\Services\StorageServiceInterface;
use App\DTOs\ChangeContractStatusDTO;
use App\DTOs\CreateSchoolContractDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\SchoolContractFilterDTO;
use App\DTOs\UpdateSchoolContractDTO;
use App\Enums\ContractStatus;
use App\Exceptions\ContractOverlapException;
use App\Models\SchoolContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SchoolContractService
{
    public function __construct(
        private readonly SchoolContractRepositoryInterface $repository,
        private readonly StorageServiceInterface $storageService,
    ) {}

    /** @return LengthAwarePaginator<int, SchoolContract> */
    public function paginate(SchoolContractFilterDTO $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: Collection<int, SchoolContract>}
     */
    public function listForDataTables(SchoolContractFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /** @return array{total: int, active: int, inactive: int} */
    public function metrics(): array
    {
        return $this->repository->metrics();
    }

    public function create(CreateSchoolContractDTO $dto): SchoolContract
    {
        $this->guardAgainstOverlap($dto->schoolId, $dto->startDate->toDateString(), $dto->endDate->toDateString());

        $documentData = $dto->document ? $this->storeDocument($dto->document) : [];

        return DB::transaction(function () use ($dto, $documentData) {
            $contract = $this->repository->create($dto, $documentData);
            $this->repository->syncServices($contract, $dto->services);

            return $contract->load(['school', 'services.service']);
        });
    }

    public function update(SchoolContract $contract, UpdateSchoolContractDTO $dto): SchoolContract
    {
        if ($dto->status === ContractStatus::ACTIVE) {
            $this->guardAgainstOverlap(
                $contract->school_id,
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
                $contract->id,
            );
        }

        $documentData = $this->resolveDocumentDataForUpdate($contract, $dto->document, $dto->removeDocument);

        return DB::transaction(function () use ($contract, $dto, $documentData) {
            $updated = $this->repository->update($contract, $dto, $documentData);
            $this->repository->syncServices($updated, $dto->services);

            return $updated->load(['school', 'services.service']);
        });
    }

    public function changeStatus(SchoolContract $contract, ChangeContractStatusDTO $dto): SchoolContract
    {
        if ($dto->status === ContractStatus::ACTIVE) {
            $this->guardAgainstOverlap(
                $contract->school_id,
                $contract->start_date->toDateString(),
                $contract->end_date->toDateString(),
                $contract->id,
            );
        }

        return $this->repository->changeStatus($contract, $dto->status);
    }

    public function downloadDocument(SchoolContract $contract): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (empty($contract->document_path) || ! $this->storageService->exists($contract->document_path)) {
            abort(404, 'Document not found');
        }

        return $this->storageService->download($contract->document_path, $contract->document_name ?? 'document');
    }

    private function guardAgainstOverlap(int $schoolId, string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        if ($this->repository->hasOverlap($schoolId, $startDate, $endDate, $ignoreId)) {
            throw new ContractOverlapException('An active contract already exists for this school in the selected period.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function storeDocument(UploadedFile $file): array
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);
        $originalName = $file->getClientOriginalName();
        $filename = "{$timestamp}_{$random}_{$originalName}";
        $path = "contract-documents/{$year}/{$month}/{$filename}";

        $this->storageService->put($path, (string) file_get_contents($file->getRealPath()));

        return [
            'document_path' => $path,
            'document_name' => $originalName,
            'document_mime_type' => $file->getMimeType(),
            'document_size' => $file->getSize(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDocumentDataForUpdate(SchoolContract $contract, ?UploadedFile $newDocument, bool $removeDocument): array
    {
        if ($newDocument) {
            $this->deleteExistingDocument($contract);

            return $this->storeDocument($newDocument);
        }

        if ($removeDocument) {
            $this->deleteExistingDocument($contract);

            return [
                'document_path' => null,
                'document_name' => null,
                'document_mime_type' => null,
                'document_size' => null,
            ];
        }

        return [];
    }

    private function deleteExistingDocument(SchoolContract $contract): void
    {
        if (! empty($contract->document_path) && $this->storageService->exists($contract->document_path)) {
            $this->storageService->delete($contract->document_path);
        }
    }
}
