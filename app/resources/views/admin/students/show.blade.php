<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (in_array($activeTab ?? 'dashboard', ['ssas', 'therapists']))
            @vite(['resources/css/common/datatables.css'])
        @endif
    </x-slot>

    {{-- Header Card --}}
    <x-ui::card class="p-6 mb-6">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <x-page-title title="{{ $student->name }}" />
                <p class="text-sm text-foreground/60 mt-1">Student ID #{{ $student->id }}</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <x-ui::badge :variant="$student->status?->value === 'active' ? 'success' : 'danger'">
                    {{ ucfirst($student->status?->value ?? 'inactive') }}
                </x-ui::badge>
                <a href="{{ route('admin.students.index') }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                    Back to list
                </a>
                <a href="{{ route('admin.students.edit', $student) }}"
                    class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    Edit Student
                </a>

                {{-- Status Change Buttons --}}
                @if ($student->status?->value === 'active')
                    <button type="button"
                        class="change-status-btn inline-flex items-center px-4 py-2 bg-danger text-white rounded-lg hover:bg-danger/90 text-sm font-medium"
                        data-student-id="{{ $student->id }}" data-status="inactive">
                        Deactivate
                    </button>
                @else
                    <button type="button"
                        class="change-status-btn inline-flex items-center px-4 py-2 bg-success text-white rounded-lg hover:bg-success/90 text-sm font-medium"
                        data-student-id="{{ $student->id }}" data-status="active">
                        Activate
                    </button>
                @endif
            </div>
        </div>
    </x-ui::card>

    {{-- Tabs Navigation --}}
    <div class="border-b border-border mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <a href="{{ route('admin.students.show', ['student' => $student, 'tab' => 'dashboard']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'dashboard' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Dashboard
            </a>
            <a href="{{ route('admin.students.show', ['student' => $student, 'tab' => 'overview']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'overview' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Overview
            </a>
            <a href="{{ route('admin.students.show', ['student' => $student, 'tab' => 'ssas']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'ssas' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                SSAs
            </a>
            <a href="{{ route('admin.students.show', ['student' => $student, 'tab' => 'therapists']) }}"
                class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ ($activeTab ?? 'dashboard') === 'therapists' ? 'border-primary text-primary' : 'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30' }}">
                Therapists
            </a>
        </nav>
    </div>

    {{-- Tab Content --}}
    @if (($activeTab ?? 'dashboard') === 'dashboard')
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <x-ui::card class="p-4 space-y-1">
                <p class="text-sm text-foreground/70">Total SSAs</p>
                <p class="text-2xl font-semibold">{{ $metrics['total_ssas'] ?? 0 }}</p>
            </x-ui::card>
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
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-ui::card class="p-6 lg:col-span-1">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-foreground">Service Progress</h3>
                    <span class="text-sm text-foreground/70">{{ $chartData['progress'] ?? 0 }}%</span>
                </div>
                <div class="relative" style="height: 260px;">
                    <canvas id="studentProgressChart" data-served="{{ $chartData['served'] ?? 0 }}"
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

            <x-ui::card class="p-6 space-y-3 lg:col-span-2">
                <h3 class="text-lg font-semibold text-foreground">School & Guardian</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-foreground/70">School</p>
                        @if ($student->studentProfile?->school)
                            <a href="{{ route('admin.schools.show', $student->studentProfile->school) }}"
                                class="text-lg font-semibold text-primary hover:underline">
                                {{ $student->studentProfile->school->display_name }}
                            </a>
                            <p class="text-sm text-foreground/60">
                                {{ $student->studentProfile->school->state ?? '—' }}
                            </p>
                        @else
                            <p class="text-lg font-semibold text-foreground/50">Not assigned</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-foreground/70">Guardian</p>
                        @if ($student->studentProfile?->parent_guardian_name)
                            <p class="text-lg font-semibold">{{ $student->studentProfile->parent_guardian_name }}</p>
                            <p class="text-sm text-foreground/60">
                                {{ $student->studentProfile->parent_guardian_email ?? '—' }} ·
                                {{ $student->studentProfile->parent_guardian_phone ?? '—' }}
                            </p>
                        @else
                            <p class="text-lg font-semibold text-foreground/50">Not provided</p>
                        @endif
                    </div>
                </div>
            </x-ui::card>
        </div>
    @elseif (($activeTab ?? 'dashboard') === 'overview')
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Student Details</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border">
                    <tbody class="divide-y divide-border">
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-foreground/70 w-1/3">Name</td>
                            <td class="px-6 py-4 text-sm text-foreground">{{ $student->name }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-foreground/70">Email</td>
                            <td class="px-6 py-4 text-sm text-primary">{{ $student->email }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-foreground/70">Grade Level</td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                {{ $student->studentProfile?->grade_level ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-foreground/70">Student ID Number</td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                {{ $student->studentProfile?->id_number ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-foreground/70">Timezone</td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                {{ $student->studentProfile?->timezone ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-foreground/70">Date of Birth</td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                {{ optional($student->studentProfile?->date_of_birth)->format('M d, Y') ?? '—' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-foreground/70">Address</td>
                            <td class="px-6 py-4 text-sm text-foreground">
                                {{ $student->studentProfile?->address ?? '—' }}<br>
                                {{ $student->studentProfile?->city }} {{ $student->studentProfile?->state }}
                                {{ $student->studentProfile?->zip_code }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-ui::card>
    @elseif (($activeTab ?? 'dashboard') === 'ssas' && isset($ssas))
        <x-admin.ssas-list :ssas="$ssas" :filters="$ssaFilters ?? []" :statuses="$statuses ?? []" :students="$students ?? []" :therapists="$therapists ?? []"
            :services="$services ?? []" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'therapists' && isset($therapists))
        <x-admin.therapists-list :therapists="$therapists" :filters="$therapistFilters ?? []" :positions="$positions ?? []" context="detail" />
    @endif

    <x-slot name="scripts">
        @if (($activeTab ?? 'dashboard') === 'dashboard')
            @vite(['resources/js/pages/admin-students-show.js'])
        @elseif (($activeTab ?? 'dashboard') === 'ssas')
            @vite(['resources/js/pages/admin-ssas-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'therapists')
            @vite(['resources/js/pages/admin-therapists-index.js'])
        @endif
    </x-slot>
</x-admin.layouts.app>
