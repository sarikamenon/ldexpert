@extends('layouts.app')

@section('content')
    <x-ui-card title="Session Logs">
        <form method="GET" class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
            <input type="text" name="school_id" placeholder="School ID" value="{{ $filters['school_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="text" name="student_id" placeholder="Student ID" value="{{ $filters['student_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="text" name="therapist_id" placeholder="Therapist ID"
                value="{{ $filters['therapist_id'] ?? '' }}" class="border-gray-300 rounded" />
            <input type="text" name="service_id" placeholder="Service ID" value="{{ $filters['service_id'] ?? '' }}"
                class="border-gray-300 rounded" />
            <select name="status" class="border-gray-300 rounded">
                <option value="">Status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                class="border-gray-300 rounded" />
            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="border-gray-300 rounded" />
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">School</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Therapist</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($sessionLogs as $log)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->session_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->school?->display_name ?? '-' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->student?->name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->therapist?->name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->service?->name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->status?->label() }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">
                                <a href="{{ route('admin.session-logs.show', $log) }}"
                                    class="text-indigo-600 hover:text-indigo-900">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-4 text-center text-sm text-gray-500">No session logs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $sessionLogs->withQueryString()->links() }}
        </div>
    </x-ui-card>
@endsection
