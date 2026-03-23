<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? No problem. Just enter your username and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Username -->
        <div>
            <x-input-label for="username" :value="__('Username')" />
            <p class="mt-1 text-xs text-foreground/60" id="username_help">Enter your username (for therapists and admins, this is your email address)</p>
            <x-ui::input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autofocus
                aria-describedby="username_help" />
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-ui::button type="submit">
                {{ __('Email Password Reset Link') }}
            </x-ui::button>
        </div>
    </form>
</x-guest-layout>
