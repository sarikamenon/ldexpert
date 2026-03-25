<?php

declare(strict_types=1);

namespace App\Domain\QGlobRequest\Repositories;

use App\DTOs\CreateQGlobRequestDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\QGlobRequestFilterDTO;
use App\DTOs\RespondQGlobRequestDTO;
use App\Models\QGlobRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

interface QGlobRequestRepositoryInterface
{
    public function create(CreateQGlobRequestDTO $dto): QGlobRequest;

    public function find(int $id): ?QGlobRequest;

    public function respond(QGlobRequest $request, RespondQGlobRequestDTO $dto): QGlobRequest;

    public function markCompleted(QGlobRequest $request, int $adminId): QGlobRequest;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, QGlobRequest>}
     */
    public function listForDataTables(
        QGlobRequestFilterDTO $filters,
        DataTablesParamsDTO $params,
        ?int $therapistId,
    ): array;

    /**
     * Students on therapist caseload with active evaluation SSAs.
     *
     * @return EloquentCollection<int, User>
     */
    public function listEligibleStudentsForTherapist(int $therapistId): EloquentCollection;

    public function isStudentEligibleForTherapist(int $studentId, int $therapistId): bool;
}
