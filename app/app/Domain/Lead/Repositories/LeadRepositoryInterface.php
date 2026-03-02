<?php

declare(strict_types=1);

namespace App\Domain\Lead\Repositories;

use App\DTOs\ChangeLeadStatusDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\LeadFilterDTO;
use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

interface LeadRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Lead;

    /** @param array<string, mixed> $data */
    public function update(Lead $lead, array $data): Lead;

    public function find(int $id): ?Lead;

    public function findWithNotes(int $id): ?Lead;

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, Lead>}
     */
    public function listForDataTables(LeadFilterDTO $filters, DataTablesParamsDTO $params): array;

    public function changeStatus(Lead $lead, ChangeLeadStatusDTO $dto): Lead;

    /** @return array{total: int, active_pipeline: int, overdue_follow_ups: int, this_month: int} */
    public function getMetrics(): array;

    /** @return EloquentCollection<int, Lead> */
    public function getOverdueFollowUps(): EloquentCollection;

    /** @return EloquentCollection<int, Lead> */
    public function getFollowUpsOnDate(string $date): EloquentCollection;

    public function createNote(Lead $lead, int $authorId, string $note): LeadNote;

    public function delete(Lead $lead): bool;
}
