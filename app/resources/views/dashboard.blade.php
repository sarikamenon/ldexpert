<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 space-y-8">
            <!-- Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="{{ route('therapist.students.index') }}" class="block transition-transform hover:scale-105">
                    <x-dashboard::metric :title="'Active Students'" :value="$activeStudents ?? 0">
                        <x-slot name="badge">+{{ $newStudentsThisMonth ?? 0 }} this month</x-slot>
                    </x-dashboard::metric>
                </a>
                <x-dashboard::metric :title="'This Week\'s Lessons'" :value="'18'">
                    <x-slot name="badge"><span class="text-xs text-foreground/60">3 today</span></x-slot>
                </x-dashboard::metric>
                <x-dashboard::metric :title="'Outstanding'" :value="'$2,450'">
                    <x-slot name="badge"><x-ui::badge variant="danger">5 overdue</x-ui::badge></x-slot>
                </x-dashboard::metric>
                <x-dashboard::metric :title="'This Month'" :value="'$8,920'">
                    <x-slot name="badge">+12% vs last month</x-slot>
                </x-dashboard::metric>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Schedule -->
                <x-dashboard::schedule class="lg:col-span-2" title="Today's Schedule">
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
                </x-dashboard::schedule>

                <!-- Menu Bar removed from side column -->

                <!-- Quick Actions -->
                <x-dashboard::quick-actions>
                    <x-ui::button class="w-full">+ Schedule New Lesson</x-ui::button>
                    <a href="{{ route('therapist.students.create') }}"
                        class="inline-flex items-center justify-center w-full px-4 py-2 border border-border rounded-lg hover:bg-gray-50">Add
                        Student</a>
                    <x-ui::button variant="secondary" class="w-full">Create Invoice</x-ui::button>
                    <x-slot name="footer">
                        <div class="text-sm font-medium text-foreground">Recent Activity</div>
                        <div class="text-sm text-foreground/70">Payment received <b>$150</b> from Sarah Kim</div>
                        <div class="text-xs text-foreground/60">2 hours ago</div>
                        <div class="text-sm text-foreground/70">New student added <b>Alex Thompson</b></div>
                    </x-slot>
                </x-dashboard::quick-actions>
            </div>
        </div>
    </div>
</x-app-layout>
