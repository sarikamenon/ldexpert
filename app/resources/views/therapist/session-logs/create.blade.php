@extends('layouts.app')

@section('content')
    <x-ui-card title="Create Session Log">
        @include('therapist.session-logs._form', [
            'sessionLog' => null,
            'schedule' => $schedule ?? null,
            'students' => $students ?? [],
            'ssas' => $ssas ?? [],
        ])
    </x-ui-card>
@endsection

