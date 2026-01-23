@php
    /** @var \App\Models\School $school */
    /** @var \Carbon\CarbonImmutable $selectedDate */
@endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1 space-y-6">
        <x-schedule.calendar :selected-date="$selectedDate" />

        <x-ui::card class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-foreground">Add Calendar Event</h2>
                    <p class="text-sm text-foreground/60">Create school-wide closures or informational events.</p>
                </div>
                <button type="button" id="calendarEventReset"
                    class="text-sm text-primary hover:underline">
                    New Entry
                </button>
            </div>

            <form id="schoolCalendarEventForm" class="space-y-4"
                data-store-url="{{ route('admin.schools.calendar-events.store', $school) }}">
                <input type="hidden" id="calendar_event_id" name="calendar_event_id" value="">

                <div>
                    <x-input-label for="event_title" value="Event Title *" />
                    <p class="mt-1 text-xs text-foreground/60" id="event_title_help">
                        Provide a short name that appears on the calendar.
                    </p>
                    <x-text-input id="event_title" name="title" class="mt-1 block w-full"
                        aria-describedby="event_title_help" required />
                    <p class="text-xs text-danger mt-2 hidden" data-error-for="title"></p>
                </div>

                <div>
                    <x-input-label for="event_type" value="Event Type *" />
                    <p class="mt-1 text-xs text-foreground/60" id="event_type_help">
                        Select whether this is a holiday (blocks scheduling) or an informational event.
                    </p>
                    <select id="event_type" name="event_type" class="mt-1 block w-full"
                        aria-describedby="event_type_help" required>
                        <option value="">Select event type</option>
                        @foreach (\App\Enums\SchoolCalendarEventType::cases() as $eventType)
                            <option value="{{ $eventType->value }}">{{ $eventType->label() }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-danger mt-2 hidden" data-error-for="event_type"></p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="event_start_date" value="Start Date *" />
                        <p class="mt-1 text-xs text-foreground/60" id="event_start_date_help">
                            First day the event applies (school local date).
                        </p>
                        <x-text-input id="event_start_date" name="start_date" type="date"
                            class="mt-1 block w-full" aria-describedby="event_start_date_help" required />
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="start_date"></p>
                    </div>
                    <div>
                        <x-input-label for="event_end_date" value="End Date *" />
                        <p class="mt-1 text-xs text-foreground/60" id="event_end_date_help">
                            Last day the event applies (can be the same as start).
                        </p>
                        <x-text-input id="event_end_date" name="end_date" type="date"
                            class="mt-1 block w-full" aria-describedby="event_end_date_help" required />
                        <p class="text-xs text-danger mt-2 hidden" data-error-for="end_date"></p>
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
        <x-ui::card class="p-6">
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
        </x-ui::card>
    </div>
</div>
