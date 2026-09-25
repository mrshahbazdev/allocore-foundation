<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ALLOCORE') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=jetbrains-mono:500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-[#1A2433] antialiased bg-[#F6F7F9]">
        <div class="min-h-screen lg:flex">
            {{-- Brand panel --}}
            <aside class="relative hidden lg:flex lg:w-[42%] flex-col justify-between p-12 bg-[#0C1622] text-white overflow-hidden">
                <div class="absolute inset-0 opacity-[0.07]" style="background-image: linear-gradient(#D9A45B 1px, transparent 1px), linear-gradient(90deg, #D9A45B 1px, transparent 1px); background-size: 56px 56px;"></div>
                <div class="relative inline-flex items-center">
                    <svg viewBox="0 0 32 32" fill="none" class="h-10 w-10">
                        <rect width="32" height="32" rx="7" fill="#D9A45B"/>
                        <path d="M9 22.5 16 8l7 14.5M11.5 18.5h9" stroke="#0C1622" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="ml-3 text-2xl font-semibold tracking-tight">ALLOCORE</span>
                </div>

                <div class="relative">
                    <h1 class="text-3xl font-semibold leading-snug tracking-tight">
                        Das zentrale digitale Betriebssystem der Unternehmensgruppe.
                    </h1>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-[#9FB0C6]">
                        Eine Daten- und Prozessplattform — Compliance ist nur das erste Modul.
                    </p>
                    <dl class="mt-10 space-y-4 text-sm">
                        <div class="flex items-center gap-3"><dt class="font-mono text-[#D9A45B] w-16">R1</dt><dd class="text-[#C7D3E2]">Keine Datensilos</dd></div>
                        <div class="flex items-center gap-3"><dt class="font-mono text-[#D9A45B] w-16">R3</dt><dd class="text-[#C7D3E2]">API First</dd></div>
                        <div class="flex items-center gap-3"><dt class="font-mono text-[#D9A45B] w-16">R4</dt><dd class="text-[#C7D3E2]">Mandantenfähig</dd></div>
                        <div class="flex items-center gap-3"><dt class="font-mono text-[#D9A45B] w-16">R5</dt><dd class="text-[#C7D3E2]">Event First</dd></div>
                    </dl>
                </div>

                <p class="relative text-xs text-[#7C8EA8]">DISAVO Holding GmbH · ALLOCORE GmbH</p>
            </aside>

            {{-- Form panel --}}
            <main class="flex-1 flex flex-col items-center justify-center px-6 py-12">
                <div class="lg:hidden mb-8 inline-flex items-center">
                    <svg viewBox="0 0 32 32" fill="none" class="h-9 w-9">
                        <rect width="32" height="32" rx="7" fill="#0C1622"/>
                        <path d="M9 22.5 16 8l7 14.5M11.5 18.5h9" stroke="#D9A45B" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span class="ml-2.5 text-xl font-semibold tracking-tight text-[#0C1622]">ALLO<span class="text-[#B07C34]">CORE</span></span>
                </div>

                <div class="w-full max-w-md bg-white border border-[#E4E9F0] rounded-xl p-8 shadow-[0_1px_2px_rgba(12,22,34,0.06)]">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
