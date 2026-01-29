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
                            \App\Enums\SessionLogStatus::APPROVED => 'success',
                            \App\Enums\SessionLogStatus::SUBMITTED => 'warning',
                            \App\Enums\SessionLogStatus::CANCELLED => 'danger',
                            default => 'secondary',
                        }">
                            {{ $sessionLog->status->label() }}
                        </x-ui::badge>
                    @endif

                    <a href="{{ route('admin.session-logs.index') }}">
                        <x-ui::button variant="secondary">
                            Back to list
                        </x-ui::button>
                    </a>

                    <a href="{{ route('admin.session-logs.edit', $sessionLog) }}">
                        <x-ui::button>
                            Override Rates
                        </x-ui::button>
                    </a>

                    @if ($sessionLog->status?->canApprove())
                        <form action="{{ route('admin.session-logs.approve', $sessionLog) }}" method="POST">
                            @csrf
                            <x-ui::button type="submit" variant="success">
                                Approve
                            </x-ui::button>
                        </form>
                    @endif

                    @if ($sessionLog->status?->canCancel())
                        <form action="{{ route('admin.session-logs.cancel', $sessionLog) }}" method="POST">
                            @csrf
                            <input type="hidden" name="cancellation_reason" value="Cancelled by admin" />
                            <x-ui::button type="submit" variant="danger">
                                Cancel
                            </x-ui::button>
                        </form>
                    @endif
                </div>
            </div>
        </x-ui::card>

        {{-- Shared details component --}}
        <x-session-log.details :session-log="$sessionLog" />

        {{-- Documents Section --}}
        @if (isset($documents))
            <x-session-log.documents-section :session-log="$sessionLog" :documents="$documents" context="admin" />
        @endif
    </div>
</x-admin.layouts.app>
