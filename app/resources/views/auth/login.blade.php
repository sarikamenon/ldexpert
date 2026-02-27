@php
    /** @var \Illuminate\Support\ViewErrorBag $errors */
    $errors = $errors ?? session('errors', new \Illuminate\Support\ViewErrorBag());
@endphp

<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Username -->
        <div>
            <x-input-label for="username" :value="__('Username')" />
            <p class="mt-1 text-xs text-foreground/60" id="username_help">For therapists and admins, use your email address</p>
            <x-ui::input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required
                autofocus autocomplete="username" aria-describedby="username_help" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-ui::input id="password" class="block mt-1 w-full" type="password" name="password" required
                autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <x-ui::checkbox id="remember_me" name="remember" label="{{ __('Remember me') }}" />
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-foreground/70 hover:text-foreground rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-ui::button type="submit" class="ms-3" dusk="login-button">
                {{ __('Log in') }}
            </x-ui::button>
        </div>
    </form>
</x-guest-layout>
