<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories;

use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

final class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function paginate(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()->paginate($perPage);
    }

    public function getUnread(User $user, int $limit = 10): Collection
    {
        return $user->unreadNotifications()->take($limit)->get();
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(User $user, string $notificationId): DatabaseNotification
    {
        $notification = $user->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        return $notification;
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    public function delete(User $user, string $notificationId): void
    {
        $notification = $user->notifications()->findOrFail($notificationId);
        $notification->delete();
    }
}
