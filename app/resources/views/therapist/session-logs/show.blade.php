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
                                \App\Enums\SessionLogStatus::CANCELLED => 'danger',
                                default => 'secondary',
                            }">
                                {{ $sessionLog->status->label() }}
                            </x-ui::badge>
                        @endif

                        @can('update', $sessionLog)
                            <a href="{{ route('therapist.session-logs.edit', $sessionLog) }}"
                                class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                                Edit
                            </a>
                        @endcan

                        @can('submit', $sessionLog)
                            @if ($sessionLog->status?->canSubmit() ?? true)
                                <form action="{{ route('therapist.session-logs.submit', $sessionLog) }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                                        Submit
                                    </button>
                                </form>
                            @endif
                        @endcan

                        <a href="{{ route('therapist.session-logs.index') }}"
                            class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                            Back to list
                        </a>
                    </div>
                </div>
            </x-ui::card>

            {{-- Details Card --}}
            <x-session-log.details :session-log="$sessionLog" />
        </div>
    </div>
</x-app-layout>
