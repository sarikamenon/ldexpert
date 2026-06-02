{{--
    @var \App\Models\School $school
    @var \Carbon\CarbonImmutable $selectedDate
    @var array<int, array{value: string, label: string}> $eventTypeOptions
    @var string|null $defaultEventType
--}}

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-6">
        <x-ui::card class="p-6">
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-foreground">Add calendar event</h2>
                <p class="text-sm text-foreground/60">Create a school or family-wide closure or informational event.</p>
            </div>

            <form id="schoolCalendarEventForm" class="space-y-6"
                data-store-url="{{ route('admin.schools.calendar-events.store', $school) }}">
                <input type="hidden" id="calendar_event_id" name="calendar_event_id" value="">

                {{-- Section: Event details --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-border">
                        <span class="text-xs font-semibold tracking-wider uppercase text-foreground/60">Event details</span>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5">
                            <x-input-label for="event_title" value="Title" />
                            <x-ui::tooltip-icon content="Shown on the calendar" />
                        </div>
                        <x-ui::input id="event_title" name="title" class="mt-1 block w-full" required />
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="title"></p>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5">
                            <x-input-label for="event_type" value="Type" />
                            <x-ui::tooltip-icon content="Holiday blocks scheduling; informational is display-only" />
                        </div>
                        <x-ui::select id="event_type" name="event_type" class="mt-1" required>
                            @foreach ($eventTypeOptions as $eventType)
                                <option value="{{ $eventType['value'] }}" @selected($eventType['value'] === $defaultEventType)>{{ $eventType['label'] }}</option>
                            @endforeach
                        </x-ui::select>
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="event_type"></p>
                    </div>
                </div>

                {{-- Section: When it applies --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-border">
                        <svg class="w-3.5 h-3.5 text-foreground/60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span class="text-xs font-semibold tracking-wider uppercase text-foreground/60">When it applies</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="event_start_date" value="Start date" />
                            <x-ui::input id="event_start_date" name="start_date" type="date"
                                class="mt-1 block w-full" required />
                            <p class="text-xs text-danger mt-2 hidden" data-error-for="start_date"></p>
                        </div>
                        <div>
                            <x-input-label for="event_end_date" value="End date" />
                            <x-ui::input id="event_end_date" name="end_date" type="date"
                                class="mt-1 block w-full" required />
                            <p class="text-xs text-danger mt-2 hidden" data-error-for="end_date"></p>
                        </div>
                    </div>
                </div>

                {{-- Section: Parent notifications --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-border">
                        <svg class="w-3.5 h-3.5 text-foreground/60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                        <span class="text-xs font-semibold tracking-wider uppercase text-foreground/60">Parent notifications</span>
                    </div>

                    <x-ui::checkbox-row
                        name="request_makeup"
                        id="event_request_makeup"
                        label="Request makeup"
                        subtext="Email parents to confirm whether a makeup is needed."
                    />

                    <div id="event_makeup_dates" class="hidden border-l-4 border-primary bg-primary/5 rounded-r-lg p-4 space-y-3">
                        <div class="flex items-center gap-4">
                            <div class="flex-1 flex items-center gap-1.5">
                                <x-input-label for="event_reminder_date" value="Email send date" />
                                <x-ui::tooltip-icon content="Reminder goes to parents" />
                            </div>
                            <div class="flex-1">
                                <x-ui::input id="event_reminder_date" name="reminder_date" type="date"
                                    class="block w-full" />
                                <p class="text-xs text-danger mt-2 hidden" data-error-for="reminder_date"></p>
                            </div>
                        </div>
                        <div class="border-t border-primary/20"></div>
                        <div class="flex items-center gap-4">
                            <div class="flex-1 flex items-center gap-1.5">
                                <x-input-label for="event_response_date" value="Response requested by" />
                                <x-ui::tooltip-icon content="Date shown in the email" />
                            </div>
                            <div class="flex-1">
                                <x-ui::input id="event_response_date" name="response_date" type="date"
                                    class="block w-full" />
                                <p class="text-xs text-danger mt-2 hidden" data-error-for="response_date"></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section: Internal notes --}}
                <div class="space-y-4">
                    <div class="flex items-center gap-2 pb-2 border-b border-border">
                        <svg class="w-3.5 h-3.5 text-foreground/60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <span class="text-xs font-semibold tracking-wider uppercase text-foreground/60">Internal notes <span class="font-normal normal-case tracking-normal text-foreground/40">(optional)</span></span>
                    </div>

                    <div>
                        <textarea id="event_notes" name="notes" rows="3"
                            class="block w-full border border-border rounded-lg px-3 py-2 text-sm"
                            placeholder="Why was this event added? Visible to staff only."></textarea>
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="notes"></p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                    <x-ui::button id="calendarEventCancel" variant="secondary" class="hidden">
                        Cancel
                    </x-ui::button>
                    <x-ui::button type="submit" id="calendarEventSubmit">
                        Save event
                    </x-ui::button>
                </div>
            </form>
        </x-ui::card>
    </div>

    <div class="lg:col-span-2">
        <x-ui::card class="p-6 space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Calendar</h2>
                    <p class="text-sm text-foreground/60">Monthly view of school/family events.</p>
                </div>
                <x-ui::button class="calendar-today-btn">TODAY'S VIEW</x-ui::button>
            </div>

            <div id="calendar" data-selected-date="{{ $selectedDate->format('Y-m-d') }}"
                class="calendar-widget calendar-admin">
                {{-- Calendar will be rendered by JavaScript --}}
            </div>

            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Events</h2>
                        <p class="text-sm text-foreground/60" id="calendarEventDateLabel"></p>
                    </div>
                    <span class="text-xs text-foreground/60">Holiday events block scheduling</span>
                </div>

                <div id="calendarEventsList"
                    data-list-url="{{ route('admin.schools.calendar-events.index', $school) }}"
                    data-update-url-template="{{ route('admin.schools.calendar-events.update', ['school' => $school, 'event' => 'EVENT_ID']) }}"
                    data-delete-url-template="{{ route('admin.schools.calendar-events.destroy', ['school' => $school, 'event' => 'EVENT_ID']) }}">
                </div>
            </div>
        </x-ui::card>
    </div>
</div>

<x-modal name="schoolCalendarEventDetailsModal" max-width="2xl">
    <div class="flex flex-col max-h-[calc(100vh-4rem)]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border shrink-0">
            <div>
                <h3 class="text-lg font-semibold text-foreground">Calendar Event Details</h3>
                <p class="text-xs text-foreground/60" id="schoolCalendarEventDetailsDate"></p>
            </div>
            <button type="button" x-on:click="$dispatch('close-modal', 'schoolCalendarEventDetailsModal')"
                class="text-foreground/60 hover:text-foreground transition-colors" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div id="schoolCalendarEventDetailsContent" class="flex-1 overflow-y-auto px-6 py-4 space-y-4"></div>
    </div>
</x-modal>
