<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" type="button" class="relative p-2 text-foreground/70 hover:text-foreground focus:outline-none">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>
        @php
            $unreadCount = auth()->user()->unreadNotifications->count();
        @endphp
        @if ($unreadCount > 0)
            <span class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-danger rounded-full">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" 
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-border z-50"
         style="display: none;">
        
        <div class="p-3 border-b border-border">
            <div class="flex justify-between items-center">
                <h3 class="text-sm font-semibold text-foreground">Notifications</h3>
                <a href="{{ route('admin.notifications.index') }}" class="text-xs text-primary hover:underline">
                    View All
                </a>
            </div>
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse (auth()->user()->unreadNotifications->take(5) as $notification)
                <div class="p-3 border-b border-border hover:bg-background/subtle">
                    <p class="text-sm text-foreground">{{ $notification->data['message'] ?? 'Notification' }}</p>
                    <p class="text-xs text-foreground/60 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
            @empty
                <div class="p-6 text-center">
                    <p class="text-sm text-foreground/60">No unread notifications</p>
                </div>
            @endforelse
        </div>

        @if (auth()->user()->unreadNotifications->count() > 0)
            <div class="p-3 border-t border-border">
                <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                    @csrf
                    <button type="submit" class="w-full text-xs text-center text-primary hover:underline">
                        Mark All as Read
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>

