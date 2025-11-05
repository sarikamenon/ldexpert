<?php

declare(strict_types=1);

namespace App\Domain\Therapist\Services;

use App\Domain\User\Repositories\UserRepositoryInterface;

class DashboardService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function getDashboardMetrics(): array
    {
        return [
            'activeStudents' => $this->users->countStudentsByStatus('active'),
            'newStudentsThisMonth' => $this->users->countNewStudentsThisMonth(),
        ];
    }
}
