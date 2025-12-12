@extends('layouts.app')

@section('content')
    <x-ui-card title="Session Log Details">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-500">Session Date</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $sessionLog->session_date?->format('Y-m-d') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Student</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $sessionLog->student?->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Service</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $sessionLog->service?->name }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Status</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $sessionLog->status?->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500">Therapist Amount</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $sessionLog->therapist_billable_amount }}</dd>
            </div>
            <div class="md:col-span-2">
                <dt class="text-sm font-medium text-gray-500">Notes</dt>
                <dd class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ $sessionLog->notes }}</dd>
            </div>
        </dl>

        <div class="mt-6 flex space-x-3">
            @can('update', $sessionLog)
                <a href="{{ route('therapist.session-logs.edit', $sessionLog) }}" class="btn btn-primary">Edit</a>
            @endcan
            @can('submit', $sessionLog)
                <form action="{{ route('therapist.session-logs.submit', $sessionLog) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success">Submit</button>
                </form>
            @endcan
        </div>
    </x-ui-card>
@endsection
