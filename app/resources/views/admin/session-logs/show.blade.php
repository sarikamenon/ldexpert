<x-admin.layouts.app>
    <div class="space-y-6">
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
                            \App\Enums\SessionLogStatus::FINALIZED => 'success',
                            \App\Enums\SessionLogStatus::SUBMITTED => 'warning',
                            \App\Enums\SessionLogStatus::CANCELLED => 'danger',
                            default => 'secondary',
                        }">
                            {{ $sessionLog->status->label() }}
                        </x-ui::badge>
                    @endif

                    <a href="{{ route('admin.session-logs.index') }}"
                        class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                        Back to list
                    </a>

                    <a href="{{ route('admin.session-logs.edit', $sessionLog) }}"
                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                        Override Rates
                    </a>

                    @if ($sessionLog->status?->canFinalize())
                        <form action="{{ route('admin.session-logs.finalize', $sessionLog) }}" method="POST">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-success text-white rounded-lg hover:bg-success/90 text-sm font-medium">
                                Finalize
                            </button>
                        </form>
                    @endif

                    @if ($sessionLog->status?->canCancel())
                        <form action="{{ route('admin.session-logs.cancel', $sessionLog) }}" method="POST">
                            @csrf
                            <input type="hidden" name="cancellation_reason" value="Cancelled by admin" />
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-danger text-white rounded-lg hover:bg-danger/90 text-sm font-medium">
                                Cancel
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </x-ui::card>

        {{-- Shared details component --}}
        <x-session-log.details :session-log="$sessionLog" />
    </div>
</x-admin.layouts.app>
