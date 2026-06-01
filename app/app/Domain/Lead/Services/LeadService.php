<?php

declare(strict_types=1);

namespace App\Domain\Lead\Services;

use App\Domain\Lead\Repositories\LeadRepositoryInterface;
use App\Domain\School\Services\SchoolService;
use App\Domain\Student\Services\StudentService;
use App\DTOs\ChangeLeadStatusDTO;
use App\DTOs\ConvertLeadDTO;
use App\DTOs\CreateLeadDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\LeadFilterDTO;
use App\DTOs\UpdateLeadDTO;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

final class LeadService
{
    public function __construct(
        private readonly LeadRepositoryInterface $repository,
        private readonly StudentService $studentService,
        private readonly SchoolService $schoolService,
    ) {}

    public function create(CreateLeadDTO $dto): Lead
    {
        return $this->repository->create($dto->toArray());
    }

    public function update(Lead $lead, UpdateLeadDTO $dto): Lead
    {
        return $this->repository->update($lead, $dto->toArray());
    }

    public function changeStatus(Lead $lead, ChangeLeadStatusDTO $dto): Lead
    {
        return $this->repository->changeStatus($lead, $dto);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, Lead>}
     */
    public function listForDataTables(LeadFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        return $this->repository->listForDataTables($filters, $params);
    }

    /** @return array{total: int, active_pipeline: int, overdue_follow_ups: int, this_month: int} */
    public function getMetrics(): array
    {
        return $this->repository->getMetrics();
    }

    public function addNote(Lead $lead, int $authorId, string $note): LeadNote
    {
        return $this->repository->createNote($lead, $authorId, $note);
    }

    public function convertToStudent(Lead $lead, ConvertLeadDTO $dto): StudentProfile
    {
        return DB::transaction(function () use ($lead, $dto): StudentProfile {
            $schoolId = $dto->schoolId;

            if ($schoolId === null) {
                $school = $this->schoolService->createSchool($dto->toCreateSchoolDTO());
                $schoolId = (int) $school->id;
            }

            $studentDTO = $dto->toCreateStudentDTO($lead, $schoolId);
            $profile = $this->studentService->create($studentDTO);

            $this->repository->update($lead, [
                'status' => LeadStatus::ENROLLED->value,
                'converted_student_id' => $profile->user_id,
                'converted_at' => Carbon::now(),
            ]);

            return $profile;
        });
    }

    public function find(int $id): ?Lead
    {
        return $this->repository->find($id);
    }

    public function findWithNotes(int $id): ?Lead
    {
        return $this->repository->findWithNotes($id);
    }

    /** @return EloquentCollection<int, Lead> */
    public function getOverdueFollowUps(): EloquentCollection
    {
        return $this->repository->getOverdueFollowUps();
    }

    /** @return EloquentCollection<int, Lead> */
    public function getFollowUpsOnDate(string $date): EloquentCollection
    {
        return $this->repository->getFollowUpsOnDate($date);
    }

    public function delete(Lead $lead): bool
    {
        return $this->repository->delete($lead);
    }
}
