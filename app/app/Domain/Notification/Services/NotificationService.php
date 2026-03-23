<?php

declare(strict_types=1);

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $repository,
    ) {}

    /** @return LengthAwarePaginator<int, DatabaseNotification> */
    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate($user, $perPage);
    }

    /** @return Collection<int, DatabaseNotification> */
    public function getUnread(User $user, int $limit = 10): Collection
    {
        return $this->repository->getUnread($user, $limit);
    }

    public function getUnreadCount(User $user): int
    {
        return $this->repository->getUnreadCount($user);
    }

    public function markAsRead(User $user, string $notificationId): DatabaseNotification
    {
        return $this->repository->markAsRead($user, $notificationId);
    }

    public function markAllAsRead(User $user): void
    {
        $this->repository->markAllAsRead($user);
    }

    public function delete(User $user, string $notificationId): void
    {
        $this->repository->delete($user, $notificationId);
    }
}
