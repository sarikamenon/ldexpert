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

            @if ($sessionLog->isSentBack())
                <x-ui::card class="p-6">
                    <h2 class="text-lg font-semibold text-foreground mb-2">Admin feedback</h2>
                    <p class="text-sm text-foreground/60 mb-4">This session was sent back for rectification. Please address the comment(s) below and resubmit.</p>
                    <div class="space-y-4">
                        @foreach ($sessionLog->comments->where('type', \App\Enums\SessionLogCommentType::SENT_BACK)->sortByDesc('created_at') as $comment)
                            <div class="rounded-lg border border-border p-4 bg-muted/30">
                                <p class="text-xs text-foreground/60 mb-1">
                                    {{ $comment->author?->name ?? 'Admin' }} · {{ $comment->created_at?->format('M d, Y g:i A') }}
                                </p>
                                <p class="text-sm text-foreground whitespace-pre-wrap">{{ $comment->comment }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-ui::card>
            @endif

            {{-- Details Card --}}
            <x-session-log.details :session-log="$sessionLog" />

            {{-- Documents Section --}}
            @if (isset($documents))
                <x-session-log.documents-section :session-log="$sessionLog" :documents="$documents" context="therapist" />
            @endif
        </div>
    </div>

    <x-slot name="scripts">
        @if (isset($documents))
            @vite(['resources/js/pages/therapist-session-logs-documents.js'])
        @endif
    </x-slot>
</x-app-layout>
