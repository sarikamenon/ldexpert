<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 lg:px-8 space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm text-foreground/60">Therapist · QGlob Requests</p>
                    <h1 class="text-2xl font-semibold text-foreground">Request details</h1>
                </div>
                <div class="flex items-center gap-2">
                    @if ($qglobRequest->status === \App\Enums\QGlobRequestStatus::PENDING)
                        <form method="POST" action="{{ route('therapist.qglob-requests.destroy', $qglobRequest) }}"
                            class="inline">
                            @csrf
                            @method('DELETE')
                            <x-ui::button type="submit" variant="danger"
                                class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                data-confirm-title="Delete request?"
                                data-confirm-text="This will permanently remove this QGlob request."
                                data-confirm-icon="warning">
                                Delete
                            </x-ui::button>
                        </form>
                    @endif
                    <a href="{{ route('therapist.qglob-requests.index') }}">
                        <x-ui::button variant="secondary" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            Back to list
                        </x-ui::button>
                    </a>
                </div>
            </div>

            <x-ui::card class="p-6 space-y-4 text-sm">
                @php
                    $status = $qglobRequest->status;
                    $badgeClass = match ($status) {
                        \App\Enums\QGlobRequestStatus::APPROVED => 'bg-success/10 text-success border border-success/20',
                        \App\Enums\QGlobRequestStatus::REJECTED => 'bg-danger/10 text-danger border border-danger/20',
                        default => 'bg-warning/10 text-warning border border-warning/20',
                    };
                @endphp
                <div class="flex items-center gap-2">
                    <span class="text-foreground/60">Status</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium {{ $badgeClass }}">
                        {{ $status->label() }}
                    </span>
                </div>

                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-foreground/70">Student</dt>
                        <dd class="text-foreground font-medium">{{ $qglobRequest->student?->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-foreground/70">School/Family</dt>
                        <dd>{{ $qglobRequest->student?->studentProfile?->school?->display_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-foreground/70">Requested date</dt>
                        <dd>{{ $qglobRequest->requested_date->format('M j, Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-foreground/70">Requested time</dt>
                        <dd>
                            @php
                                $t = trim((string) $qglobRequest->getAttribute('requested_time'));
                            @endphp
                            {{ $t !== '' ? \Carbon\Carbon::parse($qglobRequest->requested_date->format('Y-m-d').' '.$t)->format('g:i A') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-foreground/70">Your note</dt>
                        <dd class="whitespace-pre-wrap">{{ $qglobRequest->note ?: '—' }}</dd>
                    </div>
                    @if ($qglobRequest->admin_response)
                        <div>
                            <dt class="text-xs font-medium text-foreground/70">Admin response</dt>
                            <dd class="whitespace-pre-wrap">{{ $qglobRequest->admin_response }}</dd>
                        </div>
                    @endif
                    @if ($qglobRequest->responded_at)
                        <div>
                            <dt class="text-xs font-medium text-foreground/70">Responded</dt>
                            <dd>{{ $qglobRequest->responded_at->format('M j, Y g:i A') }}
                                @if ($qglobRequest->respondedBy)
                                    · {{ $qglobRequest->respondedBy->name }}
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </x-ui::card>
        </div>
    </div>
    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-qglob-requests-show.js'])
    </x-slot>
</x-app-layout>
