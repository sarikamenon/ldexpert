<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (in_array($activeTab ?? 'dashboard', ['students', 'ssas', 'contracts']))
            @vite(['resources/css/common/datatables.css'])
        @endif
    </x-slot>

    {{-- Header Card --}}
    <x-ui::card class="p-6 mb-6">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <x-page-title title="{{ $therapist->name }}" />
                <p class="text-sm text-foreground/60 mt-1">Therapist ID #{{ $therapist->id }}</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <x-ui::badge :variant="$therapist->status?->value === 'active' ? 'success' : 'danger'">
                    {{ ucfirst($therapist->status?->value ?? 'inactive') }}
                </x-ui::badge>
                <a href="{{ route('admin.therapists.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                    Back to list
                </a>
                <a href="{{ route('admin.therapists.edit', $therapist) }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Edit Therapist
                </a>

                {{-- Status Change Buttons --}}
                @if ($therapist->status?->value === 'active')
                    <button type="button"
                        class="change-status-btn inline-flex items-center px-4 py-2 bg-danger text-white rounded-lg hover:bg-danger/90 text-sm font-medium"
                        data-therapist-id="{{ $therapist->id }}" data-status="inactive">
                        Deactivate
                    </button>
                @else
                    <button type="button"
                        class="change-status-btn inline-flex items-center px-4 py-2 bg-success text-white rounded-lg hover:bg-success/90 text-sm font-medium"
                        data-therapist-id="{{ $therapist->id }}" data-status="active">
                        Activate
                    </button>
                @endif
            </div>
        </div>
    </x-ui::card>

    {{-- Tabs Navigation --}}
    <div class="border-b border-border mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'dashboard']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'dashboard' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Dashboard
            </a>
            <a href="{{ route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'overview']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'overview' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Overview
            </a>
            <a href="{{ route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'contracts']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'contracts' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Contracts
            </a>
            <a href="{{ route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'ssas']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'ssas' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                SSAs
            </a>
            <a href="{{ route('admin.therapists.show', ['therapist' => $therapist, 'tab' => 'students']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'students' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Students
            </a>
        </nav>
    </div>

    {{-- Tab Content --}}
    @if (($activeTab ?? 'dashboard') === 'dashboard')
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <x-ui::card class="p-4 space-y-1">
                <p class="text-sm text-foreground/70">Active SSAs</p>
                <p class="text-2xl font-semibold text-success">{{ $metrics['active_ssas'] ?? 0 }}</p>
            </x-ui::card>
            <x-ui::card class="p-4 space-y-1">
                <p class="text-sm text-foreground/70">Completed SSAs</p>
                <p class="text-2xl font-semibold text-primary">{{ $metrics['completed_ssas'] ?? 0 }}</p>
            </x-ui::card>
            <x-ui::card class="p-4 space-y-1">
                <p class="text-sm text-foreground/70">Pending SSAs</p>
                <p class="text-2xl font-semibold text-warning">{{ $metrics['pending_ssas'] ?? 0 }}</p>
            </x-ui::card>
            <x-ui::card class="p-4 space-y-1">
                <p class="text-sm text-foreground/70">Active Students</p>
                <p class="text-2xl font-semibold">{{ $metrics['total_students'] ?? 0 }}</p>
            </x-ui::card>
        </div>

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
                        <p>{{ $therapist->therapistProfile?->timezone ?? '—' }}</p>
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
    @endif

    <x-slot name="scripts">
        @if (($activeTab ?? 'dashboard') === 'dashboard')
            @vite(['resources/js/pages/admin-therapists-show.js'])
        @elseif (($activeTab ?? 'dashboard') === 'students')
            @vite(['resources/js/pages/admin-students-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'contracts')
            @vite(['resources/js/pages/admin-contracts-therapists-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'ssas')
            @vite(['resources/js/pages/admin-ssas-index.js'])
        @endif
    </x-slot>
</x-admin.layouts.app>
