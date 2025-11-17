<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    </x-slot>

    <div class="flex items-center justify-between mb-6">
        <x-page-title title="Schools Analytics" />
        <a href="{{ route('admin.analytics.index') }}" class="text-sm text-primary hover:underline">← Back to Overview</a>
    </div>

    <!-- Date Range Filter -->
    <x-ui::card class="p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-sm font-medium text-foreground/70 mb-1">Date Range</label>
                <select name="date_range" id="dateRangeSelect"
                    class="border border-border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                    <option value="last_7_days" @selected($dateRange === 'last_7_days')>Last 7 Days</option>
                    <option value="last_30_days" @selected($dateRange === 'last_30_days')>Last 30 Days</option>
                    <option value="last_90_days" @selected($dateRange === 'last_90_days')>Last 90 Days</option>
                    <option value="this_month" @selected($dateRange === 'this_month')>This Month</option>
                    <option value="last_month" @selected($dateRange === 'last_month')>Last Month</option>
                    <option value="this_year" @selected($dateRange === 'this_year')>This Year</option>
                    <option value="custom" @selected($dateRange === 'custom')>Custom</option>
                </select>
            </div>

            <div id="customDateRange" class="{{ $dateRange === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-foreground/70 mb-1">Start Date</label>
                <x-text-input type="date" name="start_date" value="{{ $startDate }}" />
            </div>

            <div id="customDateRangeEnd" class="{{ $dateRange === 'custom' ? '' : 'hidden' }}">
                <label class="block text-sm font-medium text-foreground/70 mb-1">End Date</label>
                <x-text-input type="date" name="end_date" value="{{ $endDate }}" />
            </div>

            <button type="submit"
                class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                Apply Filter
            </button>
        </form>
    </x-ui::card>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <x-admin.widgets.stat-card 
            title="Total Schools" 
            :value="$analytics['total']" 
            icon="school"
            color="primary" />
        
        <x-admin.widgets.stat-card 
            title="Active Schools" 
            :value="$analytics['active']" 
            icon="check"
            color="success" />
        
        <x-admin.widgets.stat-card 
            title="Inactive Schools" 
            :value="$analytics['inactive']" 
            icon="user"
            color="danger" />
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Schools by State -->
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Schools by State</h3>
            <div style="position: relative; height: 300px;">
                <canvas id="schoolsByStateChart"></canvas>
            </div>
        </x-ui::card>

        <!-- Schools by Type -->
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Schools by Type</h3>
            <div style="position: relative; height: 300px;">
                <canvas id="schoolsByTypeChart"></canvas>
            </div>
        </x-ui::card>

        <!-- Growth Trend -->
        <x-ui::card class="p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-foreground mb-4">Growth Trend</h3>
            <div style="position: relative; height: 300px;">
                <canvas id="growthTrendChart"></canvas>
            </div>
        </x-ui::card>

        <!-- Schools by Manager -->
        <x-ui::card class="p-6 lg:col-span-2">
            <h3 class="text-lg font-semibold text-foreground mb-4">Top 10 Managers by School Count</h3>
            <div style="position: relative; height: 300px;">
                <canvas id="schoolsByManagerChart"></canvas>
            </div>
        </x-ui::card>
    </div>

    <!-- Recent Additions -->
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold text-foreground mb-4">Recent School Additions</h3>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-border">
                    <tr>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">ID</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Name</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Manager</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-foreground/70">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analytics['recent_additions'] as $school)
                        <tr class="border-b border-border last:border-b-0">
                            <td class="py-3 px-4">{{ $school['id'] }}</td>
                            <td class="py-3 px-4">{{ $school['name'] }}</td>
                            <td class="py-3 px-4">{{ $school['manager'] ?? 'N/A' }}</td>
                            <td class="py-3 px-4">{{ $school['created_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', function() {
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

                const byStateData = @json($analytics['by_state']);
                new Chart(document.getElementById('schoolsByStateChart'), {
                    type: 'bar',
                    data: {
                        labels: byStateData.labels,
                        datasets: [{
                            label: 'Schools',
                            data: byStateData.data,
                            backgroundColor: '#3b82f6',
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });

                const byTypeData = @json($analytics['by_type']);
                new Chart(document.getElementById('schoolsByTypeChart'), {
                    type: 'doughnut',
                    data: {
                        labels: byTypeData.labels,
                        datasets: [{
                            data: byTypeData.data,
                            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });

                const growthData = @json($analytics['growth_trend']);
                new Chart(document.getElementById('growthTrendChart'), {
                    type: 'line',
                    data: {
                        labels: growthData.labels,
                        datasets: [{
                            label: 'New Schools',
                            data: growthData.data,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true } }
                    }
                });

                const byManagerData = @json($analytics['by_manager']);
                new Chart(document.getElementById('schoolsByManagerChart'), {
                    type: 'bar',
                    data: {
                        labels: byManagerData.labels,
                        datasets: [{
                            label: 'Schools',
                            data: byManagerData.data,
                            backgroundColor: '#10b981',
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true } }
                    }
                });
            });
        </script>
    </x-slot>
</x-admin.layouts.app>

