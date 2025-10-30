<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-foreground">Welcome back</h2>
            <div class="space-x-2">
                <x-ui::button variant="primary">+ Quick Actions</x-ui::button>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <x-ui::button type="submit" variant="secondary">Logout</x-ui::button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 space-y-8">
            <!-- Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-ui::card class="p-5">
                    <div class="text-sm text-foreground/70">Active Students</div>
                    <div class="mt-2 text-3xl font-semibold text-foreground">24</div>
                    <x-ui::badge variant="success" class="mt-3">+3 this month</x-ui::badge>
                </x-ui::card>
                <x-ui::card class="p-5">
                    <div class="text-sm text-foreground/70">This Week's Lessons</div>
                    <div class="mt-2 text-3xl font-semibold text-foreground">18</div>
                    <div class="mt-3 text-xs text-foreground/60">3 today</div>
                </x-ui::card>
                <x-ui::card class="p-5">
                    <div class="text-sm text-foreground/70">Outstanding</div>
                    <div class="mt-2 text-3xl font-semibold text-foreground">$2,450</div>
                    <x-ui::badge variant="danger" class="mt-3">5 overdue</x-ui::badge>
                </x-ui::card>
                <x-ui::card class="p-5">
                    <div class="text-sm text-foreground/70">This Month</div>
                    <div class="mt-2 text-3xl font-semibold text-foreground">$8,920</div>
                    <x-ui::badge class="mt-3">+12% vs last month</x-ui::badge>
                </x-ui::card>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Schedule -->
                <x-ui::card class="lg:col-span-2">
                    <div class="p-5 border-b border-border flex items-center justify-between">
                        <h3 class="text-lg font-medium text-foreground">Today's Schedule</h3>
                        <a href="#" class="text-sm text-accent hover:underline">View All</a>
                    </div>
                    <div class="p-5 space-y-4">
                        @foreach ([['title' => 'Algebra II - Emily Rodriguez', 'desc' => 'Quadratic equations review', 'time' => '2:00 PM', 'mode' => 'Online', 'dur' => '60 min'], ['title' => 'SAT Prep - James Chen', 'desc' => 'Math section practice test', 'time' => '4:30 PM', 'mode' => 'In-home', 'dur' => '90 min'], ['title' => 'Chemistry - Michael Park', 'desc' => 'Stoichiometry problems', 'time' => '6:00 PM', 'mode' => 'Online', 'dur' => '60 min']] as $item)
                            <div
                                class="rounded-lg border border-border p-4 flex items-center justify-between bg-background">
                                <div>
                                    <div class="font-medium text-foreground">{{ $item['title'] }}</div>
                                    <div class="text-sm text-foreground/70 mt-1">{{ $item['desc'] }}</div>
                                    <div class="mt-2 space-x-2">
                                        <x-ui::badge variant="muted">{{ $item['mode'] }}</x-ui::badge>
                                        <x-ui::badge variant="muted">{{ $item['dur'] }}</x-ui::badge>
                                    </div>
                                </div>
                                <div class="text-sm text-foreground/70">{{ $item['time'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </x-ui::card>

                <!-- Quick Actions -->
                <x-ui::card>
                    <div class="p-5 border-b border-border">
                        <h3 class="text-lg font-medium text-foreground">Quick Actions</h3>
                    </div>
                    <div class="p-5 space-y-3">
                        <x-ui::button class="w-full">+ Schedule New Lesson</x-ui::button>
                        <x-ui::button variant="secondary" class="w-full">Add Student</x-ui::button>
                        <x-ui::button variant="secondary" class="w-full">Create Invoice</x-ui::button>
                    </div>
                    <div class="p-5 border-t border-border space-y-3">
                        <div class="text-sm font-medium text-foreground">Recent Activity</div>
                        <div class="text-sm text-foreground/70">Payment received <b>$150</b> from Sarah Kim</div>
                        <div class="text-xs text-foreground/60">2 hours ago</div>
                        <div class="text-sm text-foreground/70">New student added <b>Alex Thompson</b></div>
                    </div>
                </x-ui::card>
            </div>
        </div>
    </div>
</x-app-layout>
