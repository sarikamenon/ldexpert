@extends('layouts.app')

@push('scripts')
    @vite('resources/js/pages/session-logs/index.js')
@endpush

@section('content')
    <x-ui-card title="Session Logs">
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

        <form method="GET" class="mb-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <input type="number" name="school_id" placeholder="School ID" value="{{ $filters['school_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="number" name="therapist_id" placeholder="Therapist ID" value="{{ $filters['therapist_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="number" name="service_id" placeholder="Service ID" value="{{ $filters['service_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="number" name="ssa_id" placeholder="SSA ID" value="{{ $filters['ssa_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="number" name="per_page" value="{{ $filters['per_page'] ?? 15 }}" min="5" max="100"
                class="border-gray-300 rounded" />
            <button type="submit" class="btn btn-primary">Apply</button>
        </form>

        <x-ui.session-log-table :columns="$columns" :rows="$rows" />

        <div class="mt-4">
            {{ $sessionLogs->withQueryString()->links() }}
        </div>
    </x-ui-card>
@endsection


