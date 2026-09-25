<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-semibold tracking-tight text-[#0C1622]">E-Mail bestätigen</h2>
        <p class="mt-1 text-sm leading-relaxed text-[#5B6B7E]">
            {{ __('Danke für die Registrierung. Bitte bestätigen Sie Ihre E-Mail-Adresse über den Link, den wir Ihnen gesendet haben.') }}
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 text-sm font-medium text-[#2E7D5B]">
            {{ __('Ein neuer Bestätigungslink wurde an Ihre E-Mail-Adresse gesendet.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button>
                {{ __('Link erneut senden') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-sm font-medium text-[#B07C34] hover:text-[#0C1622] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#B07C34]/40">
                {{ __('Abmelden') }}
            </button>
        </form>
    </div>
</x-guest-layout>
