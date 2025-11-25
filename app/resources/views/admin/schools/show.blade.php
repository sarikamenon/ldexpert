<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (in_array($activeTab ?? 'dashboard', ['students', 'therapists', 'ssas']))
            @vite(['resources/css/common/datatables.css'])
        @endif
    </x-slot>

    {{-- Header Card --}}
    <x-ui::card class="p-6 mb-6">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <x-page-title title="{{ $school->display_name }}" />
                <p class="text-sm text-foreground/60 mt-1">School ID #{{ $school->id }}</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <x-ui::badge :variant="$school->status?->value === 'active' ? 'success' : 'danger'">
                    {{ ucfirst($school->status?->value ?? 'inactive') }}
                </x-ui::badge>
                <a href="{{ route('admin.schools.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                    Back to list
                </a>
                <a href="{{ route('admin.schools.edit', $school) }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Edit School
                </a>

                {{-- Status Change Buttons --}}
                @if ($school->status?->value === 'active')
                    <button type="button"
                        class="change-status-btn inline-flex items-center px-4 py-2 bg-danger text-white rounded-lg hover:bg-danger/90 text-sm font-medium"
                        data-school-id="{{ $school->id }}" data-status="inactive">
                        Deactivate
                    </button>
                @else
                    <button type="button"
                        class="change-status-btn inline-flex items-center px-4 py-2 bg-success text-white rounded-lg hover:bg-success/90 text-sm font-medium"
                        data-school-id="{{ $school->id }}" data-status="active">
                        Activate
                    </button>
                @endif
            </div>
        </div>
    </x-ui::card>

    {{-- Tabs Navigation --}}
    <div class="border-b border-border mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('admin.schools.show', ['school' => $school, 'tab' => 'dashboard']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'dashboard' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Dashboard
            </a>
            <a href="{{ route('admin.schools.show', ['school' => $school, 'tab' => 'overview']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'overview' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Overview
            </a>
            <a href="{{ route('admin.schools.show', ['school' => $school, 'tab' => 'students']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'students' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Students
            </a>
            <a href="{{ route('admin.schools.show', ['school' => $school, 'tab' => 'therapists']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'therapists' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Therapists
            </a>
            <a href="{{ route('admin.schools.show', ['school' => $school, 'tab' => 'ssas']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'ssas' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                SSAs
            </a>
        </nav>
    </div>

    {{-- Tab Content --}}
    @if (($activeTab ?? 'dashboard') === 'dashboard')
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <x-ui::card class="p-4 space-y-1">
                <p class="text-sm text-foreground/70">Students</p>
                <p class="text-2xl font-semibold">{{ $metrics['total_students'] ?? 0 }}</p>
            </x-ui::card>
            <x-ui::card class="p-4 space-y-1">
                <p class="text-sm text-foreground/70">Therapists</p>
                <p class="text-2xl font-semibold">{{ $metrics['total_therapists'] ?? 0 }}</p>
            </x-ui::card>
            <x-ui::card class="p-4 space-y-1">
                <p class="text-sm text-foreground/70">Total SSAs</p>
                <p class="text-2xl font-semibold">{{ $metrics['total_ssas'] ?? 0 }}</p>
            </x-ui::card>
            <x-ui::card class="p-4 space-y-1">
                <p class="text-sm text-foreground/70">Active SSAs</p>
                <p class="text-2xl font-semibold text-success">{{ $statusCounts['Active'] ?? 0 }}</p>
            </x-ui::card>
        </div>

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
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">School Details</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <tbody class="divide-y divide-border">
                        <tr>
                            <td class="px-6 py-4 font-medium text-foreground/70 w-1/3">Full Name</td>
                            <td class="px-6 py-4 text-foreground">{{ $school->full_name }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium text-foreground/70">Display Name</td>
                            <td class="px-6 py-4 text-foreground">{{ $school->display_name }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium text-foreground/70">State</td>
                            <td class="px-6 py-4 text-foreground">{{ $school->state ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium text-foreground/70">Timezone</td>
                            <td class="px-6 py-4 text-foreground">{{ $school->timezone ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium text-foreground/70">School Type</td>
                            <td class="px-6 py-4 text-foreground">{{ $school->school_type ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium text-foreground/70">Invoice Email</td>
                            <td class="px-6 py-4 text-primary">{{ $school->invoice_email ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium text-foreground/70">External EMR Name</td>
                            <td class="px-6 py-4 text-foreground">{{ $school->external_emr_name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium text-foreground/70">Private Student?</td>
                            <td class="px-6 py-4 text-foreground">{{ $school->is_private_student ? 'Yes' : 'No' }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 font-medium text-foreground/70">Non-billable Scheduling?</td>
                            <td class="px-6 py-4 text-foreground">{{ $school->non_billable_scheduling ? 'Yes' : 'No' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-ui::card>
    @elseif (($activeTab ?? 'dashboard') === 'students' && isset($students))
        <x-admin.students-list :students="$students" :filters="$studentFilters ?? []" :schools="$schools ?? []" :statuses="$statuses ?? []"
            context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'therapists' && isset($therapists))
        <x-admin.therapists-list :therapists="$therapists" :filters="$therapistFilters ?? []" :positions="$positions ?? []" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'ssas' && isset($ssas))
        <x-admin.ssas-list :ssas="$ssas" :filters="$ssaFilters ?? []" :statuses="$statuses ?? []" :students="$students ?? []" :therapists="$therapists ?? []"
            :services="$services ?? []" context="detail" />
    @endif

    <x-slot name="scripts">
        @if (($activeTab ?? 'dashboard') === 'dashboard')
            @vite(['resources/js/pages/admin-schools-show.js'])
        @elseif (($activeTab ?? 'dashboard') === 'students')
            @vite(['resources/js/pages/admin-students-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'therapists')
            @vite(['resources/js/pages/admin-therapists-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'ssas')
            @vite(['resources/js/pages/admin-ssas-index.js'])
        @endif
    </x-slot>
</x-admin.layouts.app>
