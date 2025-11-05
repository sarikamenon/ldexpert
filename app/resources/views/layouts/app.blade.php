<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100">
        @include('layouts.navigation')

        <!-- Page Heading -->
        <header class="bg-white border-b border-border">
            <div class="max-w-7xl mx-auto py-4 px-4 lg:px-8">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-xl text-foreground">
                        @isset($header)
                            {{ $header }}
                        @endisset
                    </h2>
                    <div class="flex-1 flex items-end justify-center">
                        <x-ui::menubar>
                            <x-ui::menubar-menu>
                                <x-slot name="trigger">Students</x-slot>
                                <x-slot name="content">
                                    <x-ui::menubar-item :href="route('therapist.students.create')">Create</x-ui::menubar-item>
                                    <x-ui::menubar-item :href="route('therapist.students.index')">List</x-ui::menubar-item>
                                </x-slot>
                            </x-ui::menubar-menu>
                            <x-ui::menubar-menu>
                                <x-slot name="trigger">Lessons</x-slot>
                                <x-slot name="content">
                                    <x-ui::menubar-item href="#">Schedule</x-ui::menubar-item>
                                    <x-ui::menubar-item href="#">Calendar</x-ui::menubar-item>
                                </x-slot>
                            </x-ui::menubar-menu>
                            <x-ui::menubar-menu>
                                <x-slot name="trigger">Invoices</x-slot>
                                <x-slot name="content">
                                    <x-ui::menubar-item href="#">Create</x-ui::menubar-item>
                                    <x-ui::menubar-item href="#">List</x-ui::menubar-item>
                                </x-slot>
                            </x-ui::menubar-menu>
                            <x-ui::menubar-menu>
                                <x-slot name="trigger">Reports</x-slot>
                                <x-slot name="content">
                                    <x-ui::menubar-item href="#">Monthly</x-ui::menubar-item>
                                    <x-ui::menubar-item href="#">Custom</x-ui::menubar-item>
                                </x-slot>
                            </x-ui::menubar-menu>
                        </x-ui::menubar>
                    </div>
                    <div class="space-x-2">
                        <x-ui::button variant="primary">+ Quick Actions</x-ui::button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>
    </div>
</body>

</html>
