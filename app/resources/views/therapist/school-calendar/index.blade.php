<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/therapist-schedule.css'])
    </x-slot>

    @php
        /** @var \Illuminate\Support\Collection<int, \App\Models\School> $schools */
        /** @var \Carbon\CarbonImmutable $selectedDate */
        $defaultSchool = $schools->first();
    @endphp

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-foreground">School Calendar</h1>
                <p class="text-sm text-foreground/60">View school holidays and events for the schools you serve.</p>
            </div>

            @if ($schools->isEmpty())
                <x-ui::card class="p-6">
                    <x-ui::empty-state
                        title="No schools assigned"
                        message="You don't have any active SSAs, so there are no school calendars to view yet." />
                </x-ui::card>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1 space-y-6">
                        <x-ui::card class="p-6">
                            <h2 class="text-lg font-semibold text-foreground mb-1">School / Family</h2>
                            <p class="text-sm text-foreground/60 mb-4">Select a school to view its holidays and events.</p>

                            <x-input-label for="school_id" value="School" />
                            <p class="mt-1 text-xs text-foreground/60" id="school_id_help">
                                Showing schools where you have an active SSA.
                            </p>
                            <x-ui::select id="school_id" name="school_id" class="mt-1"
                                aria-describedby="school_id_help"
                                data-events-url-template="{{ route('therapist.school-calendar.events', ['school' => 'SCHOOL_ID']) }}">
                                @foreach ($schools as $school)
                                    <option value="{{ $school->id }}">{{ $school->display_name ?? $school->full_name }}</option>
                                @endforeach
                            </x-ui::select>
                        </x-ui::card>
                    </div>

                    <div class="lg:col-span-2">
                        <x-ui::card class="p-6 space-y-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-lg font-semibold text-foreground">Calendar</h2>
                                    <p class="text-sm text-foreground/60">Monthly view of school holidays and events.</p>
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

                                <div id="calendarEventsList"></div>
                            </div>
                        </x-ui::card>
                    </div>
                </div>
            @endif
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

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-school-calendar.js'])
    </x-slot>
</x-app-layout>
