<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    </x-slot>

    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
        <div>
            <x-page-title title="{{ $student->name }}" />
            <p class="text-sm text-foreground/60">Student ID #{{ $student->id }}</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <x-ui::badge :variant="$student->status?->value === 'active' ? 'success' : 'warning'">
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
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-ui::card class="p-4 space-y-1">
            <p class="text-sm text-foreground/70">Total SSAs</p>
            <p class="text-2xl font-semibold">{{ $metrics['total_ssas'] }}</p>
        </x-ui::card>
        <x-ui::card class="p-4 space-y-1">
            <p class="text-sm text-foreground/70">Active SSAs</p>
            <p class="text-2xl font-semibold text-success">{{ $metrics['active_ssas'] }}</p>
        </x-ui::card>
        <x-ui::card class="p-4 space-y-1">
            <p class="text-sm text-foreground/70">Completed SSAs</p>
            <p class="text-2xl font-semibold text-primary">{{ $metrics['completed_ssas'] }}</p>
        </x-ui::card>
        <x-ui::card class="p-4 space-y-1">
            <p class="text-sm text-foreground/70">Pending SSAs</p>
            <p class="text-2xl font-semibold text-warning">{{ $metrics['pending_ssas'] }}</p>
        </x-ui::card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <x-ui::card class="p-6 lg:col-span-1">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-foreground">Service Progress</h3>
                <span class="text-sm text-foreground/70">{{ $chartData['progress'] }}%</span>
            </div>
            <div class="relative" style="height: 260px;">
                <canvas id="studentProgressChart" data-served="{{ $chartData['served'] }}"
                    data-tho="{{ $chartData['served'] + $chartData['remaining'] }}"></canvas>
            </div>
            <div class="mt-4 space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-foreground/70">Served Minutes</span>
                    <span class="font-semibold">{{ number_format($chartData['served']) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-foreground/70">Remaining Minutes</span>
                    <span class="font-semibold">{{ number_format($chartData['remaining']) }}</span>
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

    <div x-data="{ activeTab: 'overview' }">
        <div class="border-b border-border mb-6">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button @click="activeTab = 'overview'"
                    :class="activeTab === 'overview' ? 'border-primary text-primary' :
                        'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    Overview
                </button>
                <button @click="activeTab = 'ssas'"
                    :class="activeTab === 'ssas' ? 'border-primary text-primary' :
                        'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    SSAs
                </button>
                <button @click="activeTab = 'therapists'"
                    :class="activeTab === 'therapists' ? 'border-primary text-primary' :
                        'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    Therapists
                </button>
            </nav>
        </div>

        <div x-show="activeTab === 'overview'" x-transition>
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
        </div>

        <div x-show="activeTab === 'ssas'" x-transition>
            <x-ui::card class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-foreground">Service Support Agreements</h3>
                @if ($ssas->isEmpty())
                    <p class="text-foreground/70 text-sm">No SSAs recorded for this student.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-foreground/70">
                                    <th class="px-4 py-2">ID</th>
                                    <th class="px-4 py-2">Service</th>
                                    <th class="px-4 py-2">Therapist</th>
                                    <th class="px-4 py-2">Date Range</th>
                                    <th class="px-4 py-2">Minutes</th>
                                    <th class="px-4 py-2 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($ssas as $ssa)
                                    <tr>
                                        <td class="px-4 py-3 font-medium">
                                            <a href="{{ route('admin.ssas.show', $ssa) }}"
                                                class="text-primary hover:underline">#{{ $ssa->id }}</a>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="font-medium">{{ $ssa->primaryService->name ?? '—' }}</p>
                                            <p class="text-foreground/60 text-xs">Frequency:
                                                {{ $ssa->frequency->label() }}</p>
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ $ssa->assignedTherapist->name ?? 'Unassigned' }}
                                        </td>
                                        <td class="px-4 py-3 text-foreground/70">
                                            {{ $ssa->start_date->format('M d, Y') }} –
                                            {{ $ssa->end_date->format('M d, Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-col text-xs text-foreground/70">
                                                <span>THO: {{ number_format($ssa->tho_minutes) }}</span>
                                                <span>Served: {{ number_format($ssa->served_minutes) }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <x-ui::badge :variant="match ($ssa->status) {
                                                \App\Enums\SSAStatus::ACTIVE => 'success',
                                                \App\Enums\SSAStatus::PENDING => 'warning',
                                                \App\Enums\SSAStatus::COMPLETED => 'primary',
                                                \App\Enums\SSAStatus::DEACTIVATED => 'secondary',
                                                default => 'secondary',
                                            }">
                                                {{ $ssa->status->label() }}
                                            </x-ui::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui::card>
        </div>

        <div x-show="activeTab === 'therapists'" x-transition>
            <x-ui::card class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-foreground">Assigned Therapists</h3>
                @if ($therapists->isEmpty())
                    <p class="text-foreground/70 text-sm">No therapists have been assigned to this student yet.</p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($therapists as $therapist)
                            <div class="p-4 border border-border rounded-lg space-y-1">
                                <a href="{{ route('admin.therapists.show', $therapist) }}"
                                    class="text-primary font-semibold hover:underline">{{ $therapist->name }}</a>
                                <p class="text-sm text-foreground/70">
                                    {{ $therapist->therapistProfile?->title ?? 'Therapist' }}
                                </p>
                                <p class="text-xs text-foreground/50">
                                    Assigned on
                                    {{ optional($therapist->pivot?->assigned_at)->format('M d, Y') ?? '—' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui::card>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-students-show.js'])
    </x-slot>
</x-admin.layouts.app>
