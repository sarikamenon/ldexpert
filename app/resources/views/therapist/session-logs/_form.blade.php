@php
    $isEdit = isset($sessionLog);
@endphp

<form method="POST"
    action="{{ $isEdit ? route('therapist.session-logs.update', $sessionLog) : route('therapist.session-logs.store') }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    @if (isset($schedule))
        <input type="hidden" name="schedule_id" value="{{ $schedule->id }}" />
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Student</label>
            <select name="student_id" class="mt-1 block w-full border-gray-300 rounded" required>
                <option value="">Select student</option>
                @foreach ($students ?? [] as $student)
                    <option value="{{ $student->id }}" @selected(old('student_id', $sessionLog->student_id ?? '') == $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">SSA</label>
            <select name="ssa_id" class="mt-1 block w-full border-gray-300 rounded" required>
                <option value="">Select SSA</option>
                @foreach ($ssas ?? [] as $ssa)
                    <option value="{{ $ssa->id }}" @selected(old('ssa_id', $sessionLog->ssa_id ?? '') == $ssa->id)>
                        SSA #{{ $ssa->id }} - {{ $ssa->primaryService?->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Service ID</label>
            <input type="number" name="service_id"
                value="{{ old('service_id', $sessionLog->service_id ?? ($schedule->service_id ?? '')) }}"
                class="mt-1 block w-full border-gray-300 rounded" required />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Session Date</label>
            <input type="date" name="session_date"
                value="{{ old('session_date', isset($sessionLog) ? $sessionLog->session_date?->format('Y-m-d') : now()->format('Y-m-d')) }}"
                class="mt-1 block w-full border-gray-300 rounded" required />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Start Time</label>
            <input type="datetime-local" name="start_time"
                value="{{ old('start_time', isset($sessionLog) ? $sessionLog->start_time?->format('Y-m-d\TH:i') : '') }}"
                class="mt-1 block w-full border-gray-300 rounded" required />
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">End Time</label>
            <input type="datetime-local" name="end_time"
                value="{{ old('end_time', isset($sessionLog) ? $sessionLog->end_time?->format('Y-m-d\TH:i') : '') }}"
                class="mt-1 block w-full border-gray-300 rounded" required />
        </div>
    </div>

    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Notes</label>
        <textarea name="notes" rows="4" class="mt-1 block w-full border-gray-300 rounded" required>{{ old('notes', $sessionLog->notes ?? '') }}</textarea>
    </div>

    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Billable to Therapist</label>
        <input type="checkbox" name="is_billable_therapist" value="1" @checked(old('is_billable_therapist', $sessionLog->is_billable_therapist ?? true)) />
        <input type="hidden" name="is_billable_school" value="1" />
    </div>

    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Override Rates</label>
        <input type="checkbox" name="is_rate_override" value="1" @checked(old('is_rate_override', $sessionLog->is_rate_override ?? false)) />
    </div>

    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Therapist Rate Type</label>
            <select name="therapist_rate_type" class="mt-1 block w-full border-gray-300 rounded">
                <option value="">Select</option>
                <option value="H" @selected(old('therapist_rate_type', $sessionLog->therapist_rate_type?->value ?? '') === 'H')>Hourly</option>
                <option value="F" @selected(old('therapist_rate_type', $sessionLog->therapist_rate_type?->value ?? '') === 'F')>Flat</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Therapist Rate Amount</label>
            <input type="number" step="0.01" name="therapist_rate_amount"
                value="{{ old('therapist_rate_amount', $sessionLog->therapist_rate_amount ?? '') }}"
                class="mt-1 block w-full border-gray-300 rounded" />
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Therapist Billable Amount</label>
            <input type="number" step="0.01" name="therapist_billable_amount"
                value="{{ old('therapist_billable_amount', $sessionLog->therapist_billable_amount ?? '') }}"
                class="mt-1 block w-full border-gray-300 rounded" />
        </div>
    </div>

    <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">Override Reason</label>
        <textarea name="override_reason" rows="3" class="mt-1 block w-full border-gray-300 rounded">{{ old('override_reason', $sessionLog->override_reason ?? '') }}</textarea>
    </div>

    <div class="mt-6">
        <button type="submit"
            class="btn btn-primary">{{ $isEdit ? 'Update Session Log' : 'Create Session Log' }}</button>
    </div>
</form>
