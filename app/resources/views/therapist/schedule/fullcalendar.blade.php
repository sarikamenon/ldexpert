<x-app-layout>
    <x-slot name="styles">
        @vite(['resources/css/fullcalendar-custom.css'])
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-foreground">Full Calendar View</h1>
                    <p class="text-sm text-foreground/60">View your schedules in day, week, or month format.</p>
                </div>
                <a href="{{ route('therapist.schedule.calendar') }}"
                    class="inline-flex items-center px-4 py-2 border border-border rounded-lg hover:bg-background/subtle text-sm font-medium transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                    </svg>
                    Switch to List View
                </a>
            </div>

            <x-ui::card class="p-6">
                <div id="fullCalendar"
                    data-events-url="{{ route('therapist.schedule-calendar.events') }}">
                </div>
            </x-ui::card>

            {{-- Schedule Details Modal --}}
            <x-schedule.schedule-details-modal />
        </div>
    </div>

    <x-slot name="scripts">
        @vite(['resources/js/pages/therapist-schedule-fullcalendar.js'])
    </x-slot>
</x-app-layout>
