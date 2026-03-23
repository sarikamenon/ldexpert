<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 space-y-6">
            @if (session('success'))
                <x-ui::alert variant="success">
                    {{ session('success') }}
                </x-ui::alert>
            @endif

            {{-- Header Card --}}
            <x-ui::card class="p-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-semibold text-foreground">
                            Session Log – {{ $sessionLog->student?->name ?? 'Unknown student' }}
                        </h1>
                        <p class="text-sm text-foreground/60 mt-1">
                            {{ $sessionLog->service?->name ?? 'No service' }}
                            · {{ $sessionLog->session_date?->format('M d, Y') ?? 'No date' }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3 flex-wrap">
                        @if ($sessionLog->status)
                            <x-ui::badge :variant="match ($sessionLog->status) {
                                \App\Enums\SessionLogStatus::APPROVED => 'success',
                                \App\Enums\SessionLogStatus::SUBMITTED => 'warning',
                                \App\Enums\SessionLogStatus::SENT_BACK => 'warning',
                                \App\Enums\SessionLogStatus::CANCELLED => 'danger',
                                default => 'secondary',
                            }">
                                {{ $sessionLog->status->label() }}
                            </x-ui::badge>
                        @endif

                        @can('update', $sessionLog)
                            <a href="{{ route('therapist.session-logs.edit', $sessionLog) }}">
                                <x-ui::button>
                                    Edit
                                </x-ui::button>
                            </a>
                        @endcan

                        @can('submit', $sessionLog)
                            @if ($sessionLog->status?->canSubmit() ?? true)
                                <form action="{{ route('therapist.session-logs.submit', $sessionLog) }}" method="POST">
                                    @csrf
                                    <x-ui::button type="submit">
                                        Submit
                                    </x-ui::button>
                                </form>
                            @endif
                        @endcan

                        <a href="{{ route('therapist.session-logs.index') }}">
                            <x-ui::button variant="secondary">
                                Back to list
                            </x-ui::button>
                        </a>
                    </div>
                </div>
            </x-ui::card>

            {{-- Details Card --}}
            <x-session-log.details :session-log="$sessionLog" />

            {{-- Documents Section --}}
            @if (isset($documents))
                <x-session-log.documents-section :session-log="$sessionLog" :documents="$documents" context="therapist" />
            @endif

            {{-- Comments Section --}}
            @if ($sessionLog->comments->count() > 0)
                <x-ui::card class="p-6">
                    <h2 class="text-lg font-semibold text-foreground mb-2">Comments</h2>
                    <p class="text-sm text-foreground/60 mb-4">Conversation between admin and therapist about this session log.</p>
                    <div class="space-y-3">
                        @foreach ($sessionLog->comments->sortBy('created_at') as $comment)
                            @php
                                $isAdminComment = $comment->type === \App\Enums\SessionLogCommentType::SENT_BACK;
                            @endphp
                            <div class="rounded-lg border border-border p-4 {{ $isAdminComment ? 'bg-muted/30' : 'bg-primary/5' }}">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs font-medium {{ $isAdminComment ? 'text-warning' : 'text-primary' }}">
                                        {{ $isAdminComment ? 'Admin' : 'Therapist' }}
                                    </span>
                                    <span class="text-xs text-foreground/60">
                                        {{ $comment->author?->name ?? ($isAdminComment ? 'Admin' : 'Therapist') }} · {{ $comment->created_at?->format('M d, Y g:i A') }}
                                    </span>
                                </div>
                                <p class="text-sm text-foreground whitespace-pre-wrap">{{ $comment->comment }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-ui::card>
            @endif
        </div>
    </div>

    <x-slot name="scripts">
        @if (isset($documents))
            @vite(['resources/js/pages/therapist-session-logs-documents.js'])
        @endif
    </x-slot>
</x-app-layout>
