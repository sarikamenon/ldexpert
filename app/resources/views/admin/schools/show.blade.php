<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (in_array($activeTab ?? 'dashboard', ['students', 'therapists', 'ssas', 'contracts', 'account']))
            @vite(['resources/css/common/datatables.css'])
        @endif
        @if (($activeTab ?? 'dashboard') === 'calendar')
            @vite(['resources/css/therapist-schedule.css'])
        @endif
    </x-slot>

    {{-- Header Card --}}
    <x-ui::show-header :title="$school->display_name" :subtitle="'School/Family ID #' . $school->id"
        :back-url="route('admin.schools.index')" back-label="Back to list"
        :edit-url="route('admin.schools.edit', $school)" edit-label="Edit School/Family">
        <x-slot name="badge">
            <x-ui::badge :variant="$school->status?->value === 'active' ? 'success' : 'danger'">
                {{ ucfirst($school->status?->value ?? 'inactive') }}
            </x-ui::badge>
        </x-slot>
        <x-slot name="actions">
            <x-ui::status-toggle :status="$school->status?->value" data-school-id="{{ $school->id }}" />
        </x-slot>
    </x-ui::show-header>

    {{-- Tabs Navigation --}}
    @php
        $tabs = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('admin.schools.show', ['school' => $school, 'tab' => 'dashboard'])],
            ['key' => 'overview', 'label' => 'Overview', 'href' => route('admin.schools.show', ['school' => $school, 'tab' => 'overview'])],
            ['key' => 'students', 'label' => 'Students', 'href' => route('admin.schools.show', ['school' => $school, 'tab' => 'students'])],
            ['key' => 'therapists', 'label' => 'Therapists', 'href' => route('admin.schools.show', ['school' => $school, 'tab' => 'therapists'])],
            ['key' => 'contracts', 'label' => 'Contracts', 'href' => route('admin.schools.show', ['school' => $school, 'tab' => 'contracts'])],
            ['key' => 'ssas', 'label' => 'SSAs', 'href' => route('admin.schools.show', ['school' => $school, 'tab' => 'ssas'])],
            ['key' => 'calendar', 'label' => 'Calendar', 'href' => route('admin.schools.show', ['school' => $school, 'tab' => 'calendar'])],
            ['key' => 'billing', 'label' => 'Billing', 'href' => route('admin.schools.show', ['school' => $school, 'tab' => 'billing'])],
            ['key' => 'account', 'label' => 'Account', 'href' => route('admin.schools.show', ['school' => $school, 'tab' => 'account'])],
        ];
    @endphp
    <x-ui::tabs :tabs="$tabs" :active-tab="$activeTab ?? 'dashboard'" />

    {{-- Tab Content --}}
    @if (($activeTab ?? 'dashboard') === 'dashboard')
        @php
            $metricItems = [
                ['label' => 'Students', 'value' => $metrics['total_students'] ?? 0],
                ['label' => 'Therapists', 'value' => $metrics['total_therapists'] ?? 0],
                ['label' => 'Total SSAs', 'value' => $metrics['total_ssas'] ?? 0],
                ['label' => 'Active SSAs', 'value' => $statusCounts['Active'] ?? 0, 'valueClass' => 'text-success'],
            ];
        @endphp
        <x-ui::metric-grid :items="$metricItems" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-ui::card class="p-6 lg:col-span-1">
                <h3 class="text-lg font-semibold text-foreground mb-4">SSA Status Mix</h3>
                <div class="relative" style="height: 260px;">
                    <canvas id="schoolSsaChart" data-chart='@json($chartData ?? [])'></canvas>
                </div>
                <div class="mt-4 space-y-2 text-sm">
                    @foreach ($statusCounts ?? [] as $label => $count)
                        <div class="flex items-center justify-between">
                            <span class="text-foreground/70">{{ $label }}</span>
                            <span class="font-semibold">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </x-ui::card>

            <x-ui::card class="p-6 space-y-4 lg:col-span-2">
                <h3 class="text-lg font-semibold text-foreground">Primary Contact</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-foreground/70">Contact Name</p>
                        <p class="font-semibold">{{ $school->contact_first_name }} {{ $school->contact_last_name }}
                        </p>
                    </div>
                    <div>
                        <p class="text-foreground/70">Contact Email</p>
                        <p class="text-primary">{{ $school->contact_email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-foreground/70">Contact Phone</p>
                        <p>{{ $school->contact_phone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-foreground/70">Manager</p>
                        <p>{{ $school->manager?->name ?? '—' }}</p>
                    </div>
                </div>
            </x-ui::card>
        </div>
    @elseif (($activeTab ?? 'dashboard') === 'overview')
        <x-school.overview-details :school="$school" context="admin" />
    @elseif (($activeTab ?? 'dashboard') === 'students' && isset($students))
        <x-admin.students-list :students="$students" :filters="$studentFilters ?? []" :schools="$schools ?? []" :statuses="$statuses ?? []"
            :datatable-url="$datatableUrl ?? null" :school-id="$schoolId ?? null" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'therapists' && isset($therapists))
        <x-admin.therapists-list :therapists="$therapists" :filters="$therapistFilters ?? []" :positions="$positions ?? []"
            :datatable-url="$datatableUrl ?? null" :school-id="$schoolId ?? null" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'contracts' && isset($contracts))
        <x-admin.school-contracts-list :contracts="$contracts" :filters="$contractFilters ?? []" :statuses="$statuses ?? []"
            :datatable-url="$datatableUrl ?? null" :school-id="$schoolId ?? null" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'ssas' && isset($ssas))
        <x-admin.ssas-list :ssas="$ssas" :filters="$ssaFilters ?? []" :statuses="$statuses ?? []" :students="$students ?? []"
            :therapists="$therapists ?? []" :services="$services ?? []"
            :datatable-url="$datatableUrl ?? null" :school-id="$schoolId ?? null" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'calendar')
        @include('admin.schools._calendar-events', [
            'school' => $school,
            'selectedDate' => $selectedDate ?? now(),
        ])
    @elseif (($activeTab ?? 'dashboard') === 'billing')
        @include('admin.billing._entity-billing-tab', [
            'entityType' => 'school',
            'entityId' => $school->id,
        ])
    @elseif (($activeTab ?? 'dashboard') === 'account')
        @include('admin.schools._account_tab', [
            'datatableUrl' => $datatableUrl,
            'accountBalance' => $accountBalance,
        ])
    @endif

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-schools-show.js'])
        @if (($activeTab ?? 'dashboard') === 'students')
            @vite(['resources/js/pages/admin-students-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'therapists')
            @vite(['resources/js/pages/admin-therapists-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'contracts')
            @vite(['resources/js/pages/admin-contracts-schools-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'ssas')
            @vite(['resources/js/pages/admin-ssas-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'calendar')
            @vite(['resources/js/pages/admin-school-calendar-events.js'])
        @elseif (($activeTab ?? 'dashboard') === 'billing')
            @vite(['resources/js/pages/admin-entity-billing.js'])
        @elseif (($activeTab ?? 'dashboard') === 'account')
            @vite(['resources/js/pages/admin-schools-account.js'])
        @endif
    </x-slot>
</x-admin.layouts.app>
