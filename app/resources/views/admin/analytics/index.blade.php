<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
            // NOVA Brand Colors for Charts
            const novaColors = {
                primary: '#5563b8',
                secondary: '#14b8a6',
                accent: '#a855f7',
                cyan: '#06b6d4',
                fuchsia: '#d946ef',
                palette: ['#5563b8', '#14b8a6', '#a855f7', '#06b6d4', '#d946ef', '#2dd4bf', '#c084fc', '#9333ea'],
                primaryBg: 'rgba(85, 99, 184, 0.1)',
                accentBg: 'rgba(168, 85, 247, 0.1)',
            };
        </script>
    </x-slot>

    <x-page-title title="Analytics Overview" />

    <!-- Date Range Filter -->
    <x-ui::card class="p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-sm font-medium text-foreground/70 mb-1">Date Range</label>
                <x-ui::select name="date_range" id="dateRangeSelect" searchable>
                    <option value="last_7_days" @selected($dateRange === 'last_7_days')>Last 7 Days</option>
                    <option value="last_30_days" @selected($dateRange === 'last_30_days')>Last 30 Days</option>
                    <option value="last_90_days" @selected($dateRange === 'last_90_days')>Last 90 Days</option>
                    <option value="this_month" @selected($dateRange === 'this_month')>This Month</option>
                    <option value="last_month" @selected($dateRange === 'last_month')>Last Month</option>
                    <option value="this_year" @selected($dateRange === 'this_year')>This Year</option>
                    <option value="custom" @selected($dateRange === 'custom')>Custom</option>
                </x-ui::select>
            </div>

            <div id="customDateRange" class="{{ $dateRange === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-foreground/70 mb-1">Start Date</label>
                <x-ui::input type="date" name="start_date" value="{{ $startDate }}" />
            </div>

            <div id="customDateRangeEnd" class="{{ $dateRange === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-foreground/70 mb-1">End Date</label>
                <x-ui::input type="date" name="end_date" value="{{ $endDate }}" />
            </div>

            <button type="submit"
                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                Apply Filter
            </button>
        </form>
    </x-ui::card>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-admin.widgets.stat-card 
            title="Total Schools" 
            :value="$analytics['schools']['total']" 
            icon="school"
            color="primary" />
        
        <x-admin.widgets.stat-card 
            title="Active Schools" 
            :value="$analytics['schools']['active']" 
            icon="check"
            color="success" />
        
        <x-admin.widgets.stat-card 
            title="Total Therapists" 
            :value="$analytics['therapists']['total']" 
            icon="users"
            color="primary" />
        
        <x-admin.widgets.stat-card 
            title="Active Therapists" 
            :value="$analytics['therapists']['active']" 
            icon="user"
            color="success" />
    </div>

    <!-- Period Summary -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-ui::card class="p-6">
            <h3 class="text-sm font-medium text-foreground/70 mb-2">New Schools This Period</h3>
            <p class="text-3xl font-bold text-primary">{{ $analytics['schools']['new_this_period'] }}</p>
        </x-ui::card>

        <x-ui::card class="p-6">
            <h3 class="text-sm font-medium text-foreground/70 mb-2">New Therapists This Period</h3>
            <p class="text-3xl font-bold text-primary">{{ $analytics['therapists']['new_this_period'] }}</p>
        </x-ui::card>

        <x-ui::card class="p-6">
            <h3 class="text-sm font-medium text-foreground/70 mb-2">Total Active Users</h3>
            <p class="text-3xl font-bold text-success">{{ $analytics['users']['active'] }}</p>
        </x-ui::card>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-1 gap-6 mb-6">
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Users by Role</h3>
            <div style="position: relative; height: 300px;">
                <canvas id="usersByRoleChart"></canvas>
            </div>
        </x-ui::card>
    </div>

    <!-- Quick Links -->
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Detailed Analytics</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="{{ route('admin.analytics.schools') }}" class="flex items-center p-4 border border-border rounded-lg hover:border-primary hover:bg-primary/5 transition-colors">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-foreground">Schools Analytics</p>
                    <p class="text-xs text-foreground/60 mt-1">View detailed school metrics and trends</p>
                </div>
            </a>

            <a href="{{ route('admin.analytics.therapists') }}" class="flex items-center p-4 border border-border rounded-lg hover:border-primary hover:bg-primary/5 transition-colors">
                <div class="flex-shrink-0 w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-foreground">Therapists Analytics</p>
                    <p class="text-xs text-foreground/60 mt-1">View detailed therapist metrics and trends</p>
                </div>
            </a>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Date range toggle
                const dateRangeSelect = document.getElementById('dateRangeSelect');
                const customDateRange = document.getElementById('customDateRange');
                const customDateRangeEnd = document.getElementById('customDateRangeEnd');

                if (dateRangeSelect) {
                    dateRangeSelect.addEventListener('change', function() {
                        if (this.value === 'custom') {
                            customDateRange?.classList.remove('hidden');
                            customDateRangeEnd?.classList.remove('hidden');
                        } else {
                            customDateRange?.classList.add('hidden');
                            customDateRangeEnd?.classList.add('hidden');
                        }
                    });
                }

                // Users by Role Chart
                const usersByRoleCtx = document.getElementById('usersByRoleChart');
                if (usersByRoleCtx) {
                    const usersByRoleData = @json($analytics['users']['by_role']);
                    new Chart(usersByRoleCtx, {
                        type: 'doughnut',
                        data: {
                            labels: usersByRoleData.labels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
                            datasets: [{
                                data: usersByRoleData.data,
                                backgroundColor: novaColors.palette.slice(0, 5),
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    });
                }
            });
        </script>
    </x-slot>
</x-admin.layouts.app>

