<x-app-layout>
    <x-slot name="title">
        Edit Schedule
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 lg:px-8 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/60">Therapist · Schedule</p>
                    <h1 class="text-2xl font-semibold text-foreground">Edit Schedule</h1>
                    <p class="text-sm text-foreground/60 mt-1">
                        Update schedule date, time, location, and notes.
                    </p>
                </div>
                <a href="{{ route('therapist.schedule.calendar', ['date' => $schedule->schedule_date?->format('Y-m-d')]) }}"
                    class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">
                    Back to Calendar
                </a>
            </div>

            {{-- Schedule Information Display --}}
            <x-ui::card class="p-6 space-y-3 border border-border">
                <h3 class="text-sm font-semibold text-foreground">Schedule Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <div class="space-y-3">
                        <div>
                            <span class="text-foreground/70 block mb-1">Student:</span>
                            <span class="font-medium text-foreground">{{ $schedule->student->name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-foreground/70 block mb-1">School:</span>
                            <span class="font-medium text-foreground">{{ $schedule->school?->display_name ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div>
                            <span class="text-foreground/70 block mb-1">Service:</span>
                            <span class="font-medium text-foreground">{{ $schedule->service?->name ?? 'N/A' }}</span>
                        </div>
                        @if ($schedule->ssa)
                            <div>
                                <span class="text-foreground/70 block mb-1">SSA:</span>
                                <span class="font-medium text-foreground">SSA #{{ $schedule->ssa->id }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </x-ui::card>

            <form method="POST" action="{{ route('therapist.schedule.update', $schedule->id) }}" id="scheduleEditForm"
                class="space-y-6">
                @csrf
                @method('PUT')

                {{-- Section 1: Schedule Details --}}
                <x-ui::card class="p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Schedule Details</h2>
                            <p class="text-sm text-foreground/60">Update date and time.</p>
                        </div>
                        <span class="text-sm text-foreground/60">All times shown in US/Central</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="schedule_date" value="Schedule Date *" />
                            <x-text-input id="schedule_date" name="schedule_date" type="date"
                                class="mt-1 block w-full"
                                value="{{ old('schedule_date', $schedule->schedule_date?->format('Y-m-d')) }}" required />
                            <x-input-error :messages="$errors->get('schedule_date')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="start_time" value="Start Time *" />
                                <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full"
                                    value="{{ old('start_time', $schedule->start_time?->format('H:i')) }}" required />
                                <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="end_time" value="End Time *" />
                                <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full"
                                    value="{{ old('end_time', $schedule->end_time?->format('H:i')) }}" required />
                                <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </x-ui::card>

                {{-- Section 2: Location & Meeting Details --}}
                <x-ui::card class="p-6 space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Location & Meeting Details</h2>
                        <p class="text-sm text-foreground/60">Update online meeting details or location information for
                            this session.</p>
                    </div>

                    <div>
                        <x-input-label for="location_details" value="Location/Meeting Details *" />
                        <textarea name="location_details" id="location_details" rows="4"
                            class="mt-1 w-full border border-border rounded-lg px-3 py-2 text-sm"
                            placeholder="Enter meeting link (e.g., Google Meet, Zoom), location address, or other meeting details..." required>{{ old('location_details', $schedule->location_details) }}</textarea>
                        <p class="text-xs text-foreground/60 mt-1">
                            Include meeting links for online sessions or address/location for in-person sessions.
                        </p>
                        <x-input-error :messages="$errors->get('location_details')" class="mt-2" />
                    </div>
                </x-ui::card>

                {{-- Section 3: Notes --}}
                <x-ui::card class="p-6 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Notes</h2>
                        <p class="text-sm text-foreground/60">Update optional notes for this schedule.</p>
                    </div>

                    <textarea name="notes" id="notes" rows="4"
                        class="w-full border border-border rounded-lg px-3 py-2 text-sm" placeholder="Notes (optional)">{{ old('notes', $schedule->notes) }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </x-ui::card>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('therapist.schedule.calendar', ['date' => $schedule->schedule_date?->format('Y-m-d')]) }}"
                        class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                        Update Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>

