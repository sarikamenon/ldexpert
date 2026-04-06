<x-admin.layouts.app>
    <x-page-title title="Lead Details" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    {{-- Header --}}
    <x-ui::card class="p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-foreground">{{ $lead->full_name }}</h2>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    @php
                        $badgeClass = match(true) {
                            $lead->status->isActive() => 'bg-info/10 text-info border border-info/20',
                            $lead->status === \App\Enums\LeadStatus::ENROLLED => 'bg-success/10 text-success border border-success/20',
                            $lead->status->isNegativeTerminal() => 'bg-danger/10 text-danger border border-danger/20',
                            default => 'bg-secondary/10 text-foreground border border-secondary/20',
                        };
                    @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium {{ $badgeClass }}">
                        {{ $lead->status->label() }}
                    </span>
                    @if ($lead->source)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium bg-secondary/10 text-foreground border border-secondary/20">
                            {{ $lead->source->label() }}
                        </span>
                    @endif
                    <span class="text-xs text-foreground/60">Created {{ $lead->created_at?->format('M d, Y') }} by {{ $lead->createdBy?->name ?? 'Unknown' }}</span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.leads.edit', $lead) }}">
                    <x-ui::button variant="secondary">Edit</x-ui::button>
                </a>
                @if ($lead->status->isActive())
                    <a href="{{ route('admin.leads.convert', $lead) }}">
                        <x-ui::button>Convert to Student</x-ui::button>
                    </a>
                @endif
                <x-ui::button variant="danger" id="deleteLeadBtn" data-lead-id="{{ $lead->id }}">Delete</x-ui::button>
            </div>
        </div>
    </x-ui::card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Contact Info --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold mb-4">Contact Information</h3>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-foreground/60">Email</dt>
                    <dd class="font-medium">{{ $lead->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-foreground/60">Gender</dt>
                    <dd class="font-medium">{{ $lead->gender ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-foreground/60">Date of Birth</dt>
                    <dd class="font-medium">{{ $lead->date_of_birth?->format('M d, Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-foreground/60">School/Family</dt>
                    <dd class="font-medium">
                        @if ($lead->school)
                            <a href="{{ route('admin.schools.show', $lead->school) }}" class="text-primary hover:underline">{{ $lead->school->display_name }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-foreground/60">Grade Level</dt>
                    <dd class="font-medium">{{ $lead->grade_level ?? '—' }}</dd>
                </div>
            </dl>
        </x-ui::card>

        {{-- Parent/Guardian & Address --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold mb-4">Parent / Guardian</h3>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-foreground/60">Name</dt>
                    <dd class="font-medium">{{ $lead->parent_guardian_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-foreground/60">Email</dt>
                    <dd class="font-medium">{{ $lead->parent_guardian_email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-foreground/60">Phone</dt>
                    <dd class="font-medium">{{ $lead->parent_guardian_phone ?? '—' }}</dd>
                </div>
            </dl>

            <h3 class="text-lg font-semibold mt-6 mb-4">Address</h3>
            <dl class="space-y-3 text-sm">
                <div>
                    <dd class="font-medium">
                        {{ $lead->address ?? '' }}
                        @if ($lead->city || $lead->state || $lead->zip_code)
                            <br>{{ implode(', ', array_filter([$lead->city, $lead->state])) }} {{ $lead->zip_code }}
                        @else
                            {{ $lead->address ? '' : '—' }}
                        @endif
                    </dd>
                </div>
            </dl>
        </x-ui::card>

        {{-- Pipeline Info --}}
        <x-ui::card class="p-6">
            <h3 class="text-lg font-semibold mb-4">Pipeline</h3>
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-foreground/60">Current Status</dt>
                    <dd class="font-medium mt-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium {{ $badgeClass }}">
                            {{ $lead->status->label() }}
                        </span>
                    </dd>
                </div>
                @if ($lead->status_reason)
                    <div>
                        <dt class="text-foreground/60">Status Reason</dt>
                        <dd class="font-medium">{{ $lead->status_reason }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-foreground/60">Follow-up Date</dt>
                    <dd class="font-medium {{ $lead->follow_up_date && $lead->follow_up_date->lt(now()->startOfDay()) && $lead->status->isActive() ? 'text-danger' : '' }}">
                        {{ $lead->follow_up_date?->format('M d, Y') ?? '—' }}
                        @if ($lead->follow_up_date && $lead->follow_up_date->lt(now()->startOfDay()) && $lead->status->isActive())
                            <span class="text-xs">(Overdue)</span>
                        @endif
                    </dd>
                </div>
                @if ($lead->follow_up_notes)
                    <div>
                        <dt class="text-foreground/60">Follow-up Notes</dt>
                        <dd class="font-medium">{{ $lead->follow_up_notes }}</dd>
                    </div>
                @endif
                @if ($lead->convertedStudent)
                    <div>
                        <dt class="text-foreground/60">Converted Student</dt>
                        <dd class="font-medium">
                            <a href="{{ route('admin.students.show', $lead->converted_student_id) }}" class="text-primary hover:underline">
                                {{ $lead->convertedStudent->name }}
                            </a>
                            <span class="text-xs text-foreground/60">({{ $lead->converted_at?->format('M d, Y') }})</span>
                        </dd>
                    </div>
                @endif
            </dl>

            {{-- Status Change --}}
            @if ($lead->status->isActive())
                <div class="mt-6 pt-4 border-t border-border">
                    <h4 class="text-sm font-semibold mb-3">Change Status</h4>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($statuses as $status)
                            @if ($status->value !== $lead->status->value)
                                <button type="button"
                                    class="change-lead-status text-xs px-3 py-1.5 rounded-base border hover:opacity-80 transition
                                        {{ $status->isNegativeTerminal() ? 'border-danger/30 text-danger bg-danger/5' : ($status === \App\Enums\LeadStatus::ENROLLED ? 'border-success/30 text-success bg-success/5' : 'border-secondary/30 text-foreground bg-secondary/5') }}"
                                    data-lead-id="{{ $lead->id }}"
                                    data-status="{{ $status->value }}"
                                    data-label="{{ $status->label() }}"
                                    data-is-negative="{{ $status->isNegativeTerminal() ? '1' : '0' }}">
                                    {{ $status->label() }}
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </x-ui::card>
    </div>

    {{-- Notes Section --}}
    <x-ui::card class="p-6">
        <h3 class="text-lg font-semibold mb-4">Notes</h3>

        {{-- Add Note Form --}}
        <form id="addNoteForm" class="mb-6">
            @csrf
            <div>
                <x-input-label for="note" value="Add a Note" />
                <p class="mt-1 text-xs text-foreground/60" id="note_help">Add a note or activity log entry for this lead</p>
                <textarea id="note" name="note" rows="3" aria-describedby="note_help"
                    class="mt-1 block w-full border-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-sm"
                    placeholder="Type your note here..." data-lead-id="{{ $lead->id }}"></textarea>
            </div>
            <div class="mt-2 flex justify-end">
                <x-ui::button type="submit" id="submitNoteBtn">Add Note</x-ui::button>
            </div>
        </form>

        {{-- Notes List --}}
        <div id="notesList" class="space-y-4">
            @forelse ($lead->leadNotes as $note)
                <div class="border border-border rounded-base p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium">{{ $note->author?->name ?? 'Unknown' }}</span>
                        <span class="text-xs text-foreground/60">{{ $note->created_at?->format('M d, Y h:i A') }}</span>
                    </div>
                    <p class="text-sm text-foreground">{{ $note->note }}</p>
                </div>
            @empty
                <p class="text-sm text-foreground/60 py-4 text-center">No notes yet.</p>
            @endforelse
        </div>
    </x-ui::card>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-leads-show.js'])
    </x-slot>
</x-admin.layouts.app>
