<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Notification\Services\NotificationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Notification\DeleteNotificationRequest;
use App\Http\Requests\Admin\Notification\IndexNotificationRequest;
use App\Http\Requests\Admin\Notification\MarkNotificationAsReadRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function index(IndexNotificationRequest $request): View
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $perPage = $request->integer('per_page', 20);
        $notifications = $this->notificationService->paginate($user, $perPage);

        return view('admin.notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $notifications = $this->notificationService->getUnread($user, 10);
        $unreadCount = $this->notificationService->getUnreadCount($user);

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(MarkNotificationAsReadRequest $request, string $id): RedirectResponse|JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->notificationService->markAsRead($user, $id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->notificationService->markAllAsRead($user);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'All notifications marked as read.');
    }

    public function destroy(DeleteNotificationRequest $request, string $id): RedirectResponse|JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $this->notificationService->delete($user, $id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Notification deleted.');
    }
}
