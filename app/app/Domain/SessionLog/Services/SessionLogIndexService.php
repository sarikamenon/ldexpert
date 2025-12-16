<?php

declare(strict_types=1);

namespace App\Domain\SessionLog\Services;

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\DTOs\SessionLogIndexDTO;
use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class SessionLogIndexService
{
    public function __construct(
        private readonly SessionLogRepositoryInterface $repository,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getAdminIndex(SessionLogIndexDTO $dto): array
    {
        $paginator = $this->repository->paginateForAdmin(
            $dto->toArray(),
            $dto->perPage
        );

        return [
            'sessionLogs' => $paginator,
            'columns' => $this->adminColumns(),
            'rows' => $this->adminRows($paginator),
            'statuses' => SessionLogStatus::cases(),
            'filters' => $dto->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTherapistIndex(User $therapist, SessionLogIndexDTO $dto): array
    {
        $filters = $dto->toArray();
        $filters['therapist_id'] = $therapist->id;

        $paginator = $this->repository->paginateForTherapist(
            $therapist,
            $filters,
            $dto->perPage
        );

        return [
            'sessionLogs' => $paginator,
            'columns' => $this->therapistColumns(),
            'rows' => $this->therapistRows($paginator),
            'statuses' => SessionLogStatus::cases(),
            'filters' => $dto->toArray(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function adminColumns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'student', 'label' => 'Student'],
            ['key' => 'school', 'label' => 'School'],
            ['key' => 'service', 'label' => 'Service'],
            ['key' => 'therapist', 'label' => 'Therapist'],
            ['key' => 'duration', 'label' => 'Duration'],
            ['key' => 'school_amount', 'label' => 'School Amount'],
            ['key' => 'therapist_amount', 'label' => 'Therapist Amount'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'actions', 'label' => 'Actions'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function therapistColumns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'student', 'label' => 'Student'],
            ['key' => 'school', 'label' => 'School'],
            ['key' => 'service', 'label' => 'Service'],
            ['key' => 'therapist', 'label' => 'Therapist'],
            ['key' => 'duration', 'label' => 'Duration'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'therapist_amount', 'label' => 'Therapist Amount'],
            ['key' => 'actions', 'label' => 'Actions'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function adminRows(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()
            ->map(fn (SessionLog $log): array => [
                'date' => $log->session_date?->format('Y-m-d') ?? '-',
                'student' => $log->student?->name ?? '-',
                'school' => $log->school?->display_name ?? '-',
                'service' => $log->service?->name ?? '-',
                'therapist' => $log->therapist?->name ?? '-',
                'duration' => $log->duration_minutes ? "{$log->duration_minutes} mins" : '-',
                'school_amount' => $this->formatCurrency($log->school_invoice_amount),
                'therapist_amount' => $this->formatCurrency($log->therapist_billable_amount),
                'status' => $log->status?->label() ?? '-',
                'actions' => $this->adminActions($log),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function therapistRows(LengthAwarePaginator $paginator): array
    {
        return $paginator->getCollection()
            ->map(fn (SessionLog $log): array => [
                'date' => $log->session_date?->format('Y-m-d') ?? '-',
                'student' => $log->student?->name ?? '-',
                'school' => $log->school?->display_name ?? '-',
                'service' => $log->service?->name ?? '-',
                'therapist' => $log->therapist?->name ?? '-',
                'duration' => $log->duration_minutes ? "{$log->duration_minutes} mins" : '-',
                'status' => $log->status?->label() ?? '-',
                'therapist_amount' => $this->formatCurrency($log->therapist_billable_amount),
                'actions' => $this->therapistActions($log),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function adminActions(SessionLog $log): array
    {
        $actions = [
            [
                'type' => 'link',
                'label' => 'View',
                'method' => 'get',
                'url' => route('admin.session-logs.show', $log),
            ],
        ];

        if ($log->status === SessionLogStatus::SUBMITTED) {
            $actions[] = [
                'type' => 'form',
                'label' => 'Approve',
                'method' => 'post',
                'url' => route('admin.session-logs.finalize', $log),
                'confirm' => [
                    'title' => 'Approve session?',
                    'text' => 'This will mark the session as approved.',
                    'icon' => 'question',
                ],
            ];

            $actions[] = [
                'type' => 'form',
                'label' => 'Cancel',
                'method' => 'post',
                'url' => route('admin.session-logs.cancel', $log),
                'confirm' => [
                    'title' => 'Cancel session?',
                    'text' => 'This will cancel the submitted session.',
                    'icon' => 'warning',
                ],
            ];
        }

        return $actions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function therapistActions(SessionLog $log): array
    {
        $actions = [
            [
                'type' => 'link',
                'label' => 'View',
                'method' => 'get',
                'url' => route('therapist.session-logs.show', $log),
            ],
        ];

        if ($log->status?->canEdit()) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Edit',
                'method' => 'get',
                'url' => route('therapist.session-logs.edit', $log),
            ];

            $actions[] = [
                'type' => 'form',
                'label' => 'Submit',
                'method' => 'post',
                'url' => route('therapist.session-logs.submit', $log),
                'confirm' => [
                    'title' => 'Submit session?',
                    'text' => 'Submit this session for approval.',
                    'icon' => 'question',
                ],
            ];
        }

        if ($log->status?->canCancel()) {
            $actions[] = [
                'type' => 'form',
                'label' => 'Cancel',
                'method' => 'post',
                'url' => route('therapist.session-logs.cancel', $log),
                'confirm' => [
                    'title' => 'Cancel session?',
                    'text' => 'This will cancel the session.',
                    'icon' => 'warning',
                ],
            ];
        }

        return $actions;
    }

    private function formatCurrency(float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '-';
        }

        if (! is_numeric($amount)) {
            return '-';
        }

        $value = (float) $amount;

        return '$'.number_format($value, 2);
    }
}
