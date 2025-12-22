<?php

declare(strict_types=1);

namespace App\Domain\Notification\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

interface NotificationRepositoryInterface
{
    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator;

    public function getUnread(User $user, int $limit = 10): Collection;

    public function getUnreadCount(User $user): int;

    public function markAsRead(User $user, string $notificationId): DatabaseNotification;

    public function markAllAsRead(User $user): void;

    public function delete(User $user, string $notificationId): void;
}
