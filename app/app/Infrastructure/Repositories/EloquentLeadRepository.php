<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Lead\Repositories\LeadRepositoryInterface;
use App\DTOs\ChangeLeadStatusDTO;
use App\DTOs\DataTablesParamsDTO;
use App\DTOs\LeadFilterDTO;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadNote;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class EloquentLeadRepository implements LeadRepositoryInterface
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Lead
    {
        return Lead::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Lead $lead, array $data): Lead
    {
        $lead->update($data);

        /** @var Lead $freshLead */
        $freshLead = $lead->fresh(['school', 'createdBy']);

        return $freshLead;
    }

    public function find(int $id): ?Lead
    {
        return Lead::with(['school', 'createdBy', 'convertedStudent'])->find($id);
    }

    public function findWithNotes(int $id): ?Lead
    {
        return Lead::with([
            'school',
            'createdBy',
            'convertedStudent',
            'leadNotes' => function ($q) {
                $q->with('author')->orderByDesc('created_at');            },
        ])->find($id);
    }

    /**
     * @return array{recordsTotal: int, recordsFiltered: int, rows: EloquentCollection<int, Lead>}
     */
    public function listForDataTables(LeadFilterDTO $filters, DataTablesParamsDTO $params): array
    {
        $baseQuery = Lead::query()
            ->leftJoin('schools', 'leads.school_id', '=', 'schools.id')
            ->select('leads.*');

        $this->applyFilters($baseQuery, $filters);

        $recordsTotal = (clone $baseQuery)->count('leads.id');

        if ($params->searchValue) {
            $baseQuery->search($params->searchValue);        }

        $recordsFiltered = (clone $baseQuery)->count('leads.id');

        $orderColumn = $params->orderColumn ?? 'leads.created_at';
        $orderDir = $params->orderDir === 'desc' ? 'desc' : 'asc';
        $baseQuery->orderBy($orderColumn, $orderDir);

        $rows = (clone $baseQuery)
            ->with(['school', 'createdBy'])
            ->skip($params->start)
            ->take($params->length)
            ->get();

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'rows' => $rows,
        ];
    }

    public function changeStatus(Lead $lead, ChangeLeadStatusDTO $dto): Lead
    {
        $lead->update($dto->toArray());

        /** @var Lead $freshLead */
        $freshLead = $lead->fresh();

        return $freshLead;
    }

    /** @return array{total: int, active_pipeline: int, overdue_follow_ups: int, this_month: int} */
    public function getMetrics(): array
    {
        $activeValues = array_map(
            static fn (LeadStatus $s): string => $s->value,
            LeadStatus::activeStatuses()
        );

        $total = Lead::count();
        $activePipeline = Lead::whereIn('status', $activeValues)->count();
        $overdueFollowUps = Lead::query()->overdueFollowUp()->count();        $thisMonth = Lead::where('created_at', '>=', Carbon::now()->startOfMonth())->count();

        return [
            'total' => $total,
            'active_pipeline' => $activePipeline,
            'overdue_follow_ups' => $overdueFollowUps,
            'this_month' => $thisMonth,
        ];
    }

    /** @return EloquentCollection<int, Lead> */
    public function getOverdueFollowUps(): EloquentCollection
    {
        return Lead::query()
            ->overdueFollowUp()            ->with(['school', 'createdBy'])
            ->orderBy('follow_up_date')
            ->get();
    }

    /** @return EloquentCollection<int, Lead> */
    public function getFollowUpsOnDate(string $date): EloquentCollection
    {
        return Lead::query()
            ->followUpDueOn($date)            ->with(['school', 'createdBy'])
            ->get();
    }

    public function createNote(Lead $lead, int $authorId, string $note): LeadNote
    {
        return LeadNote::create([
            'lead_id' => $lead->id,
            'author_id' => $authorId,
            'note' => $note,
        ]);
    }

    public function delete(Lead $lead): bool
    {
        return (bool) $lead->delete();
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    private function applyFilters(Builder $query, LeadFilterDTO $filters): Builder
    {
        if ($filters->search) {
            $query->search($filters->search);        }

        if ($filters->status) {
            $query->byStatus($filters->status);        }

        if ($filters->source) {
            $query->where('leads.source', $filters->source);
        }

        if ($filters->schoolId) {
            $query->where('leads.school_id', $filters->schoolId);
        }

        return $query;
    }
}
