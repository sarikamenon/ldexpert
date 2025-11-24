<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    </x-slot>

    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
        <div>
            <x-page-title title="{{ $school->display_name }}" />
            <p class="text-sm text-foreground/60">School ID #{{ $school->id }}</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <x-ui::badge :variant="$school->status?->value === 'active' ? 'success' : 'warning'">
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
                <button @click="activeTab = 'students'"
                    :class="activeTab === 'students' ? 'border-primary text-primary' :
                        'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    Students
                </button>
                <button @click="activeTab = 'therapists'"
                    :class="activeTab === 'therapists' ? 'border-primary text-primary' :
                        'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    Therapists
                </button>
                <button @click="activeTab = 'ssas'"
                    :class="activeTab === 'ssas' ? 'border-primary text-primary' :
                        'border-transparent text-foreground/70 hover:text-foreground hover:border-foreground/30'"
                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                    SSAs
                </button>
            </nav>
        </div>

        <div x-show="activeTab === 'dashboard'" x-transition>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <x-ui::card class="p-4 space-y-1">
                    <p class="text-sm text-foreground/70">Students</p>
                    <p class="text-2xl font-semibold">{{ $metrics['total_students'] }}</p>
                </x-ui::card>
                <x-ui::card class="p-4 space-y-1">
                    <p class="text-sm text-foreground/70">Therapists</p>
                    <p class="text-2xl font-semibold">{{ $metrics['total_therapists'] }}</p>
                </x-ui::card>
                <x-ui::card class="p-4 space-y-1">
                    <p class="text-sm text-foreground/70">Total SSAs</p>
                    <p class="text-2xl font-semibold">{{ $metrics['total_ssas'] }}</p>
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
                        <canvas id="schoolSsaChart" data-chart='@json($chartData)'></canvas>
                    </div>
                    <div class="mt-4 space-y-2 text-sm">
                        @foreach ($statusCounts as $label => $count)
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
                            <p class="font-semibold">{{ $school->contact_first_name }}
                                {{ $school->contact_last_name }}
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
        </div>

        <div x-show="activeTab === 'overview'" x-transition>
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
                                <td class="px-6 py-4 text-foreground">{{ $school->is_private_student ? 'Yes' : 'No' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4 font-medium text-foreground/70">Non-billable Scheduling?</td>
                                <td class="px-6 py-4 text-foreground">
                                    {{ $school->non_billable_scheduling ? 'Yes' : 'No' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-ui::card>
        </div>

        <div x-show="activeTab === 'students'" x-transition>
            <x-ui::card class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-foreground">Students</h3>
                @if ($students->isEmpty())
                    <p class="text-sm text-foreground/70">No students associated with this school.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-foreground/70">
                                    <th class="px-4 py-2">Student</th>
                                    <th class="px-4 py-2">Grade</th>
                                    <th class="px-4 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($students as $student)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('admin.students.show', $student) }}"
                                                class="text-primary hover:underline font-medium">
                                                {{ $student->name }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">{{ $student->studentProfile?->grade_level ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            <x-ui::badge :variant="$student->status?->value === 'active' ? 'success' : 'secondary'">
                                                {{ ucfirst($student->status?->value ?? 'inactive') }}
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
                <h3 class="text-lg font-semibold text-foreground">Therapists</h3>
                @if ($therapists->isEmpty())
                    <p class="text-sm text-foreground/70">No therapists currently working with this school's students.
                    </p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($therapists as $therapist)
                            <div class="p-4 border border-border rounded-lg space-y-1 text-sm">
                                <a href="{{ route('admin.therapists.show', $therapist) }}"
                                    class="text-primary font-semibold hover:underline">
                                    {{ $therapist->name }}
                                </a>
                                <p class="text-foreground/70">
                                    {{ $therapist->therapistProfile?->title?->label() ?? 'Therapist' }}
                                </p>
                                <p class="text-xs text-foreground/50">
                                    Students assigned: {{ $therapist->students->count() }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui::card>
        </div>

        <div x-show="activeTab === 'ssas'" x-transition>
            <x-ui::card class="p-6 space-y-4">
                <h3 class="text-lg font-semibold text-foreground">Service Support Agreements</h3>
                @if ($ssas->isEmpty())
                    <p class="text-sm text-foreground/70">No SSAs for this school.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border text-sm">
                            <thead>
                                <tr class="text-left text-foreground/70">
                                    <th class="px-4 py-2">SSA</th>
                                    <th class="px-4 py-2">Student</th>
                                    <th class="px-4 py-2">Therapist</th>
                                    <th class="px-4 py-2">Service</th>
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
                                            <a href="{{ route('admin.students.show', $ssa->student) }}"
                                                class="text-primary hover:underline">
                                                {{ $ssa->student->name ?? '—' }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($ssa->assignedTherapist)
                                                <a href="{{ route('admin.therapists.show', $ssa->assignedTherapist) }}"
                                                    class="text-primary hover:underline">
                                                    {{ $ssa->assignedTherapist->name }}
                                                </a>
                                            @else
                                                <span class="text-foreground/50">Unassigned</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $ssa->primaryService->name ?? '—' }}</td>
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
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-schools-show.js'])
    </x-slot>
</x-admin.layouts.app>
