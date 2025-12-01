<x-app-layout>
    <x-slot name="title">
        Create Schedule
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 lg:px-8 space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-foreground/60">Therapist · Schedule</p>
                    <h1 class="text-2xl font-semibold text-foreground">Create New Schedule</h1>
                    <p class="text-sm text-foreground/60 mt-1">
                        Create a single schedule for a student with an active SSA.
                    </p>
                </div>
                <a href="{{ route('therapist.schedule.calendar') }}"
                    class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">
                    Back to Calendar
                </a>
            </div>

            <form method="POST" action="{{ route('therapist.schedule.store') }}" id="scheduleCreateForm"
                class="space-y-6">
                @csrf

                {{-- Section 1: Schedule Details --}}
                <x-ui::card class="p-6 space-y-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Schedule Details</h2>
                            <p class="text-sm text-foreground/60">Choose date, time, and timezone.</p>
                        </div>
                        <span class="text-sm text-foreground/60">All times shown in US/Central</span>
                    </div>

                    {{-- SSA and Student Information (when SSA is provided) --}}
                    @if ($ssa)
                        <div class="bg-background/subtle rounded-lg p-4 space-y-3 border border-border">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-foreground">SSA #{{ $ssa->id }} Information
                                </h3>
                                <x-ui::badge variant="success">Active</x-ui::badge>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                                {{-- Left Side --}}
                                <div class="space-y-3">
                                    <div>
                                        <span class="text-foreground/70 block mb-1">Student:</span>
                                        <span
                                            class="font-medium text-foreground">{{ $ssa->student->name ?? 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-foreground/70 block mb-1">School:</span>
                                        <span class="font-medium text-foreground">
                                            {{ $ssa->student?->studentProfile?->school?->display_name ?? 'N/A' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="text-foreground/70 block mb-1">Start Date:</span>
                                        <span class="font-medium text-foreground">
                                            {{ $ssa->start_date->format('M d, Y') }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Right Side --}}
                                <div class="space-y-3">
                                    <div>
                                        <span class="text-foreground/70 block mb-1">Primary Service:</span>
                                        <span
                                            class="font-medium text-foreground">{{ $ssa->primaryService->name ?? 'N/A' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-foreground/70 block mb-1">Additional Services:</span>
                                        @if ($ssa->additionalServices->isNotEmpty())
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($ssa->additionalServices as $service)
                                                    <x-ui::badge variant="secondary"
                                                        class="text-xs">{{ $service->name }}</x-ui::badge>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="font-medium text-foreground/60">None</span>
                                        @endif
                                    </div>
                                    <div>
                                        <span class="text-foreground/70 block mb-1">End Date:</span>
                                        <span class="font-medium text-foreground">
                                            {{ $ssa->end_date->format('M d, Y') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Service Dropdown --}}
                        <div>
                            <x-input-label for="service_id" value="Service *" />
                            <select id="service_id" name="service_id" data-select-box class="mt-1 block w-full" required
                                data-initial-value="{{ old('service_id', $preselectedService?->id) }}">
                                <option value="">Select a service</option>
                                @foreach ($serviceOptions as $service)
                                    <option value="{{ $service['service_id'] }}" @selected(old('service_id', $preselectedService?->id) == $service['service_id'])
                                        data-is-primary="{{ $service['is_primary'] ? '1' : '0' }}">
                                        {{ $service['service_name'] }}
                                        @if ($service['is_primary'])
                                            (Primary)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="ssa_id" value="{{ $ssa->id }}">
                            <input type="hidden" name="student_ids[]" value="{{ $preselectedStudent->id }}">
                            <p class="text-xs text-foreground/60 mt-1">
                                Services available for this SSA (primary and additional).
                            </p>
                            <x-input-error :messages="$errors->get('service_id')" class="mt-2" />
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="schedule_date" value="Schedule Date *" />
                            <x-text-input id="schedule_date" name="schedule_date" type="date"
                                class="mt-1 block w-full"
                                value="{{ old('schedule_date', $selectedDate->format('Y-m-d')) }}" required />
                            <x-input-error :messages="$errors->get('schedule_date')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="start_time" value="Start Time *" />
                                <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block w-full"
                                    value="{{ old('start_time', '09:00') }}" required />
                                <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="end_time" value="End Time *" />
                                <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block w-full"
                                    value="{{ old('end_time', '10:00') }}" required />
                                <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </x-ui::card>

                {{-- Recurrence removed for first iteration --}}
                <input type="hidden" name="recurrence_type" value="none">

                {{-- Section 2: Location & Meeting Details --}}
                <x-ui::card class="p-6 space-y-6">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">Location & Meeting Details</h2>
                        <p class="text-sm text-foreground/60">Add online meeting details or location information for
                            this session.</p>
                    </div>

                    <div>
                        <x-input-label for="location_details" value="Location/Meeting Details *" />
                        <textarea name="location_details" id="location_details" rows="4"
                            class="mt-1 w-full border border-border rounded-lg px-3 py-2 text-sm"
                            placeholder="Enter meeting link (e.g., Google Meet, Zoom), location address, or other meeting details..." required>{{ old('location_details') }}</textarea>
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
                        <p class="text-sm text-foreground/60">Add optional notes for this schedule.</p>
                    </div>

                    <textarea name="notes" id="notes" rows="4"
                        class="w-full border border-border rounded-lg px-3 py-2 text-sm" placeholder="Notes (optional)">{{ old('notes') }}</textarea>
                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                </x-ui::card>

                <input type="hidden" id="recurrence_end_date" name="recurrence_end_date" value="">

                <div class="flex justify-end gap-3">
                    <a href="{{ route('therapist.schedule.calendar') }}"
                        class="inline-flex items-center px-4 py-2 border border-border text-sm font-medium rounded-lg hover:bg-background/subtle">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                        Create Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <x-slot name="scripts">
        <script type="application/json" id="student-service-mappings">
            {!! $studentServiceMappings->toJson() !!}
        </script>
        <script type="application/json" id="service-options-json">
            {!! $serviceOptions->toJson() !!}
        </script>
        @if ($ssa)
            <script type="application/json" id="ssa-services-json">
                {!! $serviceOptions->toJson() !!}
            </script>
        @endif
        <script type="application/json" id="schedule-create-state">
            {!! json_encode([
                'selected_students' => $preselectedStudent ? [$preselectedStudent->id] : [],
                'selected_service' => old('service_id', $preselectedService?->id),
            ]) !!}
        </script>
        @vite(['resources/js/common/select-box.js', 'resources/js/pages/therapist-schedule-create.js'])
    </x-slot>
</x-app-layout>
