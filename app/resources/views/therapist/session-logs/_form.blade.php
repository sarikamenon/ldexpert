@php
    $isEdit = isset($sessionLog);
@endphp

<form method="POST"
    action="{{ $isEdit ? route('therapist.session-logs.update', $sessionLog) : route('therapist.session-logs.store') }}"
    class="space-y-6">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    @if (isset($schedule))
        <input type="hidden" name="schedule_id" value="{{ $schedule->id }}" />
    @endif

    {{-- Billing window blocked banner (shown/hidden by JS) --}}
    <div id="billing-window-blocked" class="hidden">
        <x-ui::alert variant="danger" class="mb-4">
            <p class="font-semibold">Billing window closed</p>
            <p class="text-sm mt-1">The billing entry window for this session's week closed on
                <span id="billing-window-cutoff" class="font-medium"></span>.
                You can no longer create or edit session logs for this date.
                Please contact an administrator if you need to make changes.</p>
        </x-ui::alert>
    </div>

    {{-- Session Details --}}
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Session Details</h3>

        {{-- Row 1: Student + SSA (always read-only to keep aligned with SSA) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Student</label>
                <p class="mt-1 text-xs text-foreground/60">Student associated with this session log</p>
                <input type="hidden" name="student_id"
                    value="{{ old('student_id', $sessionLog->student_id ?? ($schedule->student_id ?? ($selectedSsa->student_id ?? ''))) }}" />
                <x-ui::input type="text" id="session-log-student-name" readonly :disabled="true"
                    value="{{ $sessionLog->student->name ?? ($schedule->student?->name ?? ($selectedSsa->student?->name ?? '')) }}"
                    class="mt-1 bg-muted" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">SSA</label>
                <p class="mt-1 text-xs text-foreground/60">Service Support Agreement for this session</p>
                <input type="hidden" name="ssa_id"
                    value="{{ old('ssa_id', $sessionLog->ssa_id ?? ($schedule->ssa_id ?? ($selectedSsa->id ?? ''))) }}" />
                <x-ui::select id="session-log-ssa" name="ssa_id" disabled class="mt-1 bg-muted">
                    <option value="">Select SSA</option>
                    @foreach ($ssas ?? [] as $ssa)
                        <option value="{{ $ssa->id }}" @selected(old('ssa_id', $sessionLog->ssa_id ?? ($schedule->ssa_id ?? ($selectedSsa->id ?? ''))) == $ssa->id)
                            data-student-id="{{ $ssa->student_id }}" data-student-name="{{ $ssa->student?->name }}"
                            data-start-date="{{ $ssa->start_date?->format('Y-m-d') }}"
                            data-end-date="{{ $ssa->end_date?->format('Y-m-d') }}">
                            {{ $ssa->student?->name }} (SSA #{{ $ssa->id }} - {{ $ssa->primaryService?->name }})
                        </option>
                    @endforeach
                </x-ui::select>
            </div>
        </div>

        {{-- Row 2: Service + Session Date --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Service *</label>
                <p class="mt-1 text-xs text-foreground/60">Service provided during this session</p>
                @if (isset($schedule))
                    {{-- From schedule: service is read-only --}}
                    <input type="hidden" name="service_id"
                        value="{{ old('service_id', $sessionLog->service_id ?? ($schedule->service_id ?? '')) }}" />
                    <x-ui::input type="text" readonly :disabled="true"
                        value="{{ $schedule->service?->name ?? ($services->firstWhere('id', $schedule->service_id)->name ?? '') }}"
                        class="mt-1 bg-muted" />
                @else
                    {{-- Standalone: service selectable based on SSA --}}
                    <x-ui::select name="service_id" id="session-log-service" class="mt-1" required>
                        @if (isset($sessionLog) || isset($selectedSsa))
                            <option value="">Select service</option>
                            @foreach ($services ?? [] as $service)
                                <option value="{{ $service->id }}" @selected(old('service_id', $sessionLog->service_id ?? '') == $service->id)>
                                    {{ $service->name }}
                                </option>
                            @endforeach
                        @else
                            <option value="">Select SSA first</option>
                        @endif
                    </x-ui::select>
                @endif
                <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="session-log-date" value="Session Date *" />
                @if (isset($schedule))
                    <p class="mt-1 text-xs text-foreground/60">Date when the session occurred (from schedule)</p>
                    <input type="hidden" name="session_date"
                        value="{{ old('session_date', $scheduleLocalDate ?? $schedule->schedule_date?->format('Y-m-d')) }}" />
                    <x-ui::input type="date" id="session-log-date" class="mt-1 block w-full bg-muted"
                        value="{{ old('session_date', $scheduleLocalDate ?? $schedule->schedule_date?->format('Y-m-d')) }}"
                        readonly :disabled="true" />
                @else
                    <p class="mt-1 text-xs text-foreground/60">Date when the session occurred. Must be a past date.</p>
                    <x-ui::input type="date" name="session_date" id="session-log-date" class="mt-1 block w-full"
                        value="{{ old('session_date', isset($sessionLog) ? $sessionLog->session_date_input : now()->format('Y-m-d')) }}"
                        max="{{ now()->format('Y-m-d') }}" required />
                @endif
                <x-input-error :messages="$errors->get('session_date')" class="mt-2" />
            </div>
        </div>

        {{-- Row 3: Start Time + Duration + End Time --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Start Time *</label>
                <p class="mt-1 text-xs text-foreground/60">Time when the session started</p>
                <x-ui::input type="time" name="start_time" id="session-log-start-time"
                    value="{{ old('start_time', isset($sessionLog) ? $sessionLog->start_time_input : (isset($scheduleLocalStartTime) ? $scheduleLocalStartTime : '')) }}"
                    class="mt-1" required />
                <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Duration (minutes) *</label>
                <p class="mt-1 text-xs text-foreground/60" id="session-log-duration-help">
                    Session duration in minutes (min {{ config('session_minutes.min') }}, max
                    {{ config('session_minutes.max') }}).
                </p>
                <x-ui::input type="number" name="duration_minutes" id="session-log-duration"
                    value="{{ old('duration_minutes', isset($sessionLog) ? $sessionLog->duration_minutes ?? '' : (isset($schedule) ? $schedule->durationMinutes() : '')) }}"
                    min="{{ config('session_minutes.min') }}" max="{{ config('session_minutes.max') }}" step="1"
                    class="mt-1" aria-describedby="session-log-duration-help" required />
                <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">End Time</label>
                <p class="mt-1 text-xs text-foreground/60">Calculated based on start time and duration</p>
                <x-ui::input type="time" name="end_time" id="session-log-end-time" readonly :disabled="true"
                    value="{{ old('end_time', isset($sessionLog) ? $sessionLog->end_time_input : (isset($scheduleLocalEndTime) ? $scheduleLocalEndTime : '')) }}"
                    class="mt-1 bg-muted" required />
                <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
            </div>
        </div>

        @isset($ssaContext)
            @if ($ssaContext['mode'] === 'goals')
                <div class="space-y-3 mt-4 rounded-lg border border-border bg-muted/30 p-4">
                    <div>
                        <h4 class="text-sm font-medium text-foreground">Goals for this SSA</h4>
                        <p class="mt-1 text-xs text-foreground/60">This is the first session log for this SSA. Review the active goals before logging the session.</p>
                    </div>
                    @forelse ($ssaContext['goals'] as $goal)
                        <div class="rounded-md border border-border bg-white p-3">
                            <p class="text-sm font-semibold text-foreground">#{{ $goal->number }}</p>
                            <p class="mt-1 text-sm text-foreground/80 whitespace-pre-wrap">{{ $goal->objective }}</p>
                            @if ($goal->progress)
                                <p class="mt-2 text-xs text-foreground/60">
                                    <span class="font-medium">Progress so far:</span> {{ $goal->progress }}
                                </p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-foreground/60">No active goals are recorded for this SSA. Add goals on the SSA's Goals tab.</p>
                    @endforelse
                </div>
            @elseif ($ssaContext['mode'] === 'previous_notes' && ($ssaContext['previousLog'] ?? null))
                <div class="space-y-2 mt-4 rounded-lg border border-border bg-muted/30 p-4">
                    <h4 class="text-sm font-medium text-foreground">Previous Session Notes</h4>
                    <p class="text-xs text-foreground/60">
                        {{ $ssaContext['previousLog']->session_date_formatted }} · {{ $ssaContext['previousLog']->start_time_formatted }} – {{ $ssaContext['previousLog']->end_time_formatted }}
                        <a href="{{ route('therapist.session-logs.show', $ssaContext['previousLog']) }}"
                            target="_blank"
                            rel="noopener"
                            class="ml-2 font-medium text-primary hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded inline-flex items-center gap-1">
                            View session
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-3 h-3" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                        </a>
                    </p>
                    <p class="text-sm text-foreground/80 whitespace-pre-wrap">{{ $ssaContext['previousLog']->notes ?: 'No notes recorded on the previous session log.' }}</p>
                </div>
            @endif
        @endisset

        <div class="space-y-4 mt-4">
            <h4 class="text-sm font-medium text-foreground/70">Notes & Outcome</h4>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-foreground">Notes *</label>
                    <p class="mt-1 text-xs text-foreground/60" id="session-notes-help">
                        Session notes must be at least 20 characters. Describe what occurred during the session.
                    </p>
                    <textarea name="notes" rows="4"
                        class="mt-1 block w-full border-border focus:border-primary focus:ring-primary rounded-md shadow-sm"
                        aria-describedby="session-notes-help" required>{{ old('notes', $sessionLog->notes ?? '') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-foreground">Session Outcome *</label>
                    <p class="mt-1 text-xs text-foreground/60">Select the outcome of this session</p>
                    <x-ui::select name="outcome" class="mt-1" placeholder="Select outcome">
                        <option value="">Select outcome</option>
                        @foreach ($sessionOutcomes ?? [] as $outcome)
                            <option value="{{ $outcome->value }}" @selected(old('outcome', $sessionLog->outcome?->value ?? 'services_administered') === $outcome->value)>
                                {{ $outcome->label() }}
                            </option>
                        @endforeach
                    </x-ui::select>
                    <x-input-error :messages="$errors->get('outcome')" class="mt-2" />
                    <input type="hidden" name="is_billable_therapist"
                        value="{{ old('is_billable_therapist', $sessionLog->is_billable_therapist ?? 1) }}">
                    <input type="hidden" name="is_billable_school" value="1">
                </div>
            </div>
        </div>
    </x-ui::card>

    <div class="flex justify-end gap-3">
        <x-ui::loading-button variant="primary" loadingText="{{ $isEdit ? 'Updating...' : 'Creating...' }}"
            x-on:click="if ($el.closest('form').checkValidity()) { $nextTick(() => loading = true) }">
            {{ $isEdit ? 'Update Session Log' : 'Create Session Log' }}
        </x-ui::loading-button>
    </div>
</form>

@if ($isEdit && $sessionLog->comments->count() > 0)
    <x-ui::card class="p-6 space-y-4">
        <h3 class="text-lg font-semibold text-foreground">Comments</h3>
        <p class="text-sm text-foreground/60">Conversation between admin and therapist about this session log.</p>
        <div class="space-y-3">
            @foreach ($sessionLog->comments->sortBy('created_at') as $comment)
                @php
                    $isAdmin = $comment->type === \App\Enums\SessionLogCommentType::SENT_BACK;
                @endphp
                <div class="rounded-lg border border-border p-4 {{ $isAdmin ? 'bg-muted/30' : 'bg-primary/5' }}">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-medium {{ $isAdmin ? 'text-warning' : 'text-primary' }}">
                            {{ $isAdmin ? 'Admin' : 'Therapist' }}
                        </span>
                        <span class="text-xs text-foreground/60">
                            {{ $comment->author?->name ?? ($isAdmin ? 'Admin' : 'Therapist') }} · {{ $comment->created_at?->format(config('display.datetime')) }}
                        </span>
                    </div>
                    <p class="text-sm text-foreground whitespace-pre-wrap">{{ $comment->comment }}</p>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('therapist.session-logs.comment', $sessionLog) }}" class="space-y-3"
            x-data="{ submitAfter: false }">
            @csrf
            <input type="hidden" name="submit_after_comment" x-bind:value="submitAfter ? '1' : '0'" />
            <div>
                <label class="block text-sm font-medium text-foreground">Add a Reply</label>
                <p class="mt-1 text-xs text-foreground/60" id="therapist-comment-help">
                    Add a comment to respond to admin feedback or provide additional context.
                </p>
                <textarea name="comment" rows="3"
                    class="mt-1 block w-full border-border focus:border-primary focus:ring-primary rounded-md shadow-sm"
                    aria-describedby="therapist-comment-help"
                    placeholder="Type your reply here..." required>{{ old('comment') }}</textarea>
                @error('comment')
                    <p class="mt-1 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex justify-end gap-3">
                <x-ui::button type="submit" variant="primary" @click="submitAfter = false">
                    Add Comment
                </x-ui::button>
                @if ($sessionLog->status?->canSubmit())
                    <x-ui::button type="submit" variant="success" @click="submitAfter = true">
                        Add Comment & Submit
                    </x-ui::button>
                @endif
            </div>
        </form>
    </x-ui::card>
@endif
