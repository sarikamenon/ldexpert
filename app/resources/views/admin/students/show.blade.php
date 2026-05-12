<x-admin.layouts.app>
    <x-slot name="styles">
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @if (in_array($activeTab ?? 'dashboard', ['ssas', 'therapists', 'schedule', 'session_logs']))
            @vite(['resources/css/common/datatables.css'])
        @endif
    </x-slot>

    @if (session('success'))
        <x-ui::alert variant="success" class="mb-6">
            {{ session('success') }}
        </x-ui::alert>
    @endif

    {{-- Header Card --}}
    <x-ui::show-header :title="$student->name" :subtitle="'Student ID #' . $student->id"
        :back-url="route('admin.students.index')" back-label="Back to list"
        :edit-url="route('admin.students.edit', $student)" edit-label="Edit Student">
        <x-slot name="badge">
            <x-ui::badge :variant="$student->status?->value === 'active' ? 'success' : 'danger'">
                {{ ucfirst($student->status?->value ?? 'inactive') }}
            </x-ui::badge>
        </x-slot>
        <x-slot name="actions">
            <x-ui::status-toggle :status="$student->status?->value" data-student-id="{{ $student->id }}" />
        </x-slot>
    </x-ui::show-header>

    {{-- Tabs Navigation --}}
    @php
        $tabs = [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'dashboard'])],
            ['key' => 'overview', 'label' => 'Overview', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'overview'])],
            ['key' => 'ssas', 'label' => 'SSAs', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'ssas'])],
            ['key' => 'goals', 'label' => 'Goals', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'goals'])],
            ['key' => 'therapists', 'label' => 'Therapists', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'therapists'])],
            ['key' => 'schedule', 'label' => 'Schedule', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'schedule'])],
            ['key' => 'session_logs', 'label' => 'Session Logs', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'session_logs'])],
            ['key' => 'comments', 'label' => 'Comments', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'comments'])],
            ['key' => 'documents', 'label' => 'Documents', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'documents'])],
            ['key' => 'email_history', 'label' => 'Email History', 'href' => route('admin.students.show', ['student' => $student, 'tab' => 'email_history'])],
        ];
    @endphp
    <x-ui::tabs :tabs="$tabs" :active-tab="$activeTab ?? 'dashboard'" />

    {{-- Tab Content --}}
    @if (($activeTab ?? 'dashboard') === 'dashboard')
        @php
            $metricItems = [
                ['label' => 'Total SSAs', 'value' => $metrics['total_ssas'] ?? 0],
                ['label' => 'Active SSAs', 'value' => $metrics['active_ssas'] ?? 0, 'valueClass' => 'text-success'],
                ['label' => 'Completed SSAs', 'value' => $metrics['completed_ssas'] ?? 0, 'valueClass' => 'text-primary'],
                ['label' => 'Pending SSAs', 'value' => $metrics['pending_ssas'] ?? 0, 'valueClass' => 'text-warning'],
            ];
        @endphp
        <x-ui::metric-grid :items="$metricItems" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-ui::card class="p-6 lg:col-span-1">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-foreground">Service Progress</h3>
                        <p class="text-xs text-foreground/60 mt-0.5">THO hours by session outcome</p>
                    </div>
                    <span class="text-sm text-foreground/70">{{ $chartData['progress'] ?? 0 }}%</span>
                </div>
                <div class="flex items-stretch gap-2 mb-4">
                    <div class="flex-1 min-w-0 rounded-md bg-muted/40 px-2 py-2 text-center">
                        <p class="text-[11px] font-medium text-foreground/60 truncate">Total THO</p>
                        <p class="text-sm font-semibold text-foreground">{{ number_format($chartData['total_tho_hours'] ?? 0, 2) }}</p>
                    </div>
                    <div class="flex-1 min-w-0 rounded-md bg-muted/40 px-2 py-2 text-center">
                        <p class="text-[11px] font-medium text-foreground/60 truncate">Served</p>
                        <p class="text-sm font-semibold text-foreground">{{ number_format($chartData['served_hours'] ?? 0, 2) }}</p>
                    </div>
                    <div class="flex-1 min-w-0 rounded-md bg-muted/40 px-2 py-2 text-center">
                        <p class="text-[11px] font-medium text-foreground/60 truncate">Remaining</p>
                        <p class="text-sm font-semibold text-foreground">{{ number_format($chartData['remaining_hours'] ?? 0, 2) }}</p>
                    </div>
                </div>
                @if (!empty($chartData['outcomes']))
                    <div class="relative" style="height: 260px;">
                        <canvas id="studentProgressChart"
                            data-outcomes='@json($chartData['outcomes'])'></canvas>
                    </div>
                    <div class="mt-4 space-y-2">
                        @foreach ($chartData['outcomes'] as $outcome)
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 text-foreground/70">
                                    <span class="js-outcome-swatch inline-block h-2.5 w-2.5 rounded-full bg-foreground/20"
                                        data-color-key="{{ $outcome['color_key'] }}"></span>
                                    {{ $outcome['label'] }}
                                </span>
                                <span class="font-semibold">{{ number_format($outcome['hours'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center justify-center text-sm text-foreground/60" style="height: 260px;">
                        No session logs yet.
                    </div>
                @endif
            </x-ui::card>

            <x-ui::card class="p-6 space-y-3 lg:col-span-2">
                <h3 class="text-lg font-semibold text-foreground">School/Family & Guardian</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-foreground/70">School/Family</p>
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
                        @if ($student->studentProfile?->schedule_email)
                            <p class="text-sm text-foreground/60 mt-1">Schedule email: {{ $student->studentProfile->schedule_email }}</p>
                        @endif
                    </div>
                </div>
            </x-ui::card>
        </div>
    @elseif (($activeTab ?? 'dashboard') === 'overview')
        <x-student.overview-details :student="$student" context="admin" />
    @elseif (($activeTab ?? 'dashboard') === 'ssas' && isset($ssas))
        <x-admin.ssas-list :ssas="$ssas" :filters="$ssaFilters ?? []" :statuses="$statuses ?? []" :students="$students ?? []" :therapists="$therapists ?? []"
            :services="$services ?? []" :datatable-url="$datatableUrl ?? null" :student-id="$studentId ?? null" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'goals' && isset($goals))
        @include('therapist.students._goals-tab', ['goals' => $goals, 'student' => $student, 'ssaRoute' => 'admin.ssas.show'])
    @elseif (($activeTab ?? 'dashboard') === 'therapists' && isset($therapists))
        <x-admin.therapists-list :therapists="$therapists" :filters="$therapistFilters ?? []" :positions="$positions ?? []"
            :datatable-url="$datatableUrl ?? null" :student-id="$studentId ?? null" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'schedule' && isset($scheduleFilters))
        <x-admin.schedules-list :schedules="$schedules ?? collect()" :filters="$scheduleFilters ?? []" :statuses="$scheduleStatuses ?? []" :billingStatuses="$billingStatuses ?? []"
            :ssas="$ssas ?? []" :therapists="$therapists ?? []" context="detail"
            :datatable-url="$scheduleDatatableUrl ?? null" :student-id="$scheduleStudentId ?? null" />
    @elseif (($activeTab ?? 'dashboard') === 'session_logs' && isset($sessionLogStatuses))
        <x-admin.session-logs-list :filters="$sessionLogFilters ?? []" :statuses="$sessionLogStatuses ?? []"
            :datatable-url="$datatableUrl ?? null" :student-id="$studentId ?? null" context="detail" />
    @elseif (($activeTab ?? 'dashboard') === 'comments' && isset($comments))
        <x-student.comments-section :student="$student" :comments="$comments" context="admin" />
    @elseif (($activeTab ?? 'dashboard') === 'documents' && isset($documents))
        <x-student.documents-section :student="$student" :documents="$documents" context="admin" />
    @elseif (($activeTab ?? 'dashboard') === 'email_history' && isset($emailLogs))
        <x-student.email-history-section :student="$student" :email-logs="$emailLogs" />
    @endif

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-students-show.js'])
        @if (($activeTab ?? 'dashboard') === 'ssas')
            @vite(['resources/js/pages/admin-ssas-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'therapists')
            @vite(['resources/js/pages/admin-therapists-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'schedule')
            @vite(['resources/js/pages/admin-students-schedule.js'])
        @elseif (($activeTab ?? 'dashboard') === 'session_logs')
            @vite(['resources/js/pages/admin-session-logs-index.js'])
        @elseif (($activeTab ?? 'dashboard') === 'comments')
            @vite(['resources/js/pages/admin-students-comments.js'])
        @elseif (($activeTab ?? 'dashboard') === 'documents')
            @vite(['resources/js/pages/admin-student-documents.js'])
        @endif
    </x-slot>
</x-admin.layouts.app>
