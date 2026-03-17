<x-app-layout>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (in_array($activeTab ?? 'dashboard', ['ssas', 'session_logs']))
            @vite(['resources/css/common/datatables.css'])
        @endif
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            @if (session('success'))
                <x-ui::alert variant="success" class="mb-6">
                    {{ session('success') }}
                </x-ui::alert>
            @endif

            {{-- Header Card --}}
            <x-ui::show-header :title="$student->name" :subtitle="'Student ID #' . $student->id" :back-url="route('therapist.students.index')" back-label="Back to list">
                <x-slot name="badge">
                    <x-ui::badge :variant="$student->status?->value === 'active' ? 'success' : 'danger'">
                        {{ ucfirst($student->status?->value ?? 'inactive') }}
                    </x-ui::badge>
                </x-slot>
            </x-ui::show-header>

            {{-- Tabs Navigation --}}
            @php
                $tabs = [
                    [
                        'key' => 'dashboard',
                        'label' => 'Dashboard',
                        'href' => route('therapist.students.show', ['student' => $student, 'tab' => 'dashboard']),
                    ],
                    [
                        'key' => 'overview',
                        'label' => 'Overview',
                        'href' => route('therapist.students.show', ['student' => $student, 'tab' => 'overview']),
                    ],
                    [
                        'key' => 'ssas',
                        'label' => 'SSAs',
                        'href' => route('therapist.students.show', ['student' => $student, 'tab' => 'ssas']),
                    ],
                    [
                        'key' => 'session_logs',
                        'label' => 'Session Logs',
                        'href' => route('therapist.students.show', ['student' => $student, 'tab' => 'session_logs']),
                    ],
                    [
                        'key' => 'comments',
                        'label' => 'Comments',
                        'href' => route('therapist.students.show', ['student' => $student, 'tab' => 'comments']),
                    ],
                    [
                        'key' => 'documents',
                        'label' => 'Documents',
                        'href' => route('therapist.students.show', ['student' => $student, 'tab' => 'documents']),
                    ],
                ];
            @endphp
            <x-ui::tabs :tabs="$tabs" :active-tab="$activeTab ?? 'dashboard'" />

            {{-- Tab Content --}}
            @if (($activeTab ?? 'dashboard') === 'dashboard')
                @php
                    $metricItems = [
                        ['label' => 'Total SSAs', 'value' => $metrics['total_ssas'] ?? 0],
                        [
                            'label' => 'Active SSAs',
                            'value' => $metrics['active_ssas'] ?? 0,
                            'valueClass' => 'text-success',
                        ],
                        [
                            'label' => 'Completed SSAs',
                            'value' => $metrics['completed_ssas'] ?? 0,
                            'valueClass' => 'text-primary',
                        ],
                        [
                            'label' => 'Pending SSAs',
                            'value' => $metrics['pending_ssas'] ?? 0,
                            'valueClass' => 'text-warning',
                        ],
                    ];
                @endphp
                <x-ui::metric-grid :items="$metricItems" />

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
                                <span class="text-foreground/70">Served Hours</span>
                                <span class="font-semibold">{{ number_format($chartData['served'] ?? 0, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-foreground/70">Remaining Hours</span>
                                <span class="font-semibold">{{ number_format($chartData['remaining'] ?? 0, 2) }}</span>
                            </div>
                        </div>
                    </x-ui::card>

                    <x-ui::card class="p-6 space-y-3 lg:col-span-2">
                        <h3 class="text-lg font-semibold text-foreground">School & Guardian</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-foreground/70">School</p>
                                @if ($student->studentProfile?->school)
                                    <p class="text-lg font-semibold">
                                        {{ $student->studentProfile->school->display_name }}
                                    </p>
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
                                    <p class="text-lg font-semibold">
                                        {{ $student->studentProfile->parent_guardian_name }}</p>
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
                <x-student.overview-details :student="$student" context="therapist" />
            @elseif (($activeTab ?? 'dashboard') === 'ssas' && isset($ssas))
                <x-admin.ssas-list :ssas="$ssas" :filters="$ssaFilters ?? []" :statuses="$statuses ?? []" :students="[]"
                    :therapists="[]" :services="[]" :datatable-url="$datatableUrl ?? null" :student-id="$studentId ?? null" context="therapist" />
            @elseif (($activeTab ?? 'dashboard') === 'session_logs' && isset($sessionLogStatuses))
                <x-admin.session-logs-list :filters="$sessionLogFilters ?? []" :statuses="$sessionLogStatuses ?? []"
                    :datatable-url="$datatableUrl ?? null" :student-id="$studentId ?? null" context="detail" />
            @elseif (($activeTab ?? 'dashboard') === 'comments' && isset($comments))
                <x-student.comments-section :student="$student" :comments="$comments" context="therapist" />
            @elseif (($activeTab ?? 'dashboard') === 'documents' && isset($documents))
                <x-student.documents-section :student="$student" :documents="$documents" context="therapist" />
            @endif
        </div>
    </div>

    <x-slot name="scripts">
        @if (($activeTab ?? 'dashboard') === 'dashboard')
            @vite(['resources/js/pages/therapist-students-show.js'])
        @elseif (($activeTab ?? 'dashboard') === 'ssas')
            @vite(['resources/js/pages/therapist-ssas-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'session_logs')
            @vite(['resources/js/pages/therapist-session-logs-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'comments')
            @vite(['resources/js/pages/therapist-students-comments.js'])
        @elseif (($activeTab ?? 'dashboard') === 'documents')
            @vite(['resources/js/pages/therapist-students-documents.js'])
        @endif
    </x-slot>
</x-app-layout>
