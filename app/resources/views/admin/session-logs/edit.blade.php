@extends('layouts.app')

@section('content')
    <x-ui-card title="Override Session Log Rates">
        <form method="POST" action="{{ route('admin.session-logs.update', $sessionLog) }}">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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

            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">School Rate Type</label>
                    <select name="school_rate_type" class="mt-1 block w-full border-gray-300 rounded">
                        <option value="">Select</option>
                        <option value="H" @selected(old('school_rate_type', $sessionLog->school_rate_type?->value ?? '') === 'H')>Hourly</option>
                        <option value="F" @selected(old('school_rate_type', $sessionLog->school_rate_type?->value ?? '') === 'F')>Flat</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">School Rate Amount</label>
                    <input type="number" step="0.01" name="school_rate_amount"
                        value="{{ old('school_rate_amount', $sessionLog->school_rate_amount ?? '') }}"
                        class="mt-1 block w-full border-gray-300 rounded" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">School Invoice Amount</label>
                    <input type="number" step="0.01" name="school_invoice_amount"
                        value="{{ old('school_invoice_amount', $sessionLog->school_invoice_amount ?? '') }}"
                        class="mt-1 block w-full border-gray-300 rounded" />
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700">Override Rates</label>
                <input type="checkbox" name="is_rate_override" value="1" @checked(old('is_rate_override', $sessionLog->is_rate_override ?? false)) />
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700">Override Reason</label>
                <textarea name="override_reason" rows="3" class="mt-1 block w-full border-gray-300 rounded">{{ old('override_reason', $sessionLog->override_reason ?? '') }}</textarea>
            </div>

            <div class="mt-6 flex space-x-2">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('admin.session-logs.show', $sessionLog) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </x-ui-card>
@endsection
