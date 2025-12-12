@extends('layouts.app')

@section('content')
    <x-ui-card title="Edit Session Log">
        @include('therapist.session-logs._form', [
            'sessionLog' => $sessionLog,
            'schedule' => null,
            'students' => [$sessionLog->student],
            'ssas' => [$sessionLog->ssa],
        ])
    </x-ui-card>
@endsection
