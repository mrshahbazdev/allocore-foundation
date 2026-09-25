<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold tracking-tight text-[#0C1622]">Passwort zurücksetzen</h2>
        <p class="mt-1 text-sm leading-relaxed text-[#5B6B7E]">
            {{ __('Geben Sie Ihre E-Mail-Adresse ein — wir senden Ihnen einen Link zum Zurücksetzen.') }}
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('E-Mail')" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autofocus placeholder="name@unternehmen.de" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-primary-button class="w-full">
                {{ __('Reset-Link senden') }}
            </x-primary-button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-[#5B6B7E]">
        <a href="{{ route('login') }}" class="font-medium text-[#B07C34] hover:text-[#0C1622]">{{ __('Zurück zur Anmeldung') }}</a>
    </p>
</x-guest-layout>
