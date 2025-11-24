<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    </x-slot>

    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
        <div>
            <x-page-title title="{{ $therapist->name }}" />
            <p class="text-sm text-foreground/60">Therapist ID #{{ $therapist->id }}</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <x-ui::badge :variant="$therapist->status?->value === 'active' ? 'success' : 'warning'">
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
        </div>
    </div>

    <div x-data="{ activeTab: 'dashboard' }">
        <div class="border-b border-border mb-6">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button @click="activeTab = 'dashboard'"
                    :class="activeTab === 'dashboard' ? 'border-primary text-primary' :
                        'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    Dashboard
                </button>
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
                <button @click="activeTab = 'students'"
                    :class="activeTab === 'students' ? 'border-primary text-primary' :
                        'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    Students
                </button>
            </nav>
        </div>

        <div x-show="activeTab === 'dashboard'" x-transition>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
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
                <x-ui::card class="p-4 space-y-1">
                    <p class="text-sm text-foreground/70">Active Students</p>
                    <p class="text-2xl font-semibold">{{ $metrics['total_students'] }}</p>
                </x-ui::card>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <x-ui::card class="p-6 lg:col-span-1">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-foreground">Service Delivery</h3>
                        <span class="text-sm text-foreground/70">{{ $chartData['progress'] }}%</span>
                    </div>
                    <div class="relative" style="height: 260px;">
                        <canvas id="therapistProgressChart" data-served="{{ $chartData['served'] }}"
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
        </div>

        <div x-show="activeTab === 'overview'" x-transition>
            <x-ui::card class="p-6">
                <h3 class="text-lg font-semibold text-foreground mb-4">Therapist Details</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border">
                        <tbody class="divide-y divide-border text-sm">
                            <tr>
                                <td class="px-6 py-4 font-medium text-foreground/70 w-1/3">Name</td>
                                <td class="px-6 py-4">{{ $therapist->name }}</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 font-medium text-foreground/70">Address</td>
                                <td class="px-6 py-4">{{ $therapist->therapistProfile?->address ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 font-medium text-foreground/70">Max Weekly Hours</td>
                                <td class="px-6 py-4">{{ $therapist->therapistProfile?->max_weekly_hours ?? '—' }}</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 font-medium text-foreground/70">Date of Birth</td>
                                <td class="px-6 py-4">
                                    {{ optional($therapist->therapistProfile?->dob)->format('M d, Y') ?? '—' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 font-medium text-foreground/70">Notes</td>
                                <td class="px-6 py-4">{{ $therapist->therapistProfile?->comments ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui::card>
        </div>

        <div x-show="activeTab === 'ssas'" x-transition>
            <x-ui::card class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-foreground">Assigned SSAs</h3>
                @if ($ssas->isEmpty())
                    <p class="text-foreground/70 text-sm">No SSAs assigned.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-foreground/70">
                                    <th class="px-4 py-2">SSA</th>
                                    <th class="px-4 py-2">Student</th>
                                    <th class="px-4 py-2">Service</th>
                                    <th class="px-4 py-2">Date Range</th>
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
                                            @if ($ssa->student)
                                                <a href="{{ route('admin.students.show', $ssa->student) }}"
                                                    class="text-primary hover:underline">{{ $ssa->student->name }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $ssa->primaryService->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-foreground/60">
                                            {{ $ssa->start_date->format('M d, Y') }} –
                                            {{ $ssa->end_date->format('M d, Y') }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <x-ui::badge :variant="match ($ssa->status) {
                                                \App\Enums\SSAStatus::ACTIVE => 'success',
                                                \App\Enums\SSAStatus::PENDING => 'warning',
                                                \App\Enums\SSAStatus::COMPLETED => 'primary',
                                                \App\Enums\SSAStatus::DEACTIVATED => 'secondary',
                                                default => 'secondary',
                                            }">{{ $ssa->status->label() }}</x-ui::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui::card>
        </div>

        <div x-show="activeTab === 'students'" x-transition>
            <x-ui::card class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-foreground">Students</h3>
                @if ($students->isEmpty())
                    <p class="text-foreground/70 text-sm">No students assigned.</p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($students as $student)
                            <div class="p-4 border border-border rounded-lg space-y-1">
                                <a href="{{ route('admin.students.show', $student) }}"
                                    class="text-primary font-semibold hover:underline">
                                    {{ $student->name }}
                                </a>
                                <p class="text-sm text-foreground/70">
                                    {{ $student->studentProfile?->school?->display_name ?? 'No school' }}
                                </p>
                                <p class="text-xs text-foreground/50">
                                    Assigned on {{ optional($student->pivot?->assigned_at)->format('M d, Y') ?? '—' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui::card>
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-therapists-show.js'])
    </x-slot>
</x-admin.layouts.app>
