<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Domain\Notification\Services\NotificationService;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Mockery;
use Tests\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function test_paginate_delegates_to_repository(): void
    {
        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $user = User::factory()->create();
        $paginator = Mockery::mock(LengthAwarePaginator::class);

        $repository->shouldReceive('paginate')
            ->once()
            ->with($user, 20)
            ->andReturn($paginator);

        $service = new NotificationService($repository);
        $result = $service->paginate($user, 20);

        $this->assertSame($paginator, $result);
    }

    public function test_get_unread_delegates_to_repository(): void
    {
        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $user = User::factory()->create();
        $notifications = collect([]);

        $repository->shouldReceive('getUnread')
            ->once()
            ->with($user, 10)
            ->andReturn($notifications);

        $service = new NotificationService($repository);
        $result = $service->getUnread($user, 10);

        $this->assertSame($notifications, $result);
    }

    public function test_get_unread_count_delegates_to_repository(): void
    {
        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $user = User::factory()->create();

        $repository->shouldReceive('getUnreadCount')
            ->once()
            ->with($user)
            ->andReturn(5);

        $service = new NotificationService($repository);
        $result = $service->getUnreadCount($user);

        $this->assertSame(5, $result);
    }

    public function test_mark_as_read_delegates_to_repository(): void
    {
        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $user = User::factory()->create();
        $notification = Mockery::mock(DatabaseNotification::class);

        $repository->shouldReceive('markAsRead')
            ->once()
            ->with($user, 'notification-id')
            ->andReturn($notification);

        $service = new NotificationService($repository);
        $result = $service->markAsRead($user, 'notification-id');

        $this->assertSame($notification, $result);
    }

    public function test_mark_all_as_read_delegates_to_repository(): void
    {
        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $user = User::factory()->create();

        $repository->shouldReceive('markAllAsRead')
            ->once()
            ->with($user);

        $service = new NotificationService($repository);
        $service->markAllAsRead($user);

        $this->assertTrue(true);
    }

    public function test_delete_delegates_to_repository(): void
    {
        $repository = Mockery::mock(NotificationRepositoryInterface::class);
        $user = User::factory()->create();

        $repository->shouldReceive('delete')
            ->once()
            ->with($user, 'notification-id');

        $service = new NotificationService($repository);
        $service->delete($user, 'notification-id');

        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
