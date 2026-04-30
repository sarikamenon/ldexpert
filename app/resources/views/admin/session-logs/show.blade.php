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
                        · {{ $sessionLog->session_date_formatted ?? 'No date' }}
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

                    <a href="{{ route('admin.session-logs.index') }}">
                        <x-ui::button variant="secondary">
                            Back to list
                        </x-ui::button>
                    </a>

                    @if ($sessionLog->isAttachedToInvoiceOrTherapistBill())
                        <span
                            class="inline-flex rounded-base focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            title="{{ \App\Models\SessionLog::RATE_OVERRIDE_BLOCKED_MESSAGE }}"
                            tabindex="0"
                        >
                            <x-ui::button type="button" disabled>
                                Override Rates
                            </x-ui::button>
                        </span>
                    @else
                        <x-ui::button href="{{ route('admin.session-logs.edit', $sessionLog) }}">
                            Override Rates
                        </x-ui::button>
                    @endif

                    @if ($sessionLog->status?->canApprove())
                        <form action="{{ route('admin.session-logs.approve', $sessionLog) }}" method="POST">
                            @csrf
                            <x-ui::button type="submit" variant="success">
                                Approve
                            </x-ui::button>
                        </form>
                    @endif

                    @if ($sessionLog->status?->canSendBack())
                        <a href="#send-back-form" class="inline-block">
                            <x-ui::button type="button" variant="danger">
                                Send back
                            </x-ui::button>
                        </a>
                    @endif

                    @if ($sessionLog->status?->canCancel())
                        <form action="{{ route('admin.session-logs.cancel', $sessionLog) }}" method="POST">
                            @csrf
                            <input type="hidden" name="cancellation_reason" value="Cancelled by admin" />
                            <x-ui::button type="submit" variant="warning">
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

        {{-- Send back for rectification (when submitted) --}}
        @if ($sessionLog->status?->canSendBack())
            <x-ui::card class="p-6" id="send-back-form">
                <h2 class="text-lg font-semibold text-foreground mb-2">Send back for rectification</h2>
                <p class="text-sm text-foreground/60 mb-4">
                    Send this session log back to the therapist with a comment. They will be notified by email and can edit and resubmit.
                </p>
                <form action="{{ route('admin.session-logs.send-back', $sessionLog) }}" method="POST" class="space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="send_back_comment" value="Comment (required) *" />
                        <p class="mt-1 text-xs text-foreground/60" id="send_back_comment_help">
                            Explain what the therapist needs to correct or add before resubmission.
                        </p>
                        <textarea
                            id="send_back_comment"
                            name="comment"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-border bg-background shadow-sm focus:border-ring focus:ring-ring text-sm"
                            aria-describedby="send_back_comment_help"
                            required
                        >{{ old('comment') }}</textarea>
                        <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                    </div>
                    <x-ui::button type="submit" variant="primary">
                        Send back to therapist
                    </x-ui::button>
                </form>
            </x-ui::card>
        @endif
    </div>
</x-admin.layouts.app>
