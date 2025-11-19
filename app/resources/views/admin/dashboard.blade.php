<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    </x-slot>

    {{-- 
        NOVA Admin Dashboard
        
        LIVE DATA:
        - Schools Overview: Total, Active, Inactive, New This Month (clickable - links to schools index)
        - Therapist Capacity: Total, Active, Available for Assignment, New This Month (clickable - links to therapists index)
        - Recent Activity: Latest schools and therapists added
        
        DUMMY DATA (will be replaced when modules are implemented):
        - Student Population metrics
        - Service Delivery (SSAs) metrics
        - Upcoming Events & Deadlines
        
        HIDDEN/DISABLED:
        - Needs Attention section (will be implemented later)
    --}}

    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-foreground">
            Welcome back, {{ auth()->user()->name }}!
        </h2>
        <p class="text-foreground/60 mt-1">Here's your NOVA command center overview</p>
    </div>

    {{-- Section 1: Key Performance Indicators --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- Schools Overview --}}
        <a href="{{ route('admin.schools.index') }}" class="block hover:scale-[1.02] transition-transform">
            <x-ui::card class="p-6 h-full hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-foreground/70">Schools Overview</p>
                </div>
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-3xl font-bold text-foreground mt-2">{{ $metrics['schools']['total'] }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                </div>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-foreground/70">Active:</span>
                        <span class="font-medium text-success">{{ $metrics['schools']['active'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-foreground/70">Inactive:</span>
                        <span class="font-medium text-danger">{{ $metrics['schools']['inactive'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-foreground/70">New This Month:</span>
                        <span class="font-medium text-primary">+{{ $metrics['schools']['new_this_month'] }}</span>
                    </div>
                </div>
            </x-ui::card>
        </a>

        {{-- Therapist Capacity --}}
        <a href="{{ route('admin.therapists.index') }}" class="block hover:scale-[1.02] transition-transform">
            <x-ui::card class="p-6 h-full hover:shadow-lg transition-shadow">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm text-foreground/70">Therapist Capacity</p>
                </div>
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-3xl font-bold text-foreground mt-2">{{ $metrics['therapists']['total'] }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-success/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 3v4a1 1 0 001 1h4" />
                        </svg>
                    </div>
                </div>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-foreground/70">Active:</span>
                        <span class="font-medium text-success">{{ $metrics['therapists']['active'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-foreground/70">Inactive:</span>
                        <span class="font-medium text-danger">{{ $metrics['therapists']['inactive'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-foreground/70">New This Month:</span>
                        <span class="font-medium text-primary">+{{ $metrics['therapists']['new_this_month'] }}</span>
                    </div>
                </div>
            </x-ui::card>
        </a>

        {{-- Student Population --}}
        <x-ui::card class="p-6 h-full">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-foreground/70">Student Population</p>
            </div>
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="text-3xl font-bold text-foreground mt-2">{{ $metrics['students']['total'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
            </div>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-foreground/70">Active:</span>
                    <span class="font-medium text-success">{{ $metrics['students']['active'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-foreground/70">Needing SSA:</span>
                    <span class="font-medium text-warning">{{ $metrics['students']['needing_ssa'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-foreground/70">New This Month:</span>
                    <span class="font-medium text-primary">+{{ $metrics['students']['new_this_month'] }}</span>
                </div>
            </div>
        </x-ui::card>

        {{-- Service Delivery Status --}}
        <x-ui::card class="p-6 h-full">
            <div class="flex items-center justify-between mb-2">
                <p class="text-sm text-foreground/70">Service Delivery</p>
            </div>
            <div class="flex items-start justify-between mb-4">
                <div>
                    <p class="text-3xl font-bold text-foreground mt-2">{{ $metrics['ssas']['active'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-success/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between">
                    <span class="text-foreground/70">Active SSAs:</span>
                    <span class="font-medium text-success">{{ $metrics['ssas']['active'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-foreground/70">Pending:</span>
                    <span class="font-medium text-warning">{{ $metrics['ssas']['pending'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-foreground/70">Utilization:</span>
                    <span class="font-medium text-primary">{{ $metrics['ssas']['avg_utilization'] }}%</span>
                </div>
            </div>
        </x-ui::card>
    </div>

    {{-- Section 2: Critical Alerts (Hidden for now - will be implemented later) --}}
    @if (false && count($alerts) > 0)
        <x-ui::card class="p-6 mb-6 border-l-4 border-warning">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-1">
                    <svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-lg font-semibold text-foreground mb-4">Needs Attention</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach ($alerts as $alert)
                            <a href="{{ $alert['link'] }}"
                                class="flex items-center gap-3 p-4 rounded-lg bg-{{ $alert['type'] }}/5 border border-{{ $alert['type'] }}/20 hover:border-{{ $alert['type'] }} hover:bg-{{ $alert['type'] }}/10 transition-colors">
                                <div
                                    class="flex-shrink-0 w-8 h-8 rounded-full bg-{{ $alert['type'] }}/10 flex items-center justify-center">
                                    <span class="w-2 h-2 rounded-full bg-{{ $alert['type'] }}"></span>
                                </div>
                                <span class="text-sm font-medium text-foreground">{{ $alert['message'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </x-ui::card>
    @endif

    {{-- Section 3: Visual Analytics --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- SSA Status Distribution --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">SSA Status Distribution</h3>
            <div style="position: relative; height: 250px;">
                <canvas id="ssaDistributionChart"></canvas>
            </div>
        </x-ui::card>

        {{-- Therapist by Position --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Therapists by Position</h3>
            <div style="position: relative; height: 250px;">
                <canvas id="therapistPositionChart"></canvas>
            </div>
        </x-ui::card>

        {{-- Service Utilization Trend --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">30-Day Utilization</h3>
            <div style="position: relative; height: 250px;">
                <canvas id="utilizationTrendChart"></canvas>
            </div>
        </x-ui::card>
    </div>

    {{-- Section 4: Activity Feed & Upcoming Events --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Recent Activity --}}
        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-foreground">Recent Activity</h3>
            </div>
            @if (count($recentActivity) > 0)
                <div class="space-y-3">
                    @foreach ($recentActivity as $activity)
                        <div class="flex items-start space-x-3 pb-3 border-b border-border last:border-b-0 last:pb-0">
                            <div class="flex-shrink-0">
                                <div
                                    class="w-8 h-8 rounded-full bg-{{ $activity['color'] }}/10 flex items-center justify-center">
                                    @if ($activity['icon'] === 'school')
                                        <svg class="w-4 h-4 text-{{ $activity['color'] }}" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    @elseif($activity['icon'] === 'user')
                                        <svg class="w-4 h-4 text-{{ $activity['color'] }}" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    @endif
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-foreground">{{ $activity['description'] }}</p>
                                <p class="text-xs text-foreground/60 mt-1">
                                    By {{ $activity['user'] }} • {{ $activity['created_at']->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-foreground/20 mx-auto mb-3" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-foreground/60">No recent activity yet</p>
                    <p class="text-xs text-foreground/40 mt-1">Activity will appear here as you add schools and
                        therapists</p>
                </div>
            @endif
        </x-ui::card>

        {{-- Upcoming Events --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Upcoming Events & Deadlines</h3>
            <div class="space-y-3">
                @foreach ($upcomingEvents as $event)
                    <div class="flex items-start space-x-3 pb-3 border-b border-border last:border-b-0 last:pb-0">
                        <div class="flex-shrink-0">
                            <div
                                class="w-8 h-8 rounded-full bg-{{ $event['priority'] === 'high' ? 'danger' : ($event['priority'] === 'medium' ? 'warning' : 'primary') }}/10 flex items-center justify-center">
                                <span
                                    class="w-2 h-2 rounded-full bg-{{ $event['priority'] === 'high' ? 'danger' : ($event['priority'] === 'medium' ? 'warning' : 'primary') }}"></span>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-foreground">{{ $event['title'] }}</p>
                            <p class="text-xs text-foreground/70 mt-1">{{ $event['entity'] }}</p>
                            <p class="text-xs text-foreground/60 mt-1">
                                Due: {{ $event['due_date']->format('M d, Y') }}
                                ({{ $event['due_date']->diffForHumans() }})
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui::card>
    </div>

    {{-- Section 5: Quick Actions --}}
    <x-ui::card class="p-6 mb-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Quick Actions</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach ($quickActions as $action)
                <a href="{{ $action['route'] === '#' ? 'javascript:void(0)' : route($action['route']) }}"
                    class="flex flex-col items-center p-4 rounded-lg border border-border hover:border-{{ $action['color'] }} hover:bg-{{ $action['color'] }}/5 transition-colors {{ $action['route'] === '#' ? 'opacity-50 cursor-not-allowed' : '' }}">
                    <div
                        class="w-12 h-12 rounded-lg bg-{{ $action['color'] }}/10 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-{{ $action['color'] }}" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            @if ($action['icon'] === 'document-add')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            @elseif($action['icon'] === 'school')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            @elseif($action['icon'] === 'user-add')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                            @elseif($action['icon'] === 'user')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            @elseif($action['icon'] === 'chart')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            @elseif($action['icon'] === 'list')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            @endif
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-center text-foreground">{{ $action['title'] }}</p>
                    <p class="text-xs text-center text-foreground/60 mt-1">{{ $action['description'] }}</p>
                </a>
            @endforeach
        </div>
    </x-ui::card>

    {{-- Section 6: Operational Metrics --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Key Operational Metrics</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach ($operationalMetrics as $metric)
                <div class="text-center">
                    <p class="text-sm text-foreground/70 mb-2">{{ $metric['label'] }}</p>
                    <p class="text-2xl font-bold text-foreground">{{ $metric['value'] }}</p>
                    @if ($metric['trend'] !== '0')
                        <p
                            class="text-xs mt-1 {{ $metric['trend_direction'] === 'up' ? 'text-success' : ($metric['trend_direction'] === 'down' ? 'text-danger' : 'text-foreground/60') }}">
                            {{ $metric['trend_direction'] === 'up' ? '↑' : ($metric['trend_direction'] === 'down' ? '↓' : '→') }}
                            {{ $metric['trend'] }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // SSA Distribution Chart
                const ssaData = @json($charts['ssa_distribution']);
                new Chart(document.getElementById('ssaDistributionChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ssaData.labels,
                        datasets: [{
                            data: ssaData.data,
                            backgroundColor: ssaData.colors,
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 10,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        }
                    }
                });

                // Therapist Position Chart
                const therapistData = @json($charts['therapist_by_position']);
                new Chart(document.getElementById('therapistPositionChart'), {
                    type: 'bar',
                    data: {
                        labels: therapistData.labels,
                        datasets: [{
                            label: 'Therapists',
                            data: therapistData.data,
                            backgroundColor: therapistData.colors,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });

                // Utilization Trend Chart
                const utilizationData = @json($charts['utilization_trend']);
                new Chart(document.getElementById('utilizationTrendChart'), {
                    type: 'line',
                    data: {
                        labels: utilizationData.labels.filter((_, i) => i % 5 === 0), // Show every 5th label
                        datasets: [{
                                label: 'THO Minutes',
                                data: utilizationData.tho_minutes.filter((_, i) => i % 5 === 0),
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                fill: false,
                            },
                            {
                                label: 'Served Minutes',
                                data: utilizationData.served_minutes.filter((_, i) => i % 5 === 0),
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                fill: false,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 10,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        }
                    }
                });
            });
        </script>
    </x-slot>
</x-admin.layouts.app>
