@extends('layouts.app')

@push('scripts')
    @vite('resources/js/pages/session-logs/index.js')
@endpush

@section('content')
    <x-ui-card title="Session Logs">
        <div class="flex justify-between items-center mb-4">
            <div>
                <a href="{{ route('therapist.session-logs.create') }}" class="btn btn-primary">Create Session Log</a>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            @foreach ($statuses as $status)
                <a href="{{ request()->fullUrlWithQuery(['status' => $status->value]) }}"
                    class="px-3 py-1 rounded-full text-sm {{ request('status') === $status->value ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                    {{ $status->label() }}
                </a>
            @endforeach
            <a href="{{ request()->url() }}"
                class="px-3 py-1 rounded-full text-sm bg-gray-100 text-gray-700">
                All
            </a>
        </div>

        <form method="GET" class="mb-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <select name="status" class="border-gray-300 rounded">
                <option value="">All Statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <input type="number" name="service_id" placeholder="Service ID" value="{{ $filters['service_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="number" name="student_id" placeholder="Student ID" value="{{ $filters['student_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="number" name="ssa_id" placeholder="SSA ID" value="{{ $filters['ssa_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                class="border-gray-300 rounded" />
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>

        <x-ui.session-log-table :columns="$columns" :rows="$rows" />

        <div class="mt-4">
            {{ $sessionLogs->withQueryString()->links() }}
        </div>
    </x-ui-card>
@endsection


