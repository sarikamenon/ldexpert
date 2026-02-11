<?php

declare(strict_types=1);

namespace App\Domain\SessionLog\Services;

use App\Domain\Therapist\Repositories\SessionLogRepositoryInterface;
use App\DTOs\SessionLogIndexDTO;
use App\Enums\SessionLogStatus;
use App\Models\SessionLog;
use App\Models\User;
use App\Support\DateHelper;
use Carbon\Carbon;
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

        if ($dto->dateFrom === null && $dto->dateTo === null) {
            $filters['date_from'] = now()->startOfMonth()->toDateString();
            $filters['date_to'] = now()->endOfMonth()->toDateString();
        }

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
            'filters' => $filters,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function adminColumns(): array
    {
        return [
            ['key' => 'date_time', 'label' => 'Date & Time / Duration'],
            ['key' => 'entry_info', 'label' => 'Entry'],
            ['key' => 'student_service_school', 'label' => 'Student, Service & School'],
            ['key' => 'therapist', 'label' => 'Therapist'],
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
            ['key' => 'date_time', 'label' => 'Date & Time'],
            ['key' => 'student_service_school', 'label' => 'Student, Service & School'],
            ['key' => 'entry_info', 'label' => 'Entry'],
            ['key' => 'therapist_amount', 'label' => 'Therapist Amount'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'actions', 'label' => 'Actions'],
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, SessionLog>  $paginator
     * @return array<int, array<string, mixed>>
     */
    private function adminRows(LengthAwarePaginator $paginator): array
    {
        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, SessionLog> $paginator */
        return $paginator->getCollection()
            ->map(function (SessionLog $log): array {
                /** @var \Carbon\Carbon|null $sessionDate */
                $sessionDate = $log->session_date;
                $createdAt = $log->created_at ? Carbon::parse($log->created_at) : null;

                return [
                    'date_time' => [
                        'date' => $sessionDate?->format('M d, Y') ?? null,
                        'time' => null,
                        'duration' => $log->duration_minutes ? "{$log->duration_minutes} mins" : null,
                    ],
                    'entry_info' => [
                        'created_date' => $createdAt?->format('M d, Y'),
                        'entry_difference' => DateHelper::daysDifferenceBetweenDates($sessionDate, $createdAt),
                    ],
                    'student_service_school' => [
                        'student' => $log->student?->name ?? null,
                        'service' => $log->service?->name ?? null,
                        'school' => $log->school?->display_name ?? null,
                    ],
                    'therapist' => $log->therapist?->name ?? '-',
                    'school_amount' => $this->formatCurrency($log->school_invoice_amount),
                    'therapist_amount' => $this->formatCurrency($log->therapist_billable_amount),
                    'status' => $this->getStatusLabel($log),
                    'actions' => $this->adminActions($log),
                ];
            })
            ->all();
    }

    /**
     * @param  LengthAwarePaginator<int, SessionLog>  $paginator
     * @return array<int, array<string, mixed>>
     */
    private function therapistRows(LengthAwarePaginator $paginator): array
    {
        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, SessionLog> $paginator */
        return $paginator->getCollection()
            ->map(function (SessionLog $log): array {
                /** @var \Carbon\Carbon|null $sessionDate */
                $sessionDate = $log->session_date;
                $createdAt = $log->created_at ? Carbon::parse($log->created_at) : null;

                $startTime = $log->start_time?->format('g:i A');
                $endTime = $log->end_time?->format('g:i A');
                $timeRange = $startTime && $endTime ? "{$startTime} - {$endTime}" : null;
                $duration = $log->duration_minutes ? "{$log->duration_minutes} mins" : null;

                return [
                    'date' => $sessionDate?->format('Y-m-d') ?? null,
                    'date_time' => [
                        'date' => $sessionDate?->format('M d, Y') ?? null,
                        'time' => $timeRange,
                        'duration' => $duration,
                    ],
                    'entry_info' => [
                        'created_date' => $createdAt?->format('M d, Y'),
                        'entry_difference' => DateHelper::daysDifferenceBetweenDates($sessionDate, $createdAt),
                    ],
                    'student_service_school' => [
                        'student' => $log->student?->name ?? null,
                        'service' => $log->service?->name ?? null,
                        'school' => $log->school?->display_name ?? null,
                    ],
                    'therapist_amount' => $this->formatCurrency($log->therapist_billable_amount),
                    'status' => $this->getStatusLabel($log),
                    'actions' => $this->therapistActions($log),
                ];
            })
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
                'icon' => 'eye',
                'method' => 'get',
                'url' => route('admin.session-logs.show', $log),
                'variant' => 'secondary',
            ],
        ];

        if ($log->status === SessionLogStatus::SUBMITTED) {
            $actions[] = [
                'type' => 'form',
                'label' => 'Approve',
                'icon' => 'check',
                'method' => 'post',
                'url' => route('admin.session-logs.approve', $log),
                'variant' => 'primary',
                'confirm' => [
                    'title' => 'Approve session?',
                    'text' => 'This will mark the session as approved.',
                    'icon' => 'question',
                ],
            ];

            $actions[] = [
                'type' => 'form',
                'label' => 'Cancel',
                'icon' => 'x',
                'method' => 'post',
                'url' => route('admin.session-logs.cancel', $log),
                'variant' => 'primary-outline',
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
                'icon' => 'eye',
                'method' => 'get',
                'url' => route('therapist.session-logs.show', $log),
                'variant' => 'secondary',
            ],
        ];

        if ($log->status?->canEdit()) {
            $actions[] = [
                'type' => 'link',
                'label' => 'Edit',
                'method' => 'get',
                'icon' => 'edit',
                'url' => route('therapist.session-logs.edit', $log),
                'variant' => 'primary',
            ];

            $actions[] = [
                'type' => 'form',
                'label' => 'Submit',
                'method' => 'post',
                'url' => route('therapist.session-logs.submit', $log),
                'icon' => 'send',
                'variant' => 'primary-outline',
                'confirm' => [
                    'title' => 'Submit session?',
                    'text' => 'Submit this session for approval.',
                    'icon' => 'question',
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

    private function getStatusLabel(SessionLog $log): string
    {
        try {
            return $log->status?->label() ?? '-';
        } catch (\Throwable $e) {
            return '-';
        }
    }
}
