<x-admin.layouts.app>
    <x-page-title title="QGlob Request" />

    @if (session('status'))
        <x-ui::alert variant="success" class="mb-4">{{ session('status') }}</x-ui::alert>
    @endif

    @php
        $status = $qglobRequest->status;
        $badgeClass = match ($status) {
            \App\Enums\QGlobRequestStatus::APPROVED => 'bg-success/10 text-success border border-success/20',
            \App\Enums\QGlobRequestStatus::REJECTED => 'bg-danger/10 text-danger border border-danger/20',
            default => 'bg-warning/10 text-warning border border-warning/20',
        };
    @endphp

    <x-ui::card class="p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-foreground">Request #{{ $qglobRequest->id }}</h2>
                <div class="mt-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-base text-xs font-medium {{ $badgeClass }}">
                        {{ $status->label() }}
                    </span>
                </div>
            </div>
            <a href="{{ route('admin.qglob-requests.index') }}">
                <x-ui::button variant="secondary" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Back to list
                </x-ui::button>
            </a>
        </div>
    </x-ui::card>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <x-ui::card class="p-6 space-y-3 text-sm">
            <h3 class="text-lg font-semibold text-foreground mb-2">Details</h3>
            <dl class="space-y-3">
                <div>
                    <dt class="text-xs font-medium text-foreground/70">Therapist</dt>
                    <dd class="font-medium">{{ $qglobRequest->requestedBy?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-foreground/70">Student</dt>
                    <dd class="font-medium">{{ $qglobRequest->student?->name ?? '—' }}</dd>
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
                        {{ $t !== '' ? \Carbon\Carbon::parse($qglobRequest->requested_date->format('Y-m-d').' '.$t)->format(config('display.time')) : '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium text-foreground/70">Therapist note</dt>
                    <dd class="whitespace-pre-wrap">{{ $qglobRequest->note ?: '—' }}</dd>
                </div>
            </dl>
        </x-ui::card>

        <div class="space-y-6">
            @if ($status === \App\Enums\QGlobRequestStatus::PENDING)
                <x-ui::card class="p-6">
                    <h3 class="text-lg font-semibold text-foreground mb-4">Respond</h3>
                    <form method="post" action="{{ route('admin.qglob-requests.respond', $qglobRequest) }}" class="space-y-4">
                        @csrf
                        <fieldset>
                            <legend class="text-xs font-medium text-foreground/70">Decision *</legend>
                            <p class="mt-1 text-xs text-foreground/60" id="decision_help">Approve or reject this QGlob access request.</p>
                            <div class="mt-2 flex flex-wrap gap-4" role="radiogroup" aria-describedby="decision_help">
                                <label class="inline-flex items-center gap-2 text-sm text-foreground cursor-pointer">
                                    <input type="radio" name="decision" value="{{ \App\Enums\QGlobRequestStatus::APPROVED->value }}"
                                        class="rounded-full border-border text-primary focus:ring-ring"
                                        @checked(old('decision') === \App\Enums\QGlobRequestStatus::APPROVED->value) required />
                                    Approve
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-foreground cursor-pointer">
                                    <input type="radio" name="decision" value="{{ \App\Enums\QGlobRequestStatus::REJECTED->value }}"
                                        class="rounded-full border-border text-primary focus:ring-ring"
                                        @checked(old('decision') === \App\Enums\QGlobRequestStatus::REJECTED->value) />
                                    Reject
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('decision')" class="mt-2" />
                        </fieldset>
                        <div>
                            <x-input-label for="admin_response" value="Admin note (optional)" />
                            <p class="mt-1 text-xs text-foreground/60" id="admin_response_help">
                                Visible to the therapist (e.g. credentials timing or rejection reason).
                            </p>
                            <textarea id="admin_response" name="admin_response" rows="3"
                                class="mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-ring text-sm"
                                aria-describedby="admin_response_help">{{ old('admin_response') }}</textarea>
                            <x-input-error :messages="$errors->get('admin_response')" class="mt-2" />
                        </div>
                        <x-ui::button type="submit" class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            Save response
                        </x-ui::button>
                    </form>
                </x-ui::card>
            @endif

            @if ($qglobRequest->admin_response || $qglobRequest->responded_at)
                <x-ui::card class="p-6 space-y-2 text-sm">
                    <h3 class="text-lg font-semibold text-foreground">Response history</h3>
                    @if ($qglobRequest->admin_response)
                        <p class="whitespace-pre-wrap">{{ $qglobRequest->admin_response }}</p>
                    @endif
                    @if ($qglobRequest->responded_at)
                        <p class="text-xs text-foreground/60">
                            {{ $qglobRequest->responded_at->format(config('display.datetime')) }}
                            @if ($qglobRequest->respondedBy)
                                · {{ $qglobRequest->respondedBy->name }}
                            @endif
                        </p>
                    @endif
                </x-ui::card>
            @endif
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-qglob-requests-show.js'])
    </x-slot>
</x-admin.layouts.app>
