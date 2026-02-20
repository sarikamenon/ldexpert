<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (in_array($activeTab ?? 'dashboard', ['students', 'ssas', 'contracts', 'session_logs']))
            @vite(['resources/css/common/datatables.css'])
        @endif
    </x-slot>

    {{-- Header Card --}}
    <x-ui::show-header :title="$therapist->name" :subtitle="'Therapist ID #' . $therapist->id"
        :back-url="route('admin.therapists.index')" back-label="Back to list"
        :edit-url="route('admin.therapists.edit', $therapist)" edit-label="Edit Therapist">
        <x-slot name="badge">
            <x-ui::badge :variant="$therapist->status?->value === 'active' ? 'success' : 'danger'">
                {{ ucfirst($therapist->status?->value ?? 'inactive') }}
            </x-ui::badge>
        </x-slot>
        <x-slot name="actions">
            <x-ui::status-toggle :status="$therapist->status?->value" data-therapist-id="{{ $therapist->id }}" />
        </x-slot>
    </x-ui::show-header>

    {{-- Tabs Navigation --}}
    @php
        $tabs = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'dashboard'])],
            ['key' => 'overview', 'label' => 'Overview', 'href' => route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'overview'])],
            ['key' => 'contracts', 'label' => 'Contracts', 'href' => route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'contracts'])],
            ['key' => 'ssas', 'label' => 'SSAs', 'href' => route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'ssas'])],
            ['key' => 'students', 'label' => 'Students', 'href' => route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'students'])],
            ['key' => 'session_logs', 'label' => 'Session Logs', 'href' => route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'session_logs'])],
        ];
    @endphp
    <x-ui::tabs :tabs="$tabs" :active-tab="$activeTab ?? 'dashboard'" />

    {{-- Tab Content --}}
    @if (($activeTab ?? 'dashboard') === 'dashboard')
        @php
            $metricItems = [
                ['label' => 'Active SSAs', 'value' => $metrics['active_ssas'] ?? 0, 'valueClass' => 'text-success'],
                ['label' => 'Completed SSAs', 'value' => $metrics['completed_ssas'] ?? 0, 'valueClass' => 'text-primary'],
                ['label' => 'Pending SSAs', 'value' => $metrics['pending_ssas'] ?? 0, 'valueClass' => 'text-warning'],
                ['label' => 'Active Students', 'value' => $metrics['total_students'] ?? 0],
            ];
        @endphp
        <x-ui::metric-grid :items="$metricItems" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-ui::card class="p-6 lg:col-span-1">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-foreground">Service Delivery</h3>
                    <span class="text-sm text-foreground/70">{{ $chartData['progress'] ?? 0 }}%</span>
                </div>
                <div class="relative" style="height: 260px;">
                    <canvas id="therapistProgressChart" data-served="{{ $chartData['served'] ?? 0 }}"
                        data-tho="{{ ($chartData['served'] ?? 0) + ($chartData['remaining'] ?? 0) }}"></canvas>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-foreground/70">Served Minutes</span>
                        <span class="font-semibold">{{ number_format($chartData['served'] ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-foreground/70">Remaining Minutes</span>
                        <span class="font-semibold">{{ number_format($chartData['remaining'] ?? 0) }}</span>
                    </div>
                </div>
            </x-ui::card>

            <x-ui::card class="p-6 space-y-4 lg:col-span-2">
                <h3 class="text-lg font-semibold text-foreground">Contact & Role</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="space-y-1">
                        <p class="text-foreground/70">Title</p>
                        <p class="font-semibold">{{ $therapist->therapistProfile?->title?->label() ?? '—' }}</p>
                        <p class="text-foreground/70">Position</p>
                        <p>{{ $therapist->therapistProfile?->position?->label() ?? '—' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-foreground/70">Manager</p>
                        <p>{{ $therapist->therapistProfile?->manager?->name ?? '—' }}</p>
                        <p class="text-foreground/70">Timezone</p>
                        <p>{{ $therapist->therapistProfile?->timezone ? \App\Constants\UsTimezones::getTimezoneLabel($therapist->therapistProfile->timezone) : '—' }}
                        </p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-foreground/70">Email</p>
                        <p class="text-primary">{{ $therapist->email }}</p>
                        <p class="text-foreground/70">LD Email</p>
                        <p>{{ $therapist->therapistProfile?->ld_email ?? '—' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-foreground/70">Phone</p>
                        <p>{{ $therapist->therapistProfile?->phone ?? '—' }}</p>
                        <p class="text-foreground/70">Employee Type</p>
                        <p>{{ $therapist->therapistProfile?->employee_type?->label() ?? '—' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p class="text-foreground/70">Hourly Rate</p>
                        <p>{{ $therapist->therapistProfile?->hourly_rate !== null ? '$' . number_format((float) $therapist->therapistProfile->hourly_rate, 2) : '—' }}</p>
                    </div>
                </div>
            </x-ui::card>
        </div>
    @elseif (($activeTab ?? 'dashboard') === 'overview')
        <x-therapist.overview-details :therapist="$therapist" context="admin" />
    @elseif (($activeTab ?? 'dashboard') === 'contracts' && isset($contracts))
        <x-admin.therapist-contracts-list :contracts="$contracts" :filters="$contractFilters ?? []" :statuses="$statuses ?? []" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'ssas' && isset($ssas))
        <x-admin.ssas-list :ssas="$ssas" :filters="$ssaFilters ?? []" :statuses="$statuses ?? []" :students="$students ?? []" :therapists="$therapists ?? []"
            :services="$services ?? []" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'students' && isset($students))
        <x-admin.students-list :students="$students" :filters="$studentFilters ?? []" :schools="$schools ?? []" :statuses="$statuses ?? []"
            context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'session_logs' && isset($sessionLogs))
        <x-admin.session-logs-list :sessionLogs="$sessionLogs" :columns="$sessionLogColumns ?? []" :rows="$sessionLogRows ?? []"
            :filters="$sessionLogFilters ?? []" :statuses="$sessionLogStatuses ?? []" context="detail" />
    @endif

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-therapists-show.js'])
        @if (($activeTab ?? 'dashboard') === 'students')
            @vite(['resources/js/pages/admin-students-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'contracts')
            @vite(['resources/js/pages/admin-contracts-therapists-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'ssas')
            @vite(['resources/js/pages/admin-ssas-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'session_logs')
            @vite(['resources/js/pages/admin-session-logs-index.js'])
        @endif
    </x-slot>
</x-admin.layouts.app>
