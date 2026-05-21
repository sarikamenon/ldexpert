@php
    /** @var \App\Models\School $school */
    /** @var \Carbon\CarbonImmutable $selectedDate */
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-6">
        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Add Calendar Event</h2>
                    <p class="text-sm text-foreground/60">Create school/family-wide closures or informational events.</p>
                </div>
            </div>

            <form id="schoolCalendarEventForm" class="space-y-4"
                data-store-url="{{ route('admin.schools.calendar-events.store', $school) }}">
                <input type="hidden" id="calendar_event_id" name="calendar_event_id" value="">

                <div>
                    <x-input-label for="event_title" value="Event Title *" />
                    <p class="mt-1 text-xs text-foreground/60" id="event_title_help">
                        Provide a short name that appears on the calendar.
                    </p>
                    <x-ui::input id="event_title" name="title" class="mt-1 block w-full"
                        aria-describedby="event_title_help" required />
                    <p class="text-xs text-danger mt-2 hidden" data-error-for="title"></p>
                </div>

                <div>
                    <x-input-label for="event_type" value="Event Type *" />
                    <p class="mt-1 text-xs text-foreground/60" id="event_type_help">
                        Select whether this is a holiday (blocks scheduling) or an informational event.
                    </p>
                    <x-ui::select id="event_type" name="event_type" class="mt-1"
                        aria-describedby="event_type_help" required>
                        <option value="">Select event type</option>
                        @foreach (\App\Enums\SchoolCalendarEventType::cases() as $eventType)
                            <option value="{{ $eventType->value }}">{{ $eventType->label() }}</option>
                        @endforeach
                    </x-ui::select>
                    <p class="text-xs text-danger mt-2 hidden" data-error-for="event_type"></p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="event_start_date" value="Start Date *" />
                        <p class="mt-1 text-xs text-foreground/60" id="event_start_date_help">
                            First day the event applies (local date for this school or family).
                        </p>
                        <x-ui::input id="event_start_date" name="start_date" type="date"
                            class="mt-1 block w-full" aria-describedby="event_start_date_help" required />
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="start_date"></p>
                    </div>
                    <div>
                        <x-input-label for="event_end_date" value="End Date *" />
                        <p class="mt-1 text-xs text-foreground/60" id="event_end_date_help">
                            Last day the event applies (can be the same as start).
                        </p>
                        <x-ui::input id="event_end_date" name="end_date" type="date"
                            class="mt-1 block w-full" aria-describedby="event_end_date_help" required />
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="end_date"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="event_reminder_date" value="Email Send Date *" />
                        <p class="mt-1 text-xs text-foreground/60" id="event_reminder_date_help">
                            Date the make-up reminder email goes to parents.
                        </p>
                        <x-ui::input id="event_reminder_date" name="reminder_date" type="date"
                            class="mt-1 block w-full" aria-describedby="event_reminder_date_help" required />
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="reminder_date"></p>
                    </div>
                    <div>
                        <x-input-label for="event_response_date" value="Response Date *" />
                        <p class="mt-1 text-xs text-foreground/60" id="event_response_date_help">
                            Date by which a parent response is requested (shown in the email).
                        </p>
                        <x-ui::input id="event_response_date" name="response_date" type="date"
                            class="mt-1 block w-full" aria-describedby="event_response_date_help" required />
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="response_date"></p>
                    </div>
                    <div>
                        <x-input-label for="event_deadline_date" value="Response Deadline *" />
                        <p class="mt-1 text-xs text-foreground/60" id="event_deadline_date_help">
                            Hard cutoff — requests with no response by this date are auto-declined.
                        </p>
                        <x-ui::input id="event_deadline_date" name="deadline_date" type="date"
                            class="mt-1 block w-full" aria-describedby="event_deadline_date_help" required />
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="deadline_date"></p>
                    </div>
                </div>

                <div>
                    <x-input-label for="event_notes" value="Notes" />
                    <p class="mt-1 text-xs text-foreground/60" id="event_notes_help">
                        Optional details to show staff why the event was added.
                    </p>
                    <textarea id="event_notes" name="notes" rows="3"
                        class="mt-1 w-full border border-border rounded-lg px-3 py-2 text-sm"
                        aria-describedby="event_notes_help"></textarea>
                    <p class="text-xs text-danger mt-2 hidden" data-error-for="notes"></p>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" id="calendarEventCancel"
                        class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle hidden">
                        Cancel Edit
                    </button>
                    <button type="submit" id="calendarEventSubmit"
                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                        Save Event
                    </button>
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
                <button type="button"
                    class="calendar-today-btn px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                    TODAY'S VIEW
                </button>
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
