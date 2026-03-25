<x-admin.layouts.app>
    <x-page-title title="New QGlob Request" description="Create a request on behalf of a therapist." />

    @if ($errors->any())
        <x-ui::alert variant="danger" class="mb-4">
            <ul class="list-disc pl-5 text-sm space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui::alert>
    @endif

    <x-ui::card class="p-6 max-w-3xl">
        <form method="post" action="{{ route('admin.qglob-requests.store') }}" class="space-y-6" id="adminQglobCreateForm">
            @csrf

            <div>
                <x-input-label for="therapist_id" value="Therapist *" />
                <p class="mt-1 text-xs text-foreground/60" id="therapist_id_help">
                    The therapist this Q-Global access is for.
                </p>
                <x-ui::select id="therapist_id" name="therapist_id" searchable required
                    class="mt-1 block w-full" aria-describedby="therapist_id_help">
                    <option value="">Select a therapist</option>
                    @foreach ($therapists as $therapist)
                        <option value="{{ $therapist->id }}" @selected(old('therapist_id') == $therapist->id)>
                            {{ $therapist->name }}
                        </option>
                    @endforeach
                </x-ui::select>
                <x-input-error :messages="$errors->get('therapist_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="student_id" value="Student *" />
                <p class="mt-1 text-xs text-foreground/60" id="student_id_help">
                    Eligible students for the selected therapist (evaluation SSA and active caseload).
                </p>
                <select id="student_id" name="student_id" required
                    class="mt-1 block w-full rounded-md border-border shadow-sm text-sm text-foreground focus:border-primary focus:ring-ring disabled:opacity-50"
                    aria-describedby="student_id_help" @disabled(! old('therapist_id'))>
                    <option value="">{{ old('therapist_id') ? 'Loading…' : 'Select a therapist first' }}</option>
                </select>
                <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="requested_date" value="Requested date *" />
                <p class="mt-1 text-xs text-foreground/60" id="requested_date_help">
                    The calendar date Q-Global access is needed.
                </p>
                <x-ui::input id="requested_date" type="date" name="requested_date" required
                    value="{{ old('requested_date') }}" class="mt-1 block w-full max-w-xs"
                    aria-describedby="requested_date_help" />
                <x-input-error :messages="$errors->get('requested_date')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="requested_time" value="Requested time *" />
                <p class="mt-1 text-xs text-foreground/60" id="requested_time_help">
                    Start time for the evaluation session.
                </p>
                <x-ui::input id="requested_time" type="time" name="requested_time" required
                    value="{{ old('requested_time') }}" class="mt-1 block w-full max-w-xs"
                    aria-describedby="requested_time_help" />
                <x-input-error :messages="$errors->get('requested_time')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="note" value="Note (optional)" />
                <p class="mt-1 text-xs text-foreground/60" id="note_help">
                    Optional context for reviewers.
                </p>
                <textarea id="note" name="note" rows="4"
                    class="mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-ring text-sm text-foreground"
                    aria-describedby="note_help">{{ old('note') }}</textarea>
                <x-input-error :messages="$errors->get('note')" class="mt-2" />
            </div>

            <div class="flex flex-wrap gap-3">
                <x-ui::button type="submit"
                    class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Create request
                </x-ui::button>
                <a href="{{ route('admin.qglob-requests.index') }}">
                    <x-ui::button type="button" variant="secondary"
                        class="focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        Cancel
                    </x-ui::button>
                </a>
            </div>
        </form>
    </x-ui::card>

    <script type="application/json" id="admin-qglob-create-config">
        {!! json_encode([
            'eligibleStudentsUrl' => route('admin.qglob-requests.eligible-students'),
            'oldTherapistId' => old('therapist_id'),
            'oldStudentId' => old('student_id'),
        ]) !!}
    </script>

    <x-slot name="scripts">
        @vite(['resources/js/pages/admin-qglob-requests-create.js'])
    </x-slot>
</x-admin.layouts.app>
