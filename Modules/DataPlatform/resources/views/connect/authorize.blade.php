<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight">Suite verbinden</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto px-4">
            <div class="bg-white rounded-lg border border-[#E4E9F0] p-6">
                <h3 class="text-lg font-semibold">"{{ $sourceName }}" mit Allocore Manager verbinden</h3>
                <p class="mt-2 text-sm text-[#5B6B7E]">
                    Wähle den Mandanten, dessen Daten diese Suite an den Manager senden soll.
                    Danach wirst du automatisch zurück zur Suite geleitet.
                </p>

                <form method="POST" action="{{ route('data-platform.connect.authorize.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">
                    <input type="hidden" name="state" value="{{ $state }}">
                    <input type="hidden" name="source_name" value="{{ $sourceName }}">

                    <div>
                        <label for="tenant_id" class="block text-sm font-medium">Mandant</label>
                        <select id="tenant_id" name="tenant_id" required class="mt-1 block w-full rounded-md border-[#D6DEE9] shadow-sm">
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <a href="{{ $redirectUri }}" class="text-sm text-[#5B6B7E] underline">Abbrechen</a>
                        <x-primary-button type="submit">Verbinden</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
