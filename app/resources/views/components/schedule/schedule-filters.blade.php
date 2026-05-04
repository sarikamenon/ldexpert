@props([
    'schools' => [],
    'students' => [],
    'selectedSchoolId' => null,
    'selectedStudentId' => null,
    'formAction' => null,
    'formMethod' => 'GET',
])

<x-ui::card class="p-4" {{ $attributes }}>
    <h3 class="text-xs font-semibold text-foreground/50 uppercase tracking-wide mb-4">FILTERS</h3>

    <form method="{{ $formMethod }}" action="{{ $formAction ?? request()->url() }}" id="scheduleFiltersForm"
        class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">SELECT SCHOOLS/FAMILIES</label>
            <x-ui::select name="school_id" searchable allow-clear placeholder="Select Schools/Families" data-select-box>
                <option value="">All Schools/Families</option>
                @foreach ($schools as $school)
                    <option value="{{ $school->id }}" @selected($selectedSchoolId == $school->id)>
                        {{ $school->display_name ?? $school->full_name }}
                    </option>
                @endforeach
            </x-ui::select>
        </div>

        <div>
            <label class="block text-sm font-medium text-foreground/70 mb-2">SELECT STUDENTS</label>
            <x-ui::select name="student_id" searchable allow-clear placeholder="Select Students" data-select-box>
                <option value="">All Students</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected($selectedStudentId == $student->id)>
                        {{ $student->first_name }} {{ $student->last_name }}
                    </option>
                @endforeach
            </x-ui::select>
        </div>
    </form>
</x-ui::card>
