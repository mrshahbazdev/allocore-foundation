<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ALLOCORE') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=jetbrains-mono:500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.dark-mode')
    </head>
    <body class="font-sans text-[#1A2433] antialiased bg-[#F6F7F9]">
        <div class="min-h-screen lg:flex">
            {{-- Brand panel --}}
            <aside class="relative hidden lg:flex lg:w-[42%] flex-col justify-between p-12 bg-[#0B0B0F] text-white overflow-hidden">
                <div class="absolute inset-0 opacity-[0.07]" style="background-image: linear-gradient(#FACC15 1px, transparent 1px), linear-gradient(90deg, #FACC15 1px, transparent 1px); background-size: 56px 56px;"></div>
                <div class="relative inline-flex items-center">
                    <img src="{{ asset('logo-mark.png') }}" alt="ALLOCORE" class="h-12 w-auto">
                    <span class="ml-3 text-2xl font-semibold tracking-tight">ALLOCORE</span>
                </div>

                <div class="relative">
                    <h1 class="text-3xl font-semibold leading-snug tracking-tight">
                        Das zentrale digitale Betriebssystem der Unternehmensgruppe.
                    </h1>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-[#9CA3AF]">
                        Eine Daten- und Prozessplattform — Compliance ist nur das erste Modul.
                    </p>
                    <dl class="mt-10 space-y-4 text-sm">
                        <div class="flex items-center gap-3"><dt class="font-mono text-[#FACC15] w-16">R1</dt><dd class="text-[#D1D5DB]">Keine Datensilos</dd></div>
                        <div class="flex items-center gap-3"><dt class="font-mono text-[#FACC15] w-16">R3</dt><dd class="text-[#D1D5DB]">API First</dd></div>
                        <div class="flex items-center gap-3"><dt class="font-mono text-[#FACC15] w-16">R4</dt><dd class="text-[#D1D5DB]">Mandantenfähig</dd></div>
                        <div class="flex items-center gap-3"><dt class="font-mono text-[#FACC15] w-16">R5</dt><dd class="text-[#D1D5DB]">Event First</dd></div>
                    </dl>
                </div>

                <p class="relative text-xs text-[#6B7280]">DISAVO Holding GmbH · ALLOCORE GmbH</p>
            </aside>

            {{-- Form panel --}}
            <main class="flex-1 flex flex-col items-center justify-center px-6 py-12">
                <div class="lg:hidden mb-8 inline-flex items-center">
                    <img src="{{ asset('logo-mark.png') }}" alt="ALLOCORE" class="h-10 w-auto">
                    <span class="ml-2.5 text-xl font-semibold tracking-tight text-[#0B0B0F]">ALLO<span class="text-[#CA8A04]">CORE</span></span>
                </div>

                <div class="w-full max-w-md bg-white border border-[#E4E9F0] rounded-xl p-8 shadow-[0_1px_2px_rgba(12,22,34,0.06)]">
                    {{ $slot }}
                </div>
            </main>
        </div>
        <button onclick="(function(){var d=!document.documentElement.classList.contains('dark');document.documentElement.classList.toggle('dark',d);try{localStorage.setItem('af_dark',d?'1':'0')}catch(e){}})()" title="Darstellung wechseln" class="fixed bottom-5 right-5 z-50 p-2 rounded-full border border-[#D6DEE9] bg-white text-[#5B6B7E] shadow hover:text-[#CA8A04]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
        </button>
    </body>
</html>
