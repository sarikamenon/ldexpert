<x-admin.layouts.app>
    <x-page-title title="Notifications" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    <x-ui::card class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-foreground">All Notifications</h3>
            @if($notifications->total() > 0)
                <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                    @csrf
                    <button type="submit" class="text-sm text-primary hover:underline">
                        Mark All as Read
                    </button>
                </form>
            @endif
        </div>

        @if ($notifications->count() > 0)
            <div class="space-y-2">
                @foreach ($notifications as $notification)
                    <div class="flex items-start gap-3 p-4 rounded-lg border border-border {{ $notification->read_at ? 'bg-background' : 'bg-primary/5' }}">
                        <div class="flex-shrink-0">
                            @if (!$notification->read_at)
                                <div class="w-2 h-2 rounded-full bg-primary mt-2"></div>
                            @else
                                <div class="w-2 h-2 rounded-full bg-transparent mt-2"></div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-foreground">{{ $notification->data['message'] ?? 'Notification' }}</p>
                            <p class="text-xs text-foreground/60 mt-1">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            @if (!$notification->read_at)
                                <form method="POST" action="{{ route('admin.notifications.mark-read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="text-xs text-primary hover:underline">
                                        Mark Read
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.notifications.destroy', $notification->id) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-danger hover:underline">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @else
            <div class="text-center py-10">
                <p class="text-foreground/70">No notifications yet.</p>
            </div>
        @endif
    </x-ui::card>
</x-admin.layouts.app>

