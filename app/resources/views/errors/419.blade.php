<x-guest-layout>
    <div class="text-center space-y-6">
        <div class="text-6xl font-extrabold text-indigo-600">419</div>
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-gray-900">Page expired</h1>
            <p class="text-gray-600">
                Your session has expired or the page timed out. Please refresh and try again.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <a href="{{ url('/') }}"
                class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Go to home
            </a>
            @auth
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center justify-center rounded-md border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700 bg-indigo-50 shadow-sm transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Go to dashboard
                </a>
            @else
                <a href="{{ route('login') }}"
                    class="inline-flex items-center justify-center rounded-md border border-indigo-200 px-4 py-2 text-sm font-semibold text-indigo-700 bg-indigo-50 shadow-sm transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Go to login
                </a>
            @endauth
        </div>
    </div>
</x-guest-layout>
