@php
    /**
     * @var array{
     *   has_request: bool,
     *   is_open: bool,
     *   is_accepted: bool,
     *   is_cancelled: bool,
     *   reason: string|null,
     *   accepted_by_name: string|null,
     *   accepted_by_initials: string|null,
     *   accepted_at: string|null,
     *   invitee_rows: \Illuminate\Support\Collection<int, array{name: string, status_label: string, status_variant: string}>,
     *   store_url: string,
     *   update_invitees_url: string|null,
     *   cancel_url: string|null,
     *   eligible_subs_url: string,
     * } $panel
     */
@endphp

<x-ui::card class="p-6 space-y-4" id="sub_coverage_panel">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Sub Coverage</h2>
            <p class="text-sm text-foreground/60">
                @if ($panel['has_request'] && $panel['is_accepted'])
                    Substitute therapist confirmed for this session.
                @else
                    Manage substitute coverage for this session.
                @endif
            </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @if ($panel['has_request'] && $panel['is_open'])
                <x-ui::badge variant="warning">Open</x-ui::badge>
            @elseif ($panel['has_request'] && $panel['is_accepted'])
                <span class="inline-flex items-center gap-1.5 rounded-full bg-success/10 border border-success/20 px-3 py-1 text-xs font-medium text-success">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    Accepted
                </span>
            @elseif ($panel['has_request'] && $panel['is_cancelled'])
                <x-ui::badge variant="muted">Cancelled</x-ui::badge>
            @endif

            @if (!$panel['has_request'])
                <x-ui::button type="button" id="show_sub_request_form_btn" variant="secondary" size="sm">
                    Request a Sub
                </x-ui::button>
            @endif
        </div>
    </div>

    @if (!$panel['has_request'])
        {{-- Hidden until "Request a Sub" is clicked --}}
        <div id="sub_request_form_wrapper" class="hidden space-y-4 border-t border-border pt-4">
            <form method="POST" action="{{ $panel['store_url'] }}">
                @csrf
                <div class="space-y-4">
                    <div class="space-y-1">
                        <x-input-label for="panel_sub_reason" value="Reason (optional)" />
                        <p class="text-xs text-foreground/60" id="panel_sub_reason_help">
                            Briefly explain why you need coverage. Visible to the therapists you invite.
                        </p>
                        <textarea name="reason" id="panel_sub_reason" rows="3"
                            aria-describedby="panel_sub_reason_help"
                            class="w-full border border-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30"
                            placeholder="e.g. Vacation, conflict, illness…">{{ old('reason') }}</textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                    </div>

                    <div class="space-y-1">
                        <x-input-label value="Invite substitute therapists *" />
                        <p class="text-xs text-foreground/60" id="panel_invitee_help">
                            Only therapists in your position with an active contract for this service are shown.
                        </p>

                        {{-- Multi-select dropdown --}}
                        <div id="coverage_invitee_picker"
                            data-eligible-subs-url="{{ $panel['eligible_subs_url'] }}"
                            data-mode="create"
                            class="relative"
                            aria-describedby="panel_invitee_help">
                            <div id="coverage_picker_trigger"
                                class="min-h-[2.5rem] w-full flex flex-wrap gap-1.5 items-center border border-border rounded-lg px-3 py-2 bg-background cursor-pointer focus-within:ring-2 focus-within:ring-primary/30"
                                tabindex="0"
                                role="combobox"
                                aria-expanded="false"
                                aria-haspopup="listbox">
                                <span class="text-sm text-foreground/40" id="coverage_picker_placeholder">Loading eligible therapists…</span>
                            </div>

                            <div id="coverage_picker_dropdown"
                                class="hidden absolute z-20 mt-1 w-full bg-background border border-border rounded-lg shadow-lg max-h-56 overflow-y-auto"
                                role="listbox">
                                <div class="p-2 border-b border-border">
                                    <input type="text" id="coverage_picker_search"
                                        class="w-full text-sm px-2 py-1.5 rounded border border-border bg-background focus:outline-none focus:ring-1 focus:ring-primary/30"
                                        placeholder="Search therapists…" autocomplete="off" />
                                </div>
                                <div id="coverage_picker_list" class="p-1"></div>
                            </div>
                        </div>

                        <div id="coverage_invitee_inputs"></div>
                        <x-input-error :messages="$errors->get('invitee_ids')" class="mt-2" />
                        <x-input-error :messages="$errors->get('invitee_ids.*')" class="mt-1" />
                    </div>

                    <div class="flex items-center gap-3">
                        <x-ui::button type="submit">Request a Sub</x-ui::button>
                        <button type="button" id="hide_sub_request_form_btn"
                            class="text-sm text-foreground/60 hover:text-foreground transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </form>
        </div>

    @elseif ($panel['is_open'])
        {{-- Open request — show invitees + manage picker --}}
        @if ($panel['reason'])
            <div class="rounded-lg bg-muted/40 border border-border px-4 py-3">
                <p class="text-xs font-medium text-foreground/70 mb-1">Reason</p>
                <p class="text-sm text-foreground">{{ $panel['reason'] }}</p>
            </div>
        @endif

        @if ($panel['invitee_rows']->isNotEmpty())
            <div class="space-y-2">
                <p class="text-xs font-medium text-foreground/70 uppercase tracking-wider">Invitees</p>
                @foreach ($panel['invitee_rows'] as $row)
                    <div class="flex items-center justify-between rounded-lg border border-border px-4 py-2">
                        <span class="text-sm text-foreground">{{ $row['name'] }}</span>
                        <x-ui::badge :variant="$row['status_variant']">{{ $row['status_label'] }}</x-ui::badge>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Manage invitees section --}}
        <div class="border-t border-border pt-4 space-y-3">
            <div>
                <p class="text-sm font-medium text-foreground">Manage Invitees</p>
                <p class="text-xs text-foreground/60">Add or remove invited therapists. Declined therapists can be re-invited.</p>
            </div>

            {{-- Multi-select dropdown --}}
            <div id="coverage_invitee_picker"
                data-eligible-subs-url="{{ $panel['eligible_subs_url'] }}"
                data-mode="edit"
                class="relative">
                <div id="coverage_picker_trigger"
                    class="min-h-[2.5rem] w-full flex flex-wrap gap-1.5 items-center border border-border rounded-lg px-3 py-2 bg-background cursor-pointer focus-within:ring-2 focus-within:ring-primary/30"
                    tabindex="0"
                    role="combobox"
                    aria-expanded="false"
                    aria-haspopup="listbox">
                    <span class="text-sm text-foreground/40" id="coverage_picker_placeholder">Loading eligible therapists…</span>
                </div>

                <div id="coverage_picker_dropdown"
                    class="hidden absolute z-20 mt-1 w-full bg-background border border-border rounded-lg shadow-lg max-h-56 overflow-y-auto"
                    role="listbox">
                    <div class="p-2 border-b border-border">
                        <input type="text" id="coverage_picker_search"
                            class="w-full text-sm px-2 py-1.5 rounded border border-border bg-background focus:outline-none focus:ring-1 focus:ring-primary/30"
                            placeholder="Search therapists…" autocomplete="off" />
                    </div>
                    <div id="coverage_picker_list" class="p-1"></div>
                </div>
            </div>
        </div>

        <div class="border-t border-border pt-4 flex justify-end gap-3">
            <button type="button"
                data-cancel-url="{{ $panel['cancel_url'] }}"
                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-danger/30 bg-background text-danger hover:bg-danger/10 transition-colors">
                Cancel Request
            </button>
            <x-ui::button id="save_invitees_btn" type="button"
                data-patch-url="{{ $panel['update_invitees_url'] }}">
                Save Changes
            </x-ui::button>
        </div>

    @elseif ($panel['is_accepted'])
        <div class="rounded-lg border border-success/30 bg-success/5 px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-success/30 bg-success/10 text-sm font-semibold text-success">
                    {{ $panel['accepted_by_initials'] ?? '—' }}
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <span class="text-sm font-semibold text-foreground">{{ $panel['accepted_by_name'] ?? '—' }}</span>
                        <span class="text-sm text-foreground/60">Covering Therapist</span>
                    </div>
                    @if ($panel['accepted_at'])
                        <p class="mt-0.5 flex items-center gap-1.5 text-sm text-foreground/60">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <circle cx="10" cy="10" r="7.25" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6v4l2.5 2.5" />
                            </svg>
                            Accepted {{ $panel['accepted_at'] }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

    @elseif ($panel['is_cancelled'])
        {{-- Cancelled --}}
        <p class="text-sm text-foreground/60">This sub request was cancelled.</p>
    @endif
</x-ui::card>
