<?php

declare(strict_types=1);

namespace App\Domain\QGlobRequest\Services;

use App\Domain\QGlobRequest\Repositories\QGlobRequestRepositoryInterface;
use App\DTOs\CreateQGlobRequestDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\QGlobRequestFilterDTO;
use App\DTOs\RespondQGlobRequestDTO;
use App\Models\QGlobRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class QGlobRequestService
{
    public function __construct(
        private readonly QGlobRequestRepositoryInterface $repository,
    ) {}

    public function create(CreateQGlobRequestDTO $dto): QGlobRequest
    {
        return $this->repository->create($dto);
    }

    public function find(int $id): ?QGlobRequest
    {
        return $this->repository->find($id);
    }

    public function respond(QGlobRequest $request, RespondQGlobRequestDTO $dto): QGlobRequest
    {
        return $this->repository->respond($request, $dto);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, QGlobRequest>}
     */
    public function listForDataTables(
        QGlobRequestFilterDTO $filters,
        DataTablesParamsDTO $params,
        ?int $therapistId,
    ): array {
        return $this->repository->listForDataTables($filters, $params, $therapistId);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function listEligibleStudentsForTherapist(int $therapistId): EloquentCollection
    {
        return $this->repository->listEligibleStudentsForTherapist($therapistId);
    }

    public function studentIsEligibleForTherapist(int $studentId, int $therapistId): bool
    {
        return $this->repository->isStudentEligibleForTherapist($studentId, $therapistId);
    }

    public function delete(QGlobRequest $request): bool
    {
        return $this->repository->delete($request);
    }
}
