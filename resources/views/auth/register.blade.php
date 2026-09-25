<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold tracking-tight text-[#0C1622]">Konto erstellen</h2>
        <p class="mt-1 text-sm text-[#5B6B7E]">Neuer Zugang zur Plattform.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1.5 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('E-Mail')" />
            <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="name@unternehmen.de" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Passwort')" />
            <x-text-input id="password" class="block mt-1.5 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Passwort bestätigen')" />
            <x-text-input id="password_confirmation" class="block mt-1.5 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div>
            <x-primary-button class="w-full">
                {{ __('Konto erstellen') }}
            </x-primary-button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm text-[#5B6B7E]">
        {{ __('Bereits registriert?') }}
        <a href="{{ route('login') }}" class="font-medium text-[#B07C34] hover:text-[#0C1622]">{{ __('Anmelden') }}</a>
    </p>
</x-guest-layout>
