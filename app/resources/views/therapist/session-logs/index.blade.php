@extends('layouts.app')

@section('content')
    <x-ui-card title="Session Logs">
        <div class="flex justify-between items-center mb-4">
            <div>
                <a href="{{ route('therapist.session-logs.create') }}" class="btn btn-primary">Create Session Log</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table id="session-logs-table" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Therapist Amt</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($sessionLogs as $log)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->session_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->student?->name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->service?->name }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->status?->label() }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900">{{ $log->therapist_billable_amount }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900 space-x-2">
                                <a href="{{ route('therapist.session-logs.show', $log) }}"
                                    class="text-indigo-600 hover:text-indigo-900">View</a>
                                @if ($log->canEdit())
                                    <a href="{{ route('therapist.session-logs.edit', $log) }}"
                                        class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                    <form action="{{ route('therapist.session-logs.submit', $log) }}" method="POST"
                                        class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-800">Submit</button>
                                    </form>
                                @endif
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
    </x-ui-card>
@endsection
