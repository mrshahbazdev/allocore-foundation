<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold tracking-tight text-[#0C1622]">Anmelden</h2>
        <p class="mt-1 text-sm text-[#5B6B7E]">Zugang zur Plattform der Unternehmensgruppe.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('E-Mail')" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="name@unternehmen.de" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Passwort')" />
            <x-text-input id="password" class="block mt-1.5 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-[#D6DEE9] text-[#B07C34] shadow-sm focus:ring-[#B07C34]/40" name="remember">
                <span class="ms-2 text-sm text-[#5B6B7E]">{{ __('Angemeldet bleiben') }}</span>
            </label>
            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-[#B07C34] hover:text-[#0C1622] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#B07C34]/40" href="{{ route('password.request') }}">
                    {{ __('Passwort vergessen?') }}
                </a>
            @endif
        </div>

        <div>
            <x-primary-button class="w-full">
                {{ __('Anmelden') }}
            </x-primary-button>
        </div>
    </form>

    @if (Route::has('register'))
        <p class="mt-6 text-center text-sm text-[#5B6B7E]">
            {{ __('Noch kein Zugang?') }}
            <a href="{{ route('register') }}" class="font-medium text-[#B07C34] hover:text-[#0C1622]">{{ __('Konto erstellen') }}</a>
        </p>
    @endif
</x-guest-layout>
