<x-app-layout>
    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 lg:px-8 space-y-6">
            <div>
                <p class="text-sm text-foreground/60">Therapist · QGlob Requests</p>
                <h1 class="text-2xl font-semibold text-foreground">New QGlob Request</h1>
            </div>

            @if ($errors->any())
                <x-ui::alert variant="danger">
                    <ul class="list-disc pl-5 text-sm space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui::alert>
            @endif

            <x-ui::card class="p-6">
                <form method="post" action="{{ route('therapist.qglob-requests.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="student_id" value="Student *" />
                        <p class="mt-1 text-xs text-foreground/60" id="student_id_help">
                            Students on your caseload with an active evaluation SSA only.
                        </p>
                        <x-ui::select id="student_id" name="student_id" searchable required
                            class="mt-1 block w-full" aria-describedby="student_id_help">
                            <option value="">Select a student</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                                    {{ $student->name }}
                                </option>
                            @endforeach
                        </x-ui::select>
                        <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="requested_date" value="Requested date *" />
                        <p class="mt-1 text-xs text-foreground/60" id="requested_date_help">
                            The calendar date you need Q-Global access.
                        </p>
                        <x-ui::input id="requested_date" type="date" name="requested_date" required
                            value="{{ old('requested_date') }}" class="mt-1 block w-full max-w-xs"
                            aria-describedby="requested_date_help" />
                        <x-input-error :messages="$errors->get('requested_date')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="requested_time" value="Requested time *" />
                        <p class="mt-1 text-xs text-foreground/60" id="requested_time_help">
                            Start time for the evaluation session (24-hour picker).
                        </p>
                        <x-ui::input id="requested_time" type="time" name="requested_time" required
                            value="{{ old('requested_time') }}" class="mt-1 block w-full max-w-xs"
                            aria-describedby="requested_time_help" />
                        <x-input-error :messages="$errors->get('requested_time')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="note" value="Note (optional)" />
                        <p class="mt-1 text-xs text-foreground/60" id="note_help">
                            Optional context for the admin (assessment type, urgency, etc.).
                        </p>
                        <textarea id="note" name="note" rows="4"
                            class="mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-ring text-sm text-foreground"
                            aria-describedby="note_help">{{ old('note') }}</textarea>
                        <x-input-error :messages="$errors->get('note')" class="mt-2" />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <x-ui::button type="submit"
                            class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                            Submit request
                        </x-ui::button>
                        <a href="{{ route('therapist.qglob-requests.index') }}">
                            <x-ui::button type="button" variant="secondary"
                                class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                Cancel
                            </x-ui::button>
                        </a>
                    </div>
                </form>
            </x-ui::card>
        </div>
    </div>
</x-app-layout>
