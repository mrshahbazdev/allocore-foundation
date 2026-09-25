<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold tracking-tight text-[#0C1622]">Geschützter Bereich</h2>
        <p class="mt-1 text-sm leading-relaxed text-[#5B6B7E]">
            {{ __('Bitte bestätigen Sie Ihr Passwort, um fortzufahren.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Passwort')" />
            <x-text-input id="password" class="block mt-1.5 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-primary-button class="w-full">
                {{ __('Bestätigen') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
