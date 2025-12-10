<nav x-data="{ open: false }" class="bg-white border-b border-border">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 gap-4">
            @php
                $user = Auth::user();
                $roleKey = $user?->role?->value ?? 'default';
                $menuConfig = config('navigation.menus');
                $rawItems = $menuConfig[$roleKey] ?? $menuConfig['default'];

                $buildMenu = function (array $items) use (&$buildMenu) {
                    return collect($items)
                        ->map(function ($item) use (&$buildMenu) {
                            $routeName = $item['route'] ?? null;
                            $hasRoute = $routeName && \Illuminate\Support\Facades\Route::has($routeName);
                            $href = $hasRoute ? route($routeName, $item['route_params'] ?? []) : $item['url'] ?? '#';
                            $children = $buildMenu($item['children'] ?? []);
                            $activePatterns = (array) ($item['active'] ?? ($routeName ?? null));
                            $activePatterns = array_values(array_filter($activePatterns));

                            return [
                                'label' => $item['label'],
                                'href' => $href,
                                'children' => $children,
                                'active_patterns' => $activePatterns,
                            ];
                        })
                        ->values()
                        ->all();
                };

                $menuItems = $buildMenu($rawItems);
            @endphp

            <div class="flex items-center gap-6">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>
            </div>

            <div class="hidden md:flex flex-1 items-center justify-center">
                @if (count($menuItems))
                    <x-ui::menubar>
                        @foreach ($menuItems as $item)
                            @if (empty($item['children']))
                                <x-ui::menubar-item :href="$item['href']">
                                    {{ $item['label'] }}
                                </x-ui::menubar-item>
                            @else
                                <x-ui::menubar-menu>
                                    <x-slot name="trigger">{{ $item['label'] }}</x-slot>
                                    <x-slot name="content">
                                        @foreach ($item['children'] as $child)
                                            <x-ui::menubar-item :href="$child['href']">
                                                {{ $child['label'] }}
                                            </x-ui::menubar-item>
                                        @endforeach
                                    </x-slot>
                                </x-ui::menubar-menu>
                            @endif
                        @endforeach
                    </x-ui::menubar>
                @endif
            </div>

            <div class="hidden md:flex items-center gap-4">

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('password.edit')">
                            {{ __('Change Password') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center md:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{ 'hidden': !open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{ 'block': open, 'hidden': !open }" class="md:hidden">
        <div class="px-4 pt-4 pb-3 space-y-3">
            <div class="space-y-1">
                @forelse ($menuItems as $item)
                    @if (empty($item['children']))
                        <x-responsive-nav-link :href="$item['href']" :active="count($item['active_patterns'])
                            ? request()->routeIs($item['active_patterns'])
                            : false">
                            {{ $item['label'] }}
                        </x-responsive-nav-link>
                    @else
                        <div>
                            <p class="px-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                {{ $item['label'] }}
                            </p>
                            <div class="mt-1 space-y-1 ps-3 border-s border-gray-200">
                                @foreach ($item['children'] as $child)
                                    <x-responsive-nav-link :href="$child['href']" :active="count($child['active_patterns'])
                                        ? request()->routeIs($child['active_patterns'])
                                        : false">
                                        {{ $child['label'] }}
                                    </x-responsive-nav-link>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @empty
                    <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-responsive-nav-link>
                @endforelse
            </div>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1 px-4">
                <x-responsive-nav-link :href="route('password.edit')">
                    {{ __('Change Password') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
