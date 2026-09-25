<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ALLOCORE — {{ $section }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=jetbrains-mono:500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.dark-mode')
</head>
<body class="font-sans antialiased bg-[#F6F7F9] text-[#1A2433]">
<div class="min-h-screen flex flex-col lg:flex-row" x-data="workspace(@js($section))" x-cloak
     @keydown.escape.window="detail = null; closeCreate(); showCreate = false; navOpen = false; palette = false; colPicker = false; showImport = false; confirmDel = false"
     @keydown.arrowright.window="detail && navDetail(1)"
     @keydown.arrowleft.window="detail && navDetail(-1)"
     @keydown.window="kbd($event)"
     @beforeprint.window="limit = 100000">

    {{-- Mobile top bar --}}
    <div class="lg:hidden flex items-center justify-between px-4 h-14 bg-[#0B0B0F] text-white sticky top-0 z-30 shrink-0 print:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <img src="{{ asset('logo-mark.png') }}" alt="ALLOCORE" class="h-7 w-auto">
            <span class="font-semibold tracking-tight">ALLO<span class="text-[#FACC15]">CORE</span></span>
        </a>
        <span class="text-[13px] text-[#9CA3AF] truncate"><span class="text-[#FACC15] mr-1.5" x-text="icons[section] || ''"></span><span x-text="title()"></span></span>
        <button @click="navOpen = !navOpen" class="p-2 -mr-2 text-[#9CA3AF] hover:text-white" title="Menü">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
    </div>

    {{-- Backdrop (mobile) --}}
    <div x-show="navOpen" @click="navOpen = false" class="lg:hidden fixed inset-0 bg-black/50 z-30" x-transition.opacity></div>

    {{-- Sidebar --}}
    <aside class="bg-[#0B0B0F] text-white w-64 flex flex-col fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-200 lg:translate-x-0 lg:static lg:min-h-screen lg:sticky lg:top-0 lg:shrink-0 print:hidden"
           :class="navOpen ? 'translate-x-0' : '-translate-x-full'">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-5 h-16 border-b border-[#1A1A1F]">
            <img src="{{ asset('logo-mark.png') }}" alt="ALLOCORE" class="h-9 w-auto">
            <span class="font-semibold tracking-tight text-[17px]">ALLO<span class="text-[#FACC15]">CORE</span></span>
        </a>

        {{-- Tenant picker --}}
        <div class="px-4 py-4 border-b border-[#1A1A1F]">
            <label class="block text-[10px] font-medium tracking-wide text-[#9CA3AF] mb-1.5">MANDANT</label>
            <select x-model="tenant" @change="loadSection(); loadNavBadges()"
                    class="w-full rounded-lg bg-[#1A1A1F] border-[#2A2A31] text-white text-sm py-2 focus:border-[#FACC15] focus:ring-[#FACC15]/30">
                <option value="">— wählen —</option>
                <template x-for="t in sortedTenants()" :key="t.id">
                    <option :value="t.id" x-text="t.name"></option>
                </template>
            </select>
            <button @click="createTenant()" class="mt-2 w-full text-left text-[11px] text-[#9CA3AF] hover:text-[#FACC15]">+ Neuer Mandant</button>
        </div>

        <nav class="flex-1 overflow-y-auto py-3 text-[13px]">
            <div x-show="pins.length" class="mb-1" style="display:none">
                <div class="px-5 pt-3 pb-1.5 text-[10px] font-semibold tracking-widest text-[#6B7280]">FAVORITEN</div>
                <template x-for="pk in pins" :key="pk">
                    <a :href="'/app/' + pk + (tenant ? '?tenant='+tenant : '')"
                       class="flex items-center gap-3 px-5 py-2 transition"
                       :class="section === pk ? 'text-white bg-[#1A1A1F] border-r-2 border-[#FACC15]' : 'text-[#9CA3AF] hover:text-white hover:bg-[#141419]'">
                        <span class="text-[#CA8A04] text-xs">★</span>
                        <span x-text="(groups.flatMap(g => g.items).find(i => i.key === pk) || {label: pk}).label"></span>
                    </a>
                </template>
            </div>
            <div x-show="recent.length" class="px-5 pb-3">
                <div class="text-[10px] font-semibold tracking-widest text-[#6B7280] pb-1.5">ZULETZT</div>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="r in recent" :key="r">
                        <a :href="'/app/' + r + (tenant ? '?tenant='+tenant : '')" class="px-2 py-1 rounded-full bg-[#1A1A1F] text-[11px] text-[#9CA3AF] hover:text-[#FACC15] transition"><span class="opacity-70 mr-1" x-text="icons[r] || '·'"></span><span x-text="sectionLabel(r)"></span></a>
                    </template>
                </div>
            </div>
            <div class="flex justify-end pr-4 -mb-1">
                <button @click="toggleAllGroups()" class="text-[10px] text-[#4B5563] hover:text-[#9CA3AF] transition" :title="allCollapsed() ? 'Alle Gruppen aufklappen' : 'Alle Gruppen einklappen'" x-text="allCollapsed() ? '▸ alle auf' : '▾ alle zu'"></button>
            </div>
            <template x-for="group in groups" :key="group.label">
                <div class="mb-1">
                    <button @click="collapsed[group.label] = !collapsed[group.label]"
                            class="w-full flex items-center justify-between px-5 pt-4 pb-1.5 text-[10px] font-semibold tracking-widest text-[#6B7280] hover:text-[#9CA3AF] transition">
                        <span><span x-text="group.label"></span><span class="ml-1.5 font-normal text-[#4B5563]" x-text="'· ' + group.items.length"></span></span>
                        <span class="flex items-center gap-1.5">
                            <span x-show="collapsed[group.label] && group.items.some(i => navBadges[i.key] > 0)" class="w-1.5 h-1.5 rounded-full bg-[#A6362E]"></span>
                            <svg class="w-2.5 h-2.5 transition-transform" :class="collapsed[group.label] && !group.items.some(i => i.key === section) ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                    <template x-for="item in group.items" :key="item.key">
                        <div x-show="!collapsed[group.label] || group.items.some(i => i.key === section)"
                           class="flex items-center gap-1 transition pr-2"
                           :class="section === item.key
                               ? 'text-white bg-[#1A1A1F] border-r-2 border-[#FACC15]'
                               : 'text-[#9CA3AF] hover:text-white hover:bg-[#141419]'">
                            <a :href="'/app/' + item.key + (tenant ? '?tenant='+tenant : '')"
                               class="flex-1 px-5 py-2 flex items-center gap-2.5"><span class="w-4 text-center text-[11px] opacity-70" x-text="icons[item.key] || '·'"></span><span x-text="item.label"></span></a>
                            <a x-show="navBadges[item.key] > 0" x-text="navBadges[item.key]"
                               :href="'/app/' + item.key + '?overdue=1' + (tenant ? '&tenant='+tenant : '')"
                               title="Überfällige Einträge anzeigen"
                               class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-[#A6362E] text-white min-w-[1.1rem] text-center hover:bg-[#8C2B24]"></a>
                        </div>
                    </template>
                </div>
            </template>
        </nav>

        <div class="px-5 py-4 border-t border-[#1A1A1F] flex items-center justify-between">
            <div class="min-w-0">
                <div class="text-sm font-medium truncate">{{ $user->name }}</div>
                <div class="text-[11px] text-[#6B7280] truncate">{{ $user->email }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button title="Abmelden" class="text-[#9CA3AF] hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H9"/></svg>
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <main class="flex-1 min-w-0">
        <header class="bg-white border-b border-[#E4E9F0] px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="font-semibold text-lg tracking-tight text-[#0B0B0F] flex items-center gap-2">
                    <span class="text-[#CA8A04] text-base" x-text="icons[section] || ''"></span>
                    <span x-text="title()"></span>
                    <button @click="togglePin(section)" :title="pins.includes(section) ? 'Aus Favoriten entfernen' : 'Zu Favoriten hinzufügen'"
                            class="text-sm transition" :class="pins.includes(section) ? 'text-[#CA8A04]' : 'text-[#D6DEE9] hover:text-[#CA8A04]'">★</button>
                </h1>
                <div x-show="groupOf(section)" class="text-[10px] font-semibold tracking-widest text-[#9CA3AF] mb-0.5">
                    <span x-text="groupOf(section)"></span><span class="mx-1.5">›</span><span x-text="title()"></span>
                </div>
                <p class="text-xs text-[#5B6B7E]" x-text="subtitle()"></p>
            </div>
            <div class="text-right">
                <span x-show="loading" class="text-xs text-[#9CA3AF]">Lädt…</span>
                <span x-show="!loading && lastLoad" class="text-[11px] text-[#9CA3AF]" x-text="lastLoad ? 'Stand ' + lastLoad.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'}) : ''"></span>
            </div>
            <div class="flex items-center gap-3">
                <button @click="toggleDark()" :title="dark ? 'Helle Darstellung (t)' : 'Dunkle Darstellung (t)'" class="text-[#9CA3AF] hover:text-[#CA8A04] transition">
                    <svg x-show="!dark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg x-show="dark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>
                <button x-show="tenant && !['dashboard','executive'].includes(section)" @click="loadSection(true)" title="Aktualisieren (r)"
                        class="transition" :class="loading ? 'text-[#CA8A04]' : 'text-[#9CA3AF] hover:text-[#CA8A04]'">
                    <svg class="w-4 h-4" :class="loading && 'animate-spin'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M5.5 9A8 8 0 0119 7.5M18.5 15A8 8 0 015 16.5"/></svg>
                </button>
            </div>
        </header>

        <div class="p-6 space-y-5">
            <div x-show="offline" class="bg-[#FFFBEB] border border-[#CA8A04]/40 rounded-xl px-4 py-2.5 text-xs text-[#CA8A04] flex items-center gap-2" x-cloak>
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.36 6.64a9 9 0 11-12.73 0M12 2v9"/></svg>
                Keine Internetverbindung — Daten werden nicht aktualisiert.
            </div>
            <div x-show="error" class="bg-white border border-[#A6362E]/40 rounded-xl px-4 py-3 text-sm text-[#A6362E] flex items-start justify-between gap-3">
                <span x-text="error"></span>
                <button @click="error = ''" class="shrink-0 text-[#A6362E]/60 hover:text-[#A6362E] leading-none" aria-label="Fehler schließen">&times;</button>
            </div>

            {{-- Dashboard --}}
            <template x-if="section === 'dashboard'">
                <div class="space-y-5">
                    <div x-show="insights.length" class="space-y-2">
                        <template x-for="i in insights" :key="i.code">
                            <a :href="insightSection(i.code) ? '/app/' + insightSection(i.code) + '?tenant=' + tenant : '#'"
                               class="flex items-start gap-3 rounded-lg border bg-white px-4 py-3 text-sm transition"
                               :class="{'border-[#A6362E]/40': i.severity==='critical','border-[#CA8A04]/50': i.severity==='warning','border-[#D6DEE9]': i.severity==='info','hover:shadow-sm': insightSection(i.code)}">
                                <span class="mt-0.5 inline-block h-2 w-2 rounded-full shrink-0"
                                      :class="{'bg-[#A6362E]': i.severity==='critical','bg-[#CA8A04]': i.severity==='warning','bg-[#5B6B7E]': i.severity==='info'}"></span>
                                <span x-text="i.message"></span>
                                <svg x-show="insightSection(i.code)" class="ml-auto h-4 w-4 shrink-0 text-[#9CA3AF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </template>
                    </div>
                    <div x-show="tenant && metrics && !insights.length" class="flex items-center gap-3 rounded-lg border border-[#2E7D5B]/30 bg-[#2E7D5B]/5 px-4 py-3 text-sm text-[#2E7D5B]">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Alles im grünen Bereich — keine offenen Hinweise.
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                        <template x-for="m in kpiCards" :key="m.key">
                            <a :href="m.to ? '/app/' + m.to + (tenant ? '?tenant='+tenant : '') : '#'"
                               class="bg-white border border-[#E4E9F0] rounded-xl px-5 py-4 block transition"
                               :class="m.to ? 'hover:border-[#CA8A04]/60 hover:shadow-sm cursor-pointer' : 'cursor-default'">
                                <div class="text-[11px] font-medium text-[#5B6B7E]" x-text="m.label"></div>
                                <div class="mt-1.5 font-mono text-2xl font-semibold tracking-tight text-[#0B0B0F]" x-text="metric(m.key)"></div>
                                <div class="mt-0.5 text-[11px] font-mono" :class="trend(m.key).direction === 'up' ? 'text-[#2E7D5B]' : (trend(m.key).direction === 'down' ? 'text-[#A6362E]' : 'text-[#9CA3AF]')" x-text="trend(m.key).delta === null ? '' : (trend(m.key).direction === 'up' ? '▲ +' : (trend(m.key).direction === 'down' ? '▼ ' : '')) + (trend(m.key).delta ?? '')"></div>
                                <div class="mt-2 flex items-end justify-between gap-2">
                                    <div class="h-0.5 w-8 rounded-full bg-[#FACC15] mb-1"></div>
                                    <svg x-show="(spark[m.key] || []).length > 1" :title="'60-Tage-Verlauf: ' + spark[m.key][0] + ' → ' + spark[m.key][spark[m.key].length - 1]" viewBox="0 0 96 24" preserveAspectRatio="none" class="h-6 w-24"
                                         :class="trend(m.key).direction === 'up' ? 'text-[#2E7D5B]' : (trend(m.key).direction === 'down' ? 'text-[#A6362E]' : 'text-[#CA8A04]')">
                                        <path :d="sparkPath(m.key)" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </a>
                        </template>
                    </div>
                    <div x-show="openTasks.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <a :href="'/app/tasks?tenant=' + tenant" class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E] flex items-center justify-between hover:text-[#CA8A04]">
                            Offene Aufgaben
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                        <div class="divide-y divide-[#F0F3F7]">
                            <template x-for="t in openTasks" :key="t.id">
                                <a :href="'/app/tasks?tenant=' + tenant + '&open=' + t.id" class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-[#FAFBFC]">
                                    <span class="text-sm text-[#1A2433] truncate" x-text="t.title"></span>
                                    <span class="text-[11px] font-mono shrink-0" :class="t.due_at && new Date(t.due_at) < new Date() ? 'text-[#A6362E]' : 'text-[#9CA3AF]'" x-text="t.due_at ? new Date(t.due_at).toLocaleDateString('de-DE') : ''"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                    <div x-show="upcoming.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <a :href="'/app/deadlines?tenant=' + tenant" class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E] flex items-center justify-between hover:text-[#CA8A04]">
                            Nächste Fristen
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                        <div class="divide-y divide-[#F0F3F7]">
                            <template x-for="d in upcoming" :key="d.id">
                                <a :href="'/app/deadlines?tenant=' + tenant + '&open=' + d.id" class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-[#FAFBFC]">
                                    <span class="text-sm text-[#1A2433] truncate" x-text="d.title"></span>
                                    <span class="text-[11px] font-mono shrink-0" :class="new Date(d.due_at) < new Date() ? 'text-[#A6362E]' : 'text-[#9CA3AF]'" x-text="new Date(d.due_at).toLocaleDateString('de-DE')"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                    <div x-show="overdueSections().length" class="bg-white border border-[#A6362E]/30 rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-[#A6362E]/20 text-xs font-medium text-[#A6362E] flex items-center justify-between">
                            Überfällige Einträge
                            <span class="font-mono" x-text="overdueSections().reduce((s, x) => s + x.count, 0)"></span>
                        </div>
                        <div class="divide-y divide-[#F0F3F7]">
                            <template x-for="o in overdueSections()" :key="o.key">
                                <a :href="'/app/' + o.key + '?tenant=' + tenant + '&overdue=1'" class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-[#FAFBFC] transition">
                                    <span class="text-sm text-[#1A2433] truncate" x-text="o.label"></span>
                                    <span class="text-[11px] font-mono text-[#A6362E] shrink-0" x-text="o.count + ' überfällig'"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                    <div x-show="events.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E]">Letzte Ereignisse</div>
                        <div class="divide-y divide-[#F0F3F7] max-h-64 overflow-y-auto">
                            <template x-for="(e, i) in events" :key="i">
                                <a x-show="eventLink(e)" :href="eventLink(e)" class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-[#FAFBFC] transition">
                                    <span class="text-sm text-[#1A2433] truncate"><span class="text-[11px] font-semibold tracking-wide text-[#CA8A04] uppercase" x-text="eventGroup(e.event_type)"></span> <span x-text="eventLabel(e.event_type)"></span><span x-show="e.event_properties && e.event_properties.subject && e.event_properties.subject.title" class="text-[#5B6B7E]" x-text="' · ' + e.event_properties.subject.title"></span></span>
                                    <span class="text-[11px] text-[#9CA3AF] font-mono shrink-0" x-text="ago(e.created_at)"></span>
                                </a>
                                <div x-show="!eventLink(e)" class="px-5 py-2.5 flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#1A2433] truncate"><span class="text-[11px] font-semibold tracking-wide text-[#CA8A04] uppercase" x-text="eventGroup(e.event_type)"></span> <span x-text="eventLabel(e.event_type)"></span></span>
                                    <span class="text-[11px] text-[#9CA3AF] font-mono shrink-0" x-text="ago(e.created_at)"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Executive (Holding rollup) --}}
            <template x-if="section === 'executive'">
                <div class="space-y-5">
                    <div x-show="!exec" class="bg-white border border-[#E4E9F0] rounded-xl px-6 py-12 text-center text-sm text-[#5B6B7E]">
                        Wählen Sie links einen Mandanten, um die Holding-Übersicht zu laden.
                    </div>
                    <template x-if="exec">
                        <div class="space-y-5">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-[#5B6B7E]" x-text="exec.totals.tenants + ' Mandanten im Konzern'"></div>
                                <button @click="createExecReport()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F]">+ Report erstellen</button>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
                                <template x-for="(v, k) in exec.totals" :key="k">
                                    <div x-show="k !== 'tenants'" class="bg-white border border-[#E4E9F0] rounded-xl px-5 py-4">
                                        <div class="text-[11px] font-medium text-[#5B6B7E]" x-text="execLabel(k)"></div>
                                        <div class="mt-1.5 font-mono text-2xl font-semibold tracking-tight text-[#0B0B0F]" x-text="v"></div>
                                        <div class="mt-2 h-0.5 w-8 rounded-full bg-[#FACC15]"></div>
                                    </div>
                                </template>
                            </div>
                            <div class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead><tr class="border-b border-[#E4E9F0] bg-[#FAFBFC] text-left">
                                        <template x-for="h in ['Mandant','Unternehmen','Personen','Offene Aufgaben','Offene Fristen','Hohe Risiken','Data Lake','Compliance %']" :key="h">
                                            <th class="px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E]" x-text="h"></th>
                                        </template>
                                    </tr></thead>
                                    <tbody>
                                        <template x-for="t in exec.tenants" :key="t.id">
                                            <tr class="border-b border-[#F0F3F7] last:border-b-0">
                                                <td class="px-5 py-3 font-medium text-[#0B0B0F]" x-text="t.name"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.companies"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.persons"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.tasks_open"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.deadlines_open"></td>
                                                <td class="px-5 py-3 font-mono" :class="t.high_risks > 0 ? 'text-[#A6362E] font-semibold' : ''" x-text="t.high_risks"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.data_objects"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.compliance_rate"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div x-show="execReports.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                                <div class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E]">Reports</div>
                                <div class="divide-y divide-[#F0F3F7]">
                                    <template x-for="r in execReports" :key="r.id">
                                        <div class="px-5 py-2.5 flex items-center justify-between gap-4">
                                            <span class="text-sm text-[#1A2433]" x-text="r.title"></span>
                                            <span class="text-[11px] text-[#9CA3AF] font-mono" x-text="fmt(r.created_at)"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Generic list --}}
            <template x-if="section !== 'dashboard' && section !== 'executive'">
                <div class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                    <div x-show="!rows" class="px-6 py-12 text-center text-sm text-[#5B6B7E]">
                        Wählen Sie links einen Mandanten, um Daten zu laden.
                    </div>
                    <div x-show="rows !== null" class="flex items-center justify-between gap-3 px-5 py-3 border-b border-[#E4E9F0] print:hidden">
                        <span class="text-xs text-[#5B6B7E] shrink-0 flex items-center gap-2">
                            <span x-text="filtered().length + ' / ' + (rows ? rows.length : 0) + ' ' + eintrag(rows ? rows.length : 0)"></span>
                            <span x-show="rows && rows.some(r => overdue(r))" class="text-[#A6362E]" x-text="'· ' + rows.filter(r => overdue(r)).length + ' überfällig'"></span>
                            <span x-show="rows && rows.some(r => dueSoon(r))" class="text-[#B45309]" x-text="'· ' + rows.filter(r => dueSoon(r)).length + ' ≤ 7 Tage'"></span>
                        </span>
                        <div class="relative">
                            <input x-ref="search" x-model.debounce.200ms="query" @keydown.enter="if (filtered().length) { detail = sorted(filtered())[0]; $event.target.blur(); }" placeholder="Suchen… (/)" class="w-40 sm:w-48 lg:w-64 rounded-lg border-[#D6DEE9] text-xs py-1.5 pr-6 focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                            <button x-show="query" @click="query = ''; $refs.search.focus()" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[#9CA3AF] hover:text-[#5B6B7E] text-xs leading-none" aria-label="Suche löschen">&times;</button>
                        </div>
                        <div class="relative shrink-0">
                            <button @click="colPicker = !colPicker" title="Spalten (c)" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition">Spalten<span x-show="Object.values(hiddenCols).filter(Boolean).length" class="ml-1 text-[#CA8A04]" x-text="'(' + Object.values(hiddenCols).filter(Boolean).length + ')'"></span></button>
                            <button @click="compact = !compact; try { localStorage.setItem('af_density', compact ? '1' : '0'); } catch (e) {}" :title="compact ? 'Normale Zeilenhöhe' : 'Kompakte Zeilenhöhe'"
                                    class="text-xs px-3 py-1.5 border rounded-lg transition shrink-0"
                                    :class="compact ? 'border-[#CA8A04] text-[#CA8A04] bg-[#CA8A04]/5' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]'">≡</button>
                            <div x-show="colPicker" @click.outside="colPicker = false" class="absolute right-0 mt-1.5 w-48 bg-white border border-[#E4E9F0] rounded-lg shadow-lg py-1 z-20 max-h-64 overflow-y-auto" style="display:none">
                                <button x-show="Object.values(hiddenCols).some(Boolean)" @click="hiddenCols = {}; saveColPrefs()" class="w-full text-left px-3 py-1.5 text-xs text-[#CA8A04] hover:bg-[#CA8A04]/10 border-b border-[#E4E9F0]">Alle einblenden</button>
                                <template x-for="c in columns" :key="c">
                                    <label class="flex items-center gap-2 px-3 py-1.5 text-xs text-[#1A2433] hover:bg-[#FAFBFC] cursor-pointer">
                                        <input type="checkbox" :checked="!hiddenCols[c]" @change="toggleCol(c)" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                        <span x-text="label(c)"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <select x-model="groupBy" title="Gruppieren nach" class="text-xs px-2 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg bg-white hover:border-[#CA8A04] transition shrink-0 max-w-[10rem]">
                            <option value="">Keine Gruppierung</option>
                            <template x-for="c in columns" :key="'g-'+c">
                                <option :value="c" x-text="label(c)"></option>
                            </template>
                        </select>
                        <div class="relative shrink-0">
                            <button @click="viewPicker = !viewPicker" title="Gespeicherte Ansichten" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition">Ansichten<span x-show="Object.keys(views()).length" class="ml-1 text-[#CA8A04]" x-text="'(' + Object.keys(views()).length + ')'"></span></button>
                            <div x-show="viewPicker" @click.outside="viewPicker = false" class="absolute right-0 mt-1.5 w-56 bg-white border border-[#E4E9F0] rounded-lg shadow-lg py-1 z-20" style="display:none">
                                <button @click="saveView()" class="w-full text-left px-3 py-1.5 text-xs text-[#CA8A04] hover:bg-[#CA8A04]/10 border-b border-[#E4E9F0]">+ Aktuelle Ansicht speichern</button>
                                <p x-show="!Object.keys(views()).length" class="px-3 py-2 text-[11px] text-[#9CA3AF]">Noch keine Ansichten.</p>
                                <template x-for="(v, name) in views()" :key="name">
                                    <div class="flex items-center hover:bg-[#FAFBFC]">
                                        <button @click="applyView(name)" class="flex-1 text-left px-3 py-1.5 text-xs text-[#1A2433] truncate" x-text="name"></button>
                                        <button @click="deleteView(name)" class="px-2 text-[#9CA3AF] hover:text-[#A6362E] text-xs" title="Ansicht löschen">&times;</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <button @click="exportCsv()" title="CSV exportieren" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition shrink-0">CSV</button>
                        <button x-show="canImport()" @click="showImport = true; importText = ''; importResult = ''" title="CSV importieren (i)" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition shrink-0">CSV ↑</button>
                        <button @click="window.print()" title="Drucken" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition shrink-0">Drucken</button>
                        <button x-show="canCreate()" @click="openCreate()" :title="'Neu anlegen: ' + title() + ' (n)'" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition shrink-0">+ Neu</button>
                    </div>
                    <div x-show="rows && (statusOpts().length > 1 || rows.some(r => overdue(r)))" class="flex flex-wrap items-center gap-1.5 px-5 py-2 border-b border-[#E4E9F0] print:hidden">
                        <button x-show="rows.some(r => overdue(r))" @click="overdueOnly = !overdueOnly" title="Überfällig (u)" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="overdueOnly ? 'border-[#A6362E] bg-[#A6362E] text-white' : 'border-[#A6362E]/40 text-[#A6362E] hover:bg-[#A6362E]/5'"
                                x-text="'Überfällig · ' + rows.filter(r => overdue(r)).length"></button>
                        <button x-show="rows.some(r => dueSoon(r))" @click="dueSoonOnly = !dueSoonOnly" title="≤7 Tage (s)" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="dueSoonOnly ? 'border-[#B45309] bg-[#B45309] text-white' : 'border-[#B45309]/40 text-[#B45309] hover:bg-[#B45309]/5'"
                                x-text="'≤ 7 Tage · ' + rows.filter(r => dueSoon(r)).length"></button>
                        <button x-show="rows.some(r => 'assignee_id' in r || 'responsible_id' in r)" @click="myOnly = !myOnly" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="myOnly ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'">Mir zugewiesen</button>
                        <button @click="statusFilter = ''" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="statusFilter === '' ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                x-text="'Alle · ' + rows.length"></button>
                        <template x-for="s in statusOpts()" :key="s">
                            <button @click="statusFilter = statusFilter === s ? '' : s" class="text-[11px] px-2.5 py-1 rounded-full border transition inline-flex items-center gap-1.5"
                                    :class="statusFilter === s ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'">
                                <span class="w-1.5 h-1.5 rounded-full" :style="'background:' + statusColor(s)"></span>
                                <span x-text="statusLabel(s) + ' · ' + rows.filter(r => String(r.status) === s).length"></span>
                            </button>
                        </template>
                    </div>
                    <div x-show="loading && !rows" class="px-6 py-6 space-y-3" aria-hidden="true">
                        <template x-for="i in 6" :key="i">
                            <div class="flex gap-4">
                                <div class="h-3.5 rounded bg-[#E8ECF2] animate-pulse" :style="'width:' + ([38,62,45,55,70,30][i-1] || 50) + '%'"></div>
                            </div>
                        </template>
                    </div>
                    <div x-show="rows && filtered().length === 0" class="px-6 py-12 text-center">
                        <p class="text-sm text-[#5B6B7E]" x-text="query || statusFilter || overdueOnly || dueSoonOnly || myOnly ? 'Keine Einträge für diese Filter.' : 'Keine Einträge vorhanden.'"></p>
                        <button x-show="query || statusFilter || overdueOnly || dueSoonOnly || myOnly" @click="query = ''; statusFilter = ''; overdueOnly = false; dueSoonOnly = false; myOnly = false"
                                title="Filter zurücksetzen (x)" class="mt-3 text-xs px-3.5 py-2 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition">Filter zurücksetzen</button>
                        <button x-show="canCreate() && !query && !statusFilter && !overdueOnly && !dueSoonOnly && !myOnly" @click="openCreate()"
                                class="mt-3 text-xs px-3.5 py-2 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition">+ Ersten Eintrag erstellen</button>
                    </div>
                    <div x-show="selCount() > 0" class="flex flex-wrap items-center gap-2 px-5 py-2.5 border-b border-[#E4E9F0] bg-[#FFFBEB]">
                        <span class="text-xs font-semibold text-[#0B0B0F]"><span x-text="selCount()"></span> ausgewählt</span>
                        <span x-show="selSums()" class="text-[11px] text-[#5B6B7E]" x-text="selSums()"></span>
                        <template x-for="a in sectionActions()" :key="a[1]">
                            <button @click="bulkStatus(a[1])" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10" x-text="a[0]"></button>
                        </template>
                        <button @click="copySel()" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" title="Als TSV in die Zwischenablage">Kopieren</button>
                        <button @click="exportCsv((this.rows || []).filter(r => selected[r.id]))" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]">CSV</button>
                        <button @click="bulkDelete()" class="text-xs px-3 py-1.5 border border-[#A6362E]/40 text-[#A6362E] rounded-lg hover:bg-[#A6362E]/5">Löschen</button>
                        <button @click="selected = {}" class="text-xs px-3 py-1.5 text-[#5B6B7E] hover:text-[#0B0B0F]">Auswahl aufheben</button>
                    </div>
                    <div class="max-h-[70vh] overflow-y-auto">
                    <table x-show="rows && filtered().length" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[#E4E9F0] bg-[#FAFBFC] text-left">
                                <th x-show="writable()" class="px-4 py-3 w-10">
                                    <input type="checkbox" @change="toggleAll($event.target.checked)" :checked="sorted(filtered()).length > 0 && selCount() === sorted(filtered()).length"
                                           class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                </th>
                                <th class="px-3 py-3 text-[11px] font-semibold tracking-wide text-[#9CA3AF] w-8">#</th>
                                <template x-for="c in visCols()" :key="c">
                                    <th @click="sort(c)" :title="'Sortieren: ' + label(c) + (sortKey===c ? (sortAsc ? ' (aufsteigend)' : ' (absteigend)') : '')" class="sticky top-0 z-10 bg-[#FAFBFC] px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E] cursor-pointer select-none hover:text-[#0B0B0F]">
                                        <span x-text="label(c)"></span><span class="ml-1 text-[#CA8A04]" x-text="sortKey===c ? (sortAsc?'▲':'▼') : ''"></span>
                                    </th>
                                </template>
                                <th x-show="sectionActions().length || canEdit()" class="px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E] w-28">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="it in renderRows()" :key="it.t === 'h' ? 'h-'+it.label : it.r.id">
                                <tr @click="it.t === 'r' ? (detail = it.r) : (collapsedGroups[it.label] = !collapsedGroups[it.label])" @dblclick="it.t === 'r' && canEdit() && (detail = it.r, openEdit())"
                                    :class="it.t === 'h' ? 'bg-[#F0F3F7] hover:bg-[#E4E9F0] cursor-pointer' : ([overdue(it.r) ? 'bg-[#A6362E]/5' : '', selected[it.r.id] ? 'bg-[#FFFBEB]' : '', detail && detail.id === it.r.id ? 'bg-[#FACC15]/10' : '', it.i % 2 ? 'bg-[#FAFBFC]/50' : ''].join(' ') + ' hover:bg-[#F3F6FA] cursor-pointer')"
                                    class="border-b border-[#F0F3F7] last:border-b-0" :title="it.t === 'r' ? 'Doppelklick: Bearbeiten' : 'Klick: einklappen/ausklappen'">
                                    <template x-if="it.t === 'h'">
                                        <td :colspan="visCols().length + 1 + (writable() ? 1 : 0) + (sectionActions().length || canEdit() ? 1 : 0)" class="px-5 py-2 text-[11px] font-semibold text-[#5B6B7E]">
                                            <span x-text="collapsedGroups[it.label] ? '▸' : '▾'" class="mr-1.5 text-[#CA8A04]"></span><span x-text="it.label || '—'"></span> <span class="font-normal text-[#9CA3AF]" x-text="'· ' + it.count"></span><span x-show="it.overdue" class="ml-1.5 font-normal text-[#A6362E]" x-text="it.overdue + ' überfällig'"></span>
                                        </td>
                                    </template>
                                    <template x-if="it.t === 'r'">
                                        <template x-for="e in it.cells" :key="e.t + ':' + (e.c || '')">
                                            <template x-if="e.t === 'cb'">
                                                <td x-show="writable()" @click.stop class="px-4 py-3 w-10">
                                                    <input type="checkbox" @change="toggleSel(it.r.id)" :checked="!!selected[it.r.id]"
                                                           class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                                </td>
                                            </template>
                                            <template x-if="e.t === 'n'">
                                                <td class="px-3 py-3 text-[#9CA3AF] text-xs tabular-nums" x-text="it.i + 1"></td>
                                            </template>
                                            <template x-if="e.t === 'c'">
                                                <td class="px-5 text-[#1A2433]" :class="compact ? 'py-1.5 text-xs' : 'py-3'">
                                                    <span x-html="cell(it.r, e.c)"></span><span x-show="e.c === visCols()[0] && isNew(it.r)" class="ml-1.5 text-[9px] font-bold px-1 py-0.5 rounded bg-[#FACC15] text-[#0B0B0F] align-middle">NEU</span>
                                                </td>
                                            </template>
                                            <template x-if="e.t === 'act'">
                                                <td x-show="sectionActions().length || canEdit()" @click.stop class="px-5 py-3 w-28">
                                                    <div class="flex gap-1 items-center">
                                                        <template x-for="a in rowActions(it.r).slice(0, 2)" :key="a[1]">
                                                            <button @click="applyRowStatus(it.r, a[1])" class="text-[10px] px-2 py-1 rounded-md border border-[#CA8A04]/50 text-[#CA8A04] hover:bg-[#CA8A04]/10 whitespace-nowrap" x-text="a[0]"></button>
                                                        </template>
                                                        <button x-show="canEdit()" @click="detail = it.r; openEdit()" title="Bearbeiten" class="text-[11px] px-1.5 py-1 rounded-md border border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]">&#9998;</button>
                                                        <button x-show="canEdit()" @click="deleteRow(it.r)" title="Löschen" class="text-[11px] px-1.5 py-1 rounded-md border border-[#A6362E]/40 text-[#A6362E] hover:bg-[#A6362E]/5">&#128465;</button>
                                                    </div>
                                                </td>
                                            </template>
                                        </template>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot x-show="hasSums()">
                            <tr class="border-t-2 border-[#E4E9F0] bg-[#FAFBFC]">
                                <td x-show="writable()" class="px-4 py-2.5 w-10"></td>
                                <td class="px-3 py-2.5"></td>
                                <template x-for="c in visCols()" :key="'f-'+c">
                                    <td class="px-5 py-2.5 text-xs font-semibold text-[#1A2433] tabular-nums" x-text="colSum(c)"></td>
                                </template>
                                <td x-show="sectionActions().length || canEdit()" class="px-5 py-2.5 w-28"></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                    <div x-show="filtered().length > limit" class="px-5 py-3 border-t border-[#E4E9F0] text-center flex items-center justify-center gap-4 print:hidden">
                        <button @click="limit += 100" class="text-xs text-[#CA8A04] hover:underline">
                            Mehr laden (<span x-text="filtered().length - limit"></span> weitere)
                        </button>
                        <button x-show="filtered().length - limit > 100" @click="limit = filtered().length" title="Alle laden (l)" class="text-xs text-[#5B6B7E] hover:text-[#CA8A04] hover:underline">
                            Alle laden
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Detail drawer --}}
        <div x-show="detail" class="fixed inset-0 z-40" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="detail = null"></div>
            <div class="absolute inset-y-0 right-0 w-full bg-white shadow-xl flex flex-col transition-[max-width] duration-200" :class="drawerWide ? 'max-w-2xl' : 'max-w-md'" role="dialog" aria-modal="true" :aria-label="title() + ' · Details'">
                <div class="px-6 py-4 border-b border-[#E4E9F0] flex items-center justify-between">
                    <h2 class="font-semibold text-[#0B0B0F] flex items-center gap-2 min-w-0"><span class="truncate" x-text="detailTitle()"></span><span x-show="overdue(detail)" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded bg-[#A6362E] text-white">ÜBERFÄLLIG</span></h2>
                    <div class="flex items-center gap-1">
                        <button @click="drawerWide = !drawerWide" class="p-1.5 rounded-lg text-[#9CA3AF] hover:text-[#0B0B0F] hover:bg-[#F0F3F7] transition" :title="drawerWide ? 'Schmal' : 'Breit'">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3m0 0l-3-3m3 3l3-3M16 21v-3m0 0l-3 3m3-3l3 3"/></svg>
                        </button>
                        <button @click="navDetail(-1)" :disabled="!hasNav(-1)" :class="hasNav(-1) ? 'text-[#9CA3AF] hover:text-[#0B0B0F] hover:bg-[#F0F3F7]' : 'text-[#E4E9F0] cursor-not-allowed'" class="p-1.5 rounded-lg transition" title="Vorheriger (←)"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
                        <button @click="navDetail(1)" :disabled="!hasNav(1)" :class="hasNav(1) ? 'text-[#9CA3AF] hover:text-[#0B0B0F] hover:bg-[#F0F3F7]' : 'text-[#E4E9F0] cursor-not-allowed'" class="p-1.5 rounded-lg transition" title="Nächster (→)"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                        <span class="text-[11px] font-mono text-[#9CA3AF] px-1" x-text="detailPos()"></span>
                    </div>
                    <button @click="detail = null" class="text-[#5B6B7E] hover:text-[#0B0B0F]" title="Schließen (Esc)" aria-label="Schließen">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-6">
                    <dl class="space-y-3 text-sm">
                        <template x-for="k in detailKeys()" :key="k">
                            <div class="flex gap-3 group">
                                <dt class="w-36 shrink-0 text-[#5B6B7E]" :title="k" x-text="label(k)"></dt>
                                <dd class="min-w-0 flex-1 font-mono text-[13px] text-[#1A2433] break-words">
                                    <span x-show="!linkOf(detail[k]) && !(k === 'status' || k === 'severity')" x-text="fmtD(detail, k)"></span>
                                    <span x-show="(k === 'status' || k === 'severity')" class="inline-flex items-center gap-1.5 font-sans text-[13px]"><span class="h-2 w-2 rounded-full" :style="'background:' + statusColor(detail[k])"></span><span x-text="statusLabel(detail[k])"></span></span>
                                    <a x-show="linkOf(detail[k])" :href="linkOf(detail[k])" :target="/^https?:/.test(linkOf(detail[k]) || '') ? '_blank' : null" rel="noopener"
                                       class="text-[#CA8A04] hover:underline break-all" x-text="fmtD(detail, k)"></a>
                                    <a x-show="refSection(k) && detail[k]" :href="'/app/' + refSection(k) + '?tenant=' + tenant + '&open=' + detail[k]"
                                       class="ml-1.5 text-[#CA8A04] hover:underline text-[11px] font-sans whitespace-nowrap">öffnen →</a>
                                </dd>
                                <button x-show="detail[k] !== null && detail[k] !== undefined && detail[k] !== ''"
                                        @click="copyVal(detail[k])" title="Wert kopieren"
                                        class="self-start shrink-0 opacity-0 group-hover:opacity-100 transition text-[#9CA3AF] hover:text-[#CA8A04] text-xs leading-none">⧉</button>
                            </div>
                        </template>
                    </dl>
                </div>
                <div x-show="statusActions().length" class="px-6 py-3 border-t border-[#E4E9F0] flex flex-wrap gap-2">
                    <template x-for="a in statusActions()" :key="a[0]">
                        <button @click="applyStatus(a[1])" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10" x-text="a[0]"></button>
                    </template>
                </div>
                <div x-show="section === 'tenders'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-3">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]">BEWERBUNGEN</div>
                    <div x-show="!apps.length" class="text-xs text-[#9CA3AF]">Noch keine Bewerbungen.</div>
                    <template x-for="a in apps" :key="a.id">
                        <div class="rounded-lg border px-3 py-2.5" :class="a.status === 'awarded' ? 'border-[#2E7D5B]/50 bg-[#2E7D5B]/5' : 'border-[#E4E9F0]'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium text-[#1A2433]" x-text="appName(a)"></span>
                                <span class="text-[10px] font-semibold" :class="a.status === 'awarded' ? 'text-[#2E7D5B]' : a.status === 'rejected' ? 'text-[#A6362E]' : 'text-[#5B6B7E]'" x-text="appStatus(a.status)"></span>
                            </div>
                            <p x-show="a.proposal" class="mt-1 text-sm text-[#42536A] whitespace-pre-wrap" x-text="a.proposal"></p>
                            <div x-show="a.price" class="mt-1 text-xs text-[#5B6B7E]"><span x-text="a.price"></span> €</div>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <template x-for="t in [['shortlisted','Vormerken'],['awarded','Vergeben'],['rejected','Ablehnen']]" :key="t[0]">
                                    <button x-show="a.status !== t[0]" @click="setAppStatus(a.id, t[0])" class="text-[11px] font-medium text-[#CA8A04] hover:underline" x-text="t[1]"></button>
                                </template>
                                <button @click="deleteApp(a.id)" class="text-[11px] text-[#A6362E] hover:underline">Löschen</button>
                            </div>
                        </div>
                    </template>
                    <form x-show="detail.status === 'open'" @submit.prevent="submitApp()" class="space-y-2 rounded-lg border border-dashed border-[#D6DEE9] p-3">
                        <select x-model="appForm.expert_profile_id" class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                            <option value="">— Experte wählen —</option>
                            <template x-for="o in fkOptions('expert_profiles')" :key="o[0]">
                                <option :value="o[0]" x-text="o[1]"></option>
                            </template>
                        </select>
                        <textarea x-model="appForm.proposal" rows="2" placeholder="Angebot / Beschreibung…"
                                  class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30"></textarea>
                        <input x-model="appForm.price" type="number" min="0" step="0.01" placeholder="Preis (€)"
                               class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                        <div class="flex justify-end">
                            <button type="submit" :disabled="!appForm.expert_profile_id" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] disabled:opacity-40">Bewerbung anlegen</button>
                        </div>
                    </form>
                </div>
                <div x-show="section === 'questions'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-3">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]">ANTWORTEN</div>
                    <div x-show="!answers.length" class="text-xs text-[#9CA3AF]">Noch keine Antworten.</div>
                    <template x-for="ans in answers" :key="ans.id">
                        <div class="rounded-lg border px-3 py-2.5" :class="ans.is_accepted ? 'border-[#2E7D5B]/50 bg-[#2E7D5B]/5' : 'border-[#E4E9F0]'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium text-[#1A2433]" x-text="ans.author?.name || resolveId('answered_by', ans.answered_by) || '—'"></span>
                                <span x-show="ans.is_accepted" class="text-[10px] font-semibold text-[#2E7D5B]">AKZEPTIERT</span>
                            </div>
                            <p class="mt-1 text-sm text-[#42536A] whitespace-pre-wrap" x-text="ans.body"></p>
                            <div class="mt-2 flex gap-3">
                                <button x-show="!ans.is_accepted" @click="acceptAnswer(ans.id)" class="text-[11px] font-medium text-[#2E7D5B] hover:underline">Akzeptieren</button>
                                <button @click="deleteAnswer(ans.id)" class="text-[11px] text-[#A6362E] hover:underline">Löschen</button>
                            </div>
                        </div>
                    </template>
                    <form @submit.prevent="postAnswer()" class="space-y-2">
                        <textarea x-model="answerText" rows="3" placeholder="Antwort schreiben…"
                                  class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30"></textarea>
                        <div class="flex justify-end">
                            <button type="submit" :disabled="!answerText.trim()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] disabled:opacity-40">Antworten</button>
                        </div>
                    </form>
                </div>
                <div x-show="section === 'documents'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-3">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]">VERSIONEN</div>
                    <template x-for="v in docVersions" :key="v.id">
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-[#E4E9F0] px-3 py-2">
                            <div class="min-w-0">
                                <span class="text-xs font-semibold text-[#0B0B0F]">v<span x-text="v.version"></span></span>
                                <span class="ml-2 text-xs text-[#42536A] truncate" x-text="v.original_name"></span>
                            </div>
                            <a :href="'/api/v1/documents/' + detail.id + '/download/' + v.id" class="text-[11px] font-medium text-[#CA8A04] hover:underline shrink-0">Download</a>
                        </div>
                    </template>
                    <form x-show="writable()" @submit.prevent="uploadVersion()" class="flex items-center gap-2">
                        <input type="file" x-ref="versionFile" class="min-w-0 flex-1 text-xs text-[#42536A]">
                        <button type="submit" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] shrink-0">Neue Version</button>
                    </form>
                </div>
                <div x-show="section === 'graph-entities'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-3">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]">VERKNÜPFUNGEN</div>
                    <template x-for="e in entityEdges" :key="e.relation + e.other">
                        <div>
                            <div class="flex items-center gap-2 rounded-lg border border-[#E4E9F0] px-3 py-2 text-xs hover:border-[#CA8A04]/60 transition">
                                <a :href="'/app/graph-entities?tenant=' + tenant + '&open=' + e.other" class="flex items-center gap-2 flex-1 min-w-0">
                                    <span class="font-mono text-[#9CA3AF] w-4 shrink-0" x-text="e.dir"></span>
                                    <span class="text-[#5B6B7E]" x-text="e.relation"></span>
                                    <span class="ml-auto font-medium text-[#1A2433] truncate" x-text="e.name"></span>
                                </a>
                                <button @click="expandedEdge = expandedEdge === e.relation + e.other ? null : e.relation + e.other"
                                        class="shrink-0 text-[#9CA3AF] hover:text-[#CA8A04] px-1" title="Nachbarn zeigen">
                                    <span x-text="expandedEdge === e.relation + e.other ? '−' : '+'"></span>
                                </button>
                            </div>
                            <div x-show="expandedEdge === e.relation + e.other" class="ml-6 mt-1 space-y-1">
                                <template x-for="n in edgeNeighbors(e.other)" :key="n.relation + n.other">
                                    <a :href="'/app/graph-entities?tenant=' + tenant + '&open=' + n.other"
                                       class="flex items-center gap-2 rounded-md bg-[#FAFBFC] px-3 py-1.5 text-[11px] hover:bg-[#F0F3F7] transition">
                                        <span class="font-mono text-[#9CA3AF] w-4 shrink-0" x-text="n.dir"></span>
                                        <span class="text-[#5B6B7E]" x-text="n.relation"></span>
                                        <span class="ml-auto font-medium text-[#1A2433] truncate" x-text="n.name"></span>
                                    </a>
                                </template>
                                <div x-show="edgeNeighbors(e.other).length === 0" class="text-[11px] text-[#9CA3AF] px-3 py-1">Keine weiteren Kanten.</div>
                            </div>
                        </div>
                    </template>
                    <div x-show="entityEdges.length === 0" class="text-xs text-[#9CA3AF]">Keine Kanten zu dieser Entität.</div>
                </div>
                <div x-show="detail && (detail.created_at || detail.updated_at)" class="px-6 py-2.5 border-t border-[#F0F3F7] text-[10px] text-[#9CA3AF] flex gap-4">
                    <span x-show="detail && detail.created_at">Erstellt: <span x-text="detail && new Date(detail.created_at).toLocaleString('de-DE')"></span></span>
                    <span x-show="detail && detail.updated_at">Geändert: <span x-text="detail && new Date(detail.updated_at).toLocaleString('de-DE')"></span></span>
                </div>
                <div class="px-6 py-4 border-t border-[#E4E9F0] flex justify-end gap-2">
                    <button @click="copyLink()" title="Link kopieren (p)" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" x-text="linkCopied ? 'Kopiert' : 'Link'"></button>
                    <button @click="copyJson()" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" x-text="jsonCopied ? 'Kopiert' : 'JSON'"></button>
                    <button x-show="['documents','data-objects'].includes(section)" @click="downloadDoc(detail)" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10">Download</button>
                    <button x-show="canEdit() && section !== 'documents'" @click="openDuplicate()" title="Duplizieren (d)" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]">Duplizieren</button>
                    <button x-show="canEdit()" @click="openEdit()" title="Bearbeiten (e)" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F]">Bearbeiten</button>
                    <button x-show="writable()" @click="confirmDel ? deleteRow(detail) : (confirmDel = true, setTimeout(() => confirmDel = false, 3000))"
                            class="text-xs px-3 py-1.5 border rounded-lg transition"
                            :class="confirmDel ? 'border-[#A6362E] bg-[#A6362E] text-white' : 'border-[#A6362E]/40 text-[#A6362E] hover:bg-[#A6362E]/5'"
                            x-text="confirmDel ? 'Wirklich löschen?' : 'Löschen'"></button>
                </div>
            </div>
        </div>

        {{-- Shortcuts help (?) --}}
        <div x-show="kbdHelp" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/50" @click="kbdHelp = false"></div>
            <div class="relative w-full max-w-sm bg-white rounded-xl shadow-2xl p-6 max-h-[80vh] overflow-y-auto">
                <h2 class="font-semibold text-[#0B0B0F] mb-4">Tastenkürzel</h2>
                <dl class="space-y-2 text-sm">
                    <template x-for="k in [['Ctrl/⌘ + K', 'Befehlspalette öffnen'], ['/', 'Suche fokussieren'], ['n', 'Neuen Eintrag anlegen'], ['e', 'Eintrag bearbeiten (Drawer)'], ['d', 'Eintrag duplizieren (Drawer)'], ['p', 'Deep-Link kopieren (Drawer)'], ['o', 'Erste gefilterte Zeile öffnen'], ['l', 'Alle Zeilen laden'], ['r', 'Liste neu laden'], ['i', 'CSV-Import öffnen'], ['c', 'Spalten-Picker'], ['x', 'Filter zurücksetzen'], ['u', 'Überfällig-Filter'], ['s', '≤7-Tage-Filter'], ['.', 'Zum Dashboard'], ['1–9', 'n-te Zeile öffnen'], ['← / →', 'Vorheriger / nächster Eintrag (Drawer)'], ['Ctrl + Enter', 'Formular absenden'], ['t', 'Dunkel/Hell umschalten'], ['Esc', 'Schließen'], ['?', 'Diese Übersicht']]" :key="k[0]">
                        <div class="flex justify-between items-center">
                            <dt class="text-[#5B6B7E]"><kbd class="px-1.5 py-0.5 bg-[#F0F3F7] border border-[#E4E9F0] rounded text-xs font-mono" x-text="k[0]"></kbd></dt>
                            <dd class="text-[#1A2433]" x-text="k[1]"></dd>
                        </div>
                    </template>
                </dl>
            </div>
        </div>

        {{-- Command palette (Ctrl+K) --}}
        <div x-show="palette" class="fixed inset-0 z-50" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/50" @click="palette = false"></div>
            <div class="relative mx-auto mt-24 w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden">
                <input x-ref="paletteInput" x-model="paletteQ" x-init="$watch('palette', v => v && $nextTick(() => $refs.paletteInput.focus()))"
                       placeholder="Modul suchen…" class="w-full px-5 py-4 text-sm border-0 border-b border-[#E4E9F0] focus:ring-0 focus:border-[#CA8A04]"
                       @keydown.enter.prevent="paletteGo()">
                <div class="max-h-72 overflow-y-auto py-1">
                    <template x-for="it in paletteItems()" :key="it.key">
                        <a :href="'/app/' + it.key + (tenant ? '?tenant='+tenant : '')"
                           class="block px-5 py-2.5 text-sm text-[#1A2433] hover:bg-[#FAFBFC] transition">
                            <span class="inline-block w-4 text-center text-[11px] text-[#9CA3AF] mr-2" x-text="icons[it.key] || '·'"></span><span x-text="it.label"></span>
                            <span class="ml-2 text-[10px] text-[#9CA3AF] tracking-wide" x-text="it.group"></span>
                        </a>
                    </template>
                    <div x-show="paletteItems().length === 0" class="px-5 py-6 text-center text-xs text-[#9CA3AF]">Kein Modul gefunden.</div>
                </div>
            </div>
        </div>

        {{-- Create modal --}}
        <div x-show="showCreate" class="fixed inset-0 z-40 flex items-center justify-center" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="closeCreate()"></div>
            <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl" role="dialog" aria-modal="true">
                <div class="px-6 py-4 border-b border-[#E4E9F0]">
                    <h2 class="font-semibold text-[#0B0B0F]" x-text="(editing ? 'Bearbeiten: ' : 'Neu: ') + title()"></h2>
                </div>
                <form @submit.prevent="submitCreate" @input="formDirty = true" @keydown.ctrl.enter.prevent="submitCreate" class="p-6 space-y-4" id="createForm">
                    <template x-for="f in createFields()" :key="f.key">
                        <div>
                            <label class="block text-[13px] font-medium text-[#42536A] mb-1">
                                <span x-text="label(f.key)"></span><span x-show="f.req" class="text-[#A6362E]"> *</span>
                            </label>
                            <select x-show="f.type === 'fk'" x-model="form[f.key]"
                                    class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                                <option value="">— wählen —</option>
                                <template x-for="o in fkOptions(f.table)" :key="o[0]">
                                    <option :value="o[0]" x-text="o[1]"></option>
                                </template>
                            </select>
                            <label x-show="f.type === 'checkbox'" class="inline-flex items-center gap-2 text-sm text-[#42536A]">
                                <input type="checkbox" x-model="form[f.key]" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                <span x-text="label(f.key)"></span>
                            </label>
                            <textarea x-show="f.type === 'textarea'" x-model="form[f.key]" rows="3"
                                      class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30"></textarea>
                            <div x-show="f.type !== 'fk' && f.type !== 'checkbox' && f.type !== 'textarea'" class="relative">
                                <input x-model="form[f.key]" :type="f.type"
                                       class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                                <button x-show="f.type === 'date' || f.type === 'datetime-local'" type="button"
                                        @click="form[f.key] = f.type === 'date' ? new Date().toISOString().slice(0,10) : new Date(Date.now() - new Date().getTimezoneOffset()*60000).toISOString().slice(0,16)"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-[#CA8A04] hover:underline">Heute</button>
                            </div>
                        </div>
                    </template>
                    <div x-show="['documents','data-objects'].includes(section) && !editing">
                        <label class="block text-[13px] font-medium text-[#42536A] mb-1">Datei</label>
                        <input type="file" x-ref="fileInput" class="w-full text-sm">
                    </div>
                    <div x-show="formError" class="text-xs text-[#A6362E]" x-text="formError"></div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="closeCreate()" class="text-sm px-4 py-2 text-[#5B6B7E]">Abbrechen</button>
                        <button type="submit" :disabled="createFields().some(f => f.req && !String(form[f.key] || '').trim())"
                                class="text-sm px-4 py-2 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] disabled:opacity-40 disabled:cursor-not-allowed" title="Speichern (Strg+↵)">Speichern</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- CSV import modal --}}
        <div x-show="showImport" class="fixed inset-0 z-40 flex items-center justify-center" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="showImport = false"></div>
            <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl">
                <div class="px-6 py-4 border-b border-[#E4E9F0]">
                    <h2 class="font-semibold text-[#0B0B0F]">CSV Import: <span x-text="title()"></span></h2>
                </div>
                <div class="p-6 space-y-3">
                    <p class="text-xs text-[#5B6B7E]">Erste Zeile = Spaltennamen (<span x-text="createFields().map(f => f.key).join(', ')"></span>). Trennzeichen ; oder ,</p>
                    <textarea x-model="importText" rows="8" class="w-full rounded-lg border-[#D6DEE9] text-xs font-mono focus:border-[#CA8A04] focus:ring-[#CA8A04]/30" placeholder="title;status&#10;Beispiel;open"></textarea>
                    <div x-show="importResult" class="text-xs" :class="importErr ? 'text-[#A6362E]' : 'text-[#2E7D4F]'" x-text="importResult"></div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="showImport = false" class="text-sm px-4 py-2 text-[#5B6B7E]">Schließen</button>
                        <button type="button" @click="importCsv()" :disabled="importing" class="text-sm px-4 py-2 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] disabled:opacity-50" x-text="importing ? 'Importiere…' : 'Importieren'"></button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Toasts --}}
    <div class="fixed bottom-4 right-4 z-50 space-y-2">
        <template x-for="t in toasts" :key="t.id">
            <div class="bg-[#0B0B0F] text-white text-xs px-4 py-3 rounded-lg shadow-lg border-l-2 border-[#A6362E] max-w-xs flex items-center gap-3">
                <span x-text="t.msg" class="flex-1"></span>
                <button x-show="t.action" @click="t.action.fn(); toasts = toasts.filter(x => x.id !== t.id)"
                        class="text-[#FACC15] font-semibold hover:underline shrink-0" x-text="t.action ? t.action.label : ''"></button>
            </div>
        </template>
    </div>
</div>

<script>
function workspace(initial) {
    const GROUPS = [
        {label:'START', items:[{key:'dashboard',label:'Dashboard'},{key:'executive',label:'Executive'}]},
        {label:'STAMMDATEN', items:[
            {key:'companies',label:'Unternehmen',ep:'/api/v1/companies'},
            {key:'persons',label:'Personen',ep:'/api/v1/persons'},
            {key:'documents',label:'Dokumente',ep:'/api/v1/documents'},
            {key:'tasks',label:'Aufgaben',ep:'/api/v1/tasks'},
        ]},
        {label:'COMPLIANCE', items:[
            {key:'instructions',label:'Unterweisungen',ep:'/api/v1/instructions'},
            {key:'inspections',label:'Prüfungen',ep:'/api/v1/inspections'},
            {key:'deadlines',label:'Fristen',ep:'/api/v1/deadlines'},
            {key:'risk-assessments',label:'Gefährdungsbeurteilungen',ep:'/api/v1/risk-assessments'},
            {key:'operating-instructions',label:'Betriebsanweisungen',ep:'/api/v1/operating-instructions'},
        ]},
        {label:'NETZWERK', items:[
            {key:'expert-profiles',label:'Experten',ep:'/api/v1/expert-profiles'},
            {key:'questions',label:'Fragen',ep:'/api/v1/questions'},
            {key:'tenders',label:'Ausschreibungen',ep:'/api/v1/tenders'},
        ]},
        {label:'ENTWICKLUNG', items:[
            {key:'strategies',label:'Strategien',ep:'/api/v1/strategies'},
            {key:'projects',label:'Projekte',ep:'/api/v1/projects'},
            {key:'measures',label:'Maßnahmen',ep:'/api/v1/measures'},
        ]},
        {label:'KAPITAL', items:[
            {key:'portfolios',label:'Portfolios',ep:'/api/v1/portfolios'},
            {key:'investments',label:'Investments',ep:'/api/v1/investments'},
            {key:'participations',label:'Beteiligungen',ep:'/api/v1/participations'},
        ]},
        {label:'PRODUKTION', items:[
            {key:'machines',label:'Maschinen',ep:'/api/v1/machines'},
            {key:'production-orders',label:'Aufträge',ep:'/api/v1/production-orders'},
        ]},
        {label:'ORGANISATION', items:[
            {key:'leave-requests',label:'Abwesenheiten',ep:'/api/v1/leave-requests'},
            {key:'financial-reports',label:'Finanzen',ep:'/api/v1/financial-reports'},
        ]},
        {label:'PLATTFORM', items:[
            {key:'events',label:'Events',ep:'/api/v1/events'},
            {key:'data-objects',label:'Data Lake',ep:'/api/v1/data-objects'},
            {key:'ai-analyses',label:'KI-Analysen',ep:'/api/v1/ai-analyses'},
            {key:'graph-entities',label:'Graphen · Entitäten',ep:'/api/v1/graph-entities'},
            {key:'graph-edges',label:'Graphen · Kanten',ep:'/api/v1/graph-edges'},
        ]},
    ];
    const ICONS = {dashboard:'◈',companies:'▣',persons:'◉',documents:'▤',tasks:'☑',instructions:'ⓘ',inspections:'✓',deadlines:'◷','risk-assessments':'⚠','operating-instructions':'✎','expert-profiles':'◎',questions:'?',tenders:'☰',strategies:'⌘',projects:'◇',measures:'→',portfolios:'▲',investments:'€',participations:'◆',machines:'⚙','production-orders':'▶','hr-requests':'◔','financial-reports':'₣',events:'≋','data-objects':'▦','ai-analyses':'✦','graph-entities':'●','graph-edges':'↔',executive:'∑'};
    const FKMAP = {person_id:'persons',company_id:'companies',machine_id:'machines',project_id:'projects',strategy_id:'strategies',portfolio_id:'portfolios',tender_id:'tenders',question_id:'questions',document_id:'documents',expert_profile_id:'expert_profiles',responsible_id:'users',assignee_id:'users',owner_id:'users',asked_by:'users',approved_by:'users',answered_by:'users',created_by:'users',uploaded_by:'users',assigned_to:'persons',from_entity_id:'graph_entities',to_entity_id:'graph_entities',subject_id:'graph_entities'};
    const STATUS_DE = {open:'Offen',pending:'Ausstehend',in_progress:'Läuft',active:'Aktiv',done:'Fertig',completed:'Abgeschlossen',approved:'Genehmigt',archived:'Archiviert',draft:'Entwurf',maintenance:'Wartung',retired:'Ausgemustert',awarded:'Vergeben',info:'Info',warning:'Warnung',critical:'Kritisch',high:'Hoch',medium:'Mittel',low:'Niedrig',scheduled:'Geplant',cancelled:'Abgesagt',rejected:'Abgelehnt',answered:'Beantwortet',closed:'Geschlossen',submitted:'Eingereicht',shortlisted:'Vorauswahl',queued:'Warteschlange',running:'Läuft',mitigated:'Gemindert',accepted:'Akzeptiert',planned:'Geplant',on_hold:'Pausiert',inactive:'Inaktiv'};
    const HIDE = new Set(['id','tenant_id','created_at','updated_at','deleted_at','pivot','data','roles','permissions','email_verified_at']);
    const KPI = [
        {key:'companies',label:'Unternehmen',to:'companies'},{key:'persons',label:'Personen',to:'persons'},
        {key:'documents',label:'Dokumente',to:'documents'},{key:'tasks_open',label:'Offene Aufgaben',to:'tasks'},
        {key:'instructions',label:'Unterweisungen',to:'instructions'},{key:'compliance_rate',label:'Compliance %',to:'instructions'},
        {key:'deadlines_open',label:'Offene Fristen',to:'deadlines'},{key:'risk_high',label:'Hohe Risiken',to:'risk-assessments'},
        {key:'tenders_open',label:'Offene Ausschreibungen',to:'tenders'},{key:'expert_profiles',label:'Experten',to:'expert-profiles'},
        {key:'questions',label:'Fragen',to:'questions'},{key:'inspections',label:'Prüfungen',to:'inspections'},
    ];
    return {
        section: initial, groups: GROUPS, kpiCards: KPI, icons: ICONS,
        tenant: '', rows: null, columns: [], metrics: null, insights: [], events: [], trends: [], spark: {}, exec: null, execReports: [], lookups: {}, navOpen: false, collapsed: {}, upcoming: [], openTasks: [],
        tenantList: {{ \Illuminate\Support\Js::from($tenants->map(fn($t) => ['id' => $t->id, 'name' => $t->name])) }},
        loading: false, error: '', detail: null, drawerWide: false, showCreate: false, compact: localStorage.getItem('af_density') === '1',
        form: {}, formError: '', formDirty: false, query: '', editing: null, showImport: false, importText: '', importResult: '', importErr: false, importing: false, toasts: [],
        sortKey: '', sortAsc: true, limit: 100, statusFilter: '', overdueOnly: false, dueSoonOnly: false, myOnly: false, linkCopied: false, jsonCopied: false, lastLoad: null, dark: document.documentElement.classList.contains('dark'),
        pins: JSON.parse(localStorage.getItem('af_pins') || '[]'), kbdHelp: false, hiddenCols: {}, colPicker: false, viewPicker: false, selected: {}, recent: [], navBadges: {},
        docVersions: [], entityEdges: [], allEdges: [], expandedEdge: null, confirmDel: false,
        answers: [], answerText: '', apps: [], appForm: {expert_profile_id: '', proposal: '', price: ''}, palette: false, paletteQ: '',
        meId: @js($user->id ?? null), offline: !navigator.onLine, groupBy: '', collapsedGroups: {},
        init() {
            window.addEventListener('online', () => { this.offline = false; this.loadSection(true); this.loadNavBadges(); });
            window.addEventListener('offline', () => { this.offline = true; });
            const p = new URLSearchParams(location.search);
            const t = p.get('tenant');
            try { this.recent = JSON.parse(localStorage.getItem('af_recent') || '[]').filter(k => k !== this.section).slice(0, 5); } catch (e) { this.recent = []; }
            if (this.section !== 'dashboard' && this.section !== 'executive') {
                const next = [this.section, ...this.recent.filter(k => k !== this.section)].slice(0, 5);
                try { localStorage.setItem('af_recent', JSON.stringify(next)); } catch (e) {}
            }
            if (t) this.tenant = t;
            else if (localStorage.getItem('allocore.tenant')) this.tenant = localStorage.getItem('allocore.tenant');
            if (this.tenant) { this.loadSection(); this.loadNavBadges(); }
            if (p.get('new')) this.$nextTick(() => { if (this.tenant && this.canCreate()) this.openCreate(); });
            if (p.get('q')) this.query = p.get('q');
            if (p.get('status')) this.statusFilter = p.get('status');
            if (p.get('overdue')) this.overdueOnly = true;
            if (p.get('dueSoon')) this.dueSoonOnly = true;
            if (p.get('my')) this.myOnly = true;
            if (p.get('group')) this.groupBy = p.get('group');
            this._urlSort = p.get('sort') || null;
            this.$watch('query', () => this.syncUrl());
            this.$watch('statusFilter', () => this.syncUrl());
            this.$watch('overdueOnly', () => this.syncUrl());
            this.$watch('dueSoonOnly', () => this.syncUrl());
            this.$watch('myOnly', () => this.syncUrl());
            this.$watch('groupBy', v => { try { localStorage.setItem('af_group_' + this.section, v); } catch (e) {} this.syncUrl(); });
            this.$watch('tenant', v => {
                if (v) localStorage.setItem('allocore.tenant', v); else localStorage.removeItem('allocore.tenant');
                document.title = this.title() + ' · ' + this.tenantName() + ' — ALLOCORE';
            });
            setInterval(() => { if (this.tenant && !this.detail && !this.showCreate && !this.palette) this.loadSection(true); }, 30000);
            this.$watch('detail', v => {
                const url = new URL(location.href);
                if (v && v.id) url.searchParams.set('open', v.id); else url.searchParams.delete('open');
                history.replaceState(null, '', url);
                this.answers = []; this.answerText = ''; this.apps = []; this.appForm = {expert_profile_id: '', proposal: '', price: ''}; this.docVersions = []; this.entityEdges = []; this.expandedEdge = null;
                this.confirmDel = false;
                if (v && this.section === 'questions') this.loadAnswers(v.id);
                if (v && this.section === 'tenders') this.loadApps(v.id);
                if (v && this.section === 'documents') this.loadDocVersions(v.id);
                if (v && this.section === 'graph-entities') this.loadEntityEdges(v.id);
            });
        },
        syncUrl() {
            const url = new URL(location.href);
            if (this.query) url.searchParams.set('q', this.query); else url.searchParams.delete('q');
            if (this.statusFilter) url.searchParams.set('status', this.statusFilter); else url.searchParams.delete('status');
            if (this.overdueOnly) url.searchParams.set('overdue', '1'); else url.searchParams.delete('overdue');
            if (this.dueSoonOnly) url.searchParams.set('dueSoon', '1'); else url.searchParams.delete('dueSoon');
            if (this.myOnly) url.searchParams.set('my', '1'); else url.searchParams.delete('my');
            if (this.groupBy) url.searchParams.set('group', this.groupBy); else url.searchParams.delete('group');
            history.replaceState(null, '', url);
        },
        item() {
            return GROUPS.flatMap(g => g.items).find(i => i.key === this.section) || {label:this.section};
        },
        groupOf(key) {
            const g = GROUPS.find(g => g.items.some(i => i.key === key));
            return g ? g.label : '';
        },
        title() { return this.item().label; },
        togglePin(key) {
            this.pins = this.pins.includes(key) ? this.pins.filter(k => k !== key) : [...this.pins, key];
            try { localStorage.setItem('af_pins', JSON.stringify(this.pins)); } catch (e) {}
        },
        sectionLabel(k) { const i = this.groups.flatMap(g => g.items).find(x => x.key === k); return i ? i.label : k; },
        allCollapsed() { return this.groups.every(g => this.collapsed[g.label]); },
        toggleAllGroups() { const v = !this.allCollapsed(); this.groups.forEach(g => { this.collapsed[g.label] = v; }); },
        overdueSections() {
            return Object.entries(this.navBadges || {}).filter(([k, n]) => n > 0)
                .map(([k, n]) => ({key: k, label: this.sectionLabel(k), count: n}))
                .sort((a, b) => b.count - a.count);
        },
        paletteItems() {
            const q = this.paletteQ.trim().toLowerCase();
            const all = this.groups.flatMap(g => g.items.map(i => ({key: i.key, label: i.label, group: g.label})));
            return q ? all.filter(i => i.label.toLowerCase().includes(q) || i.key.includes(q)) : all;
        },
        paletteGo() {
            const it = this.paletteItems()[0];
            if (it) location.href = '/app/' + it.key + (this.tenant ? '?tenant=' + this.tenant : '');
        },
        insightSection(code) { return ({tasks_overdue:'tasks',compliance_rate_low:'instructions',high_risks_open:'risk-assessments',deadlines_overdue:'deadlines',tenders_open:'tenders'})[code] || null; },
        tenantName() { const t = this.tenantList.find(x => x.id === this.tenant); return t ? t.name : '— kein Mandant —'; },
        sortedTenants() { return [...this.tenantList].sort((a, b) => String(a.name).localeCompare(String(b.name), 'de')); },
        execLabel(k) { const M = {companies:'Unternehmen',persons:'Personen',tasks_open:'Offene Aufgaben',deadlines_open:'Offene Fristen',high_risks:'Hohe Risiken',data_objects:'Data Lake'}; return M[k] || k; },
        createExecReport() {
            const title = prompt('Report-Titel', 'Executive Report ' + new Date().toLocaleDateString('de-DE'));
            if (!title) return;
            this.api('/api/v1/exec-reports', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({title})})
                .then(r => { if (r.ok) this.loadSection(); else this.toast('Report fehlgeschlagen (HTTP '+r.status+')'); });
        },
        subtitle() {
            const base = (this.section === 'dashboard' ? 'Unternehmenssteuerung' : 'Modul ' + (this.item().label||this.section)) + ' · ' + this.tenantName();
            if (this.section === 'dashboard') return base + ' · ' + new Date().toLocaleDateString('de-DE', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'});
            if (this.section === 'executive') return base;
            if (this.rows && this.rows.length) {
                const od = this.rows.filter(r => this.overdue(r)).length;
                return base + ' · ' + this.rows.length + ' ' + this.eintrag(this.rows.length) + (od ? ' · ' + od + ' überfällig' : '');
            }
            return base;
        },
        metric(k) { const v = this.metrics && this.metrics[k]; return v ? parseFloat(v.value) : '–'; },
        trend(k) { const t = this.trends.find(x => x.metric === k); return t || {delta: null, direction: 'unknown'}; },
        sparkPath(k) {
            const v = this.spark[k] || [];
            if (v.length < 2) return '';
            const min = Math.min(...v), max = Math.max(...v), span = max - min || 1;
            return v.map((p, i) => (i ? 'L' : 'M') + (i * 96 / (v.length - 1)).toFixed(1) + ',' + (22 - (p - min) / span * 20).toFixed(1)).join(' ');
        },
        toast(msg, action) {
            const t = {id: Date.now() + Math.random(), msg, action};
            this.toasts.push(t);
            setTimeout(() => { this.toasts = this.toasts.filter(x => x.id !== t.id); }, 4500);
        },
        api(path, opts={}) {
            opts.headers = Object.assign({
                'Authorization': 'Bearer {{ $apiToken }}',
                'X-Tenant': this.tenant, 'Accept': 'application/json',
            }, opts.headers||{});
            return fetch(path, opts).then(r => {
                if ((r.status === 401 || r.status === 419) && !this._authRedirect) {
                    this._authRedirect = true;
                    this.toast('Sitzung abgelaufen — bitte neu anmelden.');
                    setTimeout(() => { location.href = '/login'; }, 1400);
                }
                return r;
            });
        },
        loadNavBadges() {
            const items = this.groups.flatMap(g => g.items).filter(i => i.ep);
            Promise.all(items.map(i =>
                this.api(i.ep).then(r => r.ok ? r.json() : []).then(d => {
                    const rows = Array.isArray(d) ? d : (d.data || []);
                    return [i.key, rows.filter(r => this.overdue(r)).length];
                }).catch(() => [i.key, 0])
            )).then(pairs => { this.navBadges = Object.fromEntries(pairs); });
        },
        loadSection(soft) {
            if (!this.tenant) { this.rows = null; return; }
            if (this._loadedTenant !== this.tenant) { this.lookups = {}; this._loadedTenant = this.tenant; }
            this.loading = true; this.error = '';
            if (!soft) { this.limit = 100; this.statusFilter = ''; this.overdueOnly = false; this.dueSoonOnly = false; this.myOnly = false; this.hiddenCols = this.loadColPrefs(); this.colPicker = false; this.selected = {}; this.groupBy = localStorage.getItem('af_group_' + this.section) || ''; }
            try { const sp = JSON.parse(localStorage.getItem('af_sort_' + this.section) || 'null'); this.sortKey = sp ? sp.k : ''; this.sortAsc = sp ? sp.a : true; } catch (e) { this.sortKey = ''; this.sortAsc = true; }
            if (this._urlSort) { const m = this._urlSort.match(/^(.+?)(?::(asc|desc))?$/); this.sortKey = m[1]; this.sortAsc = m[2] !== 'desc'; this._urlSort = null; }
            const url = new URL(location.href); url.searchParams.set('tenant', this.tenant);
            history.replaceState(null,'',url);
            if (this.section === 'dashboard') {
                this.api('/api/v1/metrics').then(r => r.ok ? r.json() : (this.error='HTTP '+r.status, null))
                    .then(d => { this.metrics = d; this.loading = false; this.lastLoad = new Date(); });
                const sparkFrom = new Date(Date.now() - 60 * 864e5).toISOString().slice(0, 10);
                this.spark = {};
                KPI.forEach(m => {
                    this.api('/api/v1/metrics/' + m.key + '?from=' + sparkFrom).then(r => r.ok ? r.json() : [])
                        .then(d => { const v = (Array.isArray(d) ? d : (d.data || [])).map(x => x.value); if (v.length > 1) this.spark[m.key] = v; });
                });
                this.api('/api/v1/insights').then(r => r.ok ? r.json() : [])
                    .then(d => this.insights = d.filter(i => i.code !== 'all_clear')
                        .sort((a, b) => ({critical: 0, warning: 1, info: 2}[a.severity] ?? 3) - ({critical: 0, warning: 1, info: 2}[b.severity] ?? 3)));
                this.api('/api/v1/events').then(r => r.ok ? r.json() : [])
                    .then(d => this.events = (Array.isArray(d) ? d : (d.data || [])).slice(-15).reverse());
                this.api('/api/v1/analytics/trends').then(r => r.ok ? r.json() : [])
                    .then(d => this.trends = Array.isArray(d) ? d : (d.data || []));
                this.api('/api/v1/deadlines').then(r => r.ok ? r.json() : [])
                    .then(d => {
                        const rs = Array.isArray(d) ? d : (d.data || []);
                        this.upcoming = rs.filter(x => x.status !== 'completed' && x.due_at)
                            .sort((a,b) => new Date(a.due_at) - new Date(b.due_at)).slice(0, 5);
                    });
                this.api('/api/v1/tasks').then(r => r.ok ? r.json() : [])
                    .then(d => {
                        const rs = Array.isArray(d) ? d : (d.data || []);
                        this.openTasks = rs.filter(x => ['open','in_progress'].includes(String(x.status)))
                            .sort((a,b) => new Date(a.due_at || '9999') - new Date(b.due_at || '9999')).slice(0, 5);
                    });
                return;
            }
            if (this.section === 'executive') {
                this.api('/api/v1/executive/overview').then(r => {
                    if (!r.ok) { this.error = 'HTTP '+r.status+' — keine Berechtigung (executive.view)?'; this.exec = null; this.loading=false; return null; }
                    return r.json();
                }).then(d => { if (d) this.exec = d; this.loading = false; this.lastLoad = new Date(); });
                this.api('/api/v1/exec-reports').then(r => r.ok ? r.json() : [])
                    .then(d => this.execReports = Array.isArray(d) ? d : (d.data || []));
                return;
            }
            this.loadLookups();
            this.api(this.item().ep).then(r => {
                if (!r.ok) { this.error = 'HTTP '+r.status+' — keine Berechtigung?'; this.rows=[]; this.loading=false; return null; }
                return r.json();
            }).then(d => {
                if (d === null) return;
                const rows = Array.isArray(d) ? d : (d.data || []);
                this.rows = rows;
                this.navBadges = {...this.navBadges, [this.section]: rows.filter(r => this.overdue(r)).length};
                if (rows.length) {
                    const keys = Object.keys(rows[0]).filter(k => !HIDE.has(k) && typeof rows[0][k] !== 'object');
                    this.columns = keys.slice(0, 7);
                } else this.columns = [];
                const oid = new URLSearchParams(location.search).get('open');
                if (oid) { const r = rows.find(x => String(x.id) === oid); if (r) this.detail = r; }
                this.loading = false; this.lastLoad = new Date();
            });
        },
        sort(c) {
            if (this.sortKey === c) this.sortAsc = !this.sortAsc; else { this.sortKey = c; this.sortAsc = !/_at$|_date$|amount|price|value|qty|rate$|pct|percent|progress|revenue|ebitda|hours|salary|budget|cost/i.test(c); }
            try { localStorage.setItem('af_sort_' + this.section, JSON.stringify({k: this.sortKey, a: this.sortAsc})); } catch (e) {}
            const su = new URL(location.href); su.searchParams.set('sort', this.sortKey + ':' + (this.sortAsc ? 'asc' : 'desc')); history.replaceState(null, '', su);
        },
        renderRows() {
            const rows = this.sorted(this.filtered()).slice(0, this.limit);
            const mk = (r, i) => ({t: 'r', r, i, cells: [{t: 'cb'}, {t: 'n'}, ...this.visCols().map(c => ({t: 'c', c})), {t: 'act'}]});
            if (!this.groupBy) return rows.map((r, i) => mk(r, i));
            const buckets = new Map();
            rows.forEach(r => {
                const g = String(r[this.groupBy] ?? '');
                if (!buckets.has(g)) buckets.set(g, []);
                buckets.get(g).push(r);
            });
            const out = []; let i = 0;
            for (const [label, rs] of buckets) {
                out.push({t: 'h', label, count: rs.length, overdue: rs.filter(r => this.overdue(r)).length});
                if (!this.collapsedGroups[label]) rs.forEach(r => out.push(mk(r, i++)));
                else i += rs.length;
            }
            return out;
        },
        sorted(rows) {
            const k = this.sortKey || 'updated_at', dir = (this.sortKey ? this.sortAsc : false) ? 1 : -1;
            if (!rows.length || rows[0][k] === undefined) return rows;
            return [...rows].sort((a,b) => {
                const x = a[k], y = b[k];
                if (x === y) return 0;
                if (x === null || x === undefined) return 1;
                if (y === null || y === undefined) return -1;
                return (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), 'de')) * dir;
            });
        },
        sectionActions() {
            const A = {
                'leave-requests': [['Genehmigen','approved'],['Ablehnen','rejected']],
                'tasks': [['Starten','in_progress'],['Erledigen','done'],['Absagen','cancelled'],['Wieder öffnen','open']],
                'instructions': [['Erledigt','completed']],
                'inspections': [['Abschließen','completed'],['Absagen','cancelled']],
                'deadlines': [['Erledigt','completed']],
                'tenders': [['Schließen','closed']],
                'questions': [['Schließen','closed']],
                'projects': [['Aktivieren','active'],['Abschließen','done'],['Absagen','cancelled']],
                'measures': [['Starten','in_progress'],['Erledigt','done'],['Absagen','cancelled']],
                'production-orders': [['Starten','running'],['Fertig','done'],['Absagen','cancelled']],
                'risk-assessments': [['Akzeptieren','accepted'],['Gemindert','mitigated']],
                'strategies': [['Aktivieren','active'],['Archivieren','archived']],
            };
            return A[this.section] || [];
        },
        statusActions() {
            if (!this.detail || !('status' in this.detail)) return [];
            return this.sectionActions().filter(a => a[1] !== this.detail.status);
        },
        rowActions(row) {
            if (!row || !('status' in row)) return [];
            return this.sectionActions().filter(a => a[1] !== row.status);
        },
        applyRowStatus(row, s) {
            this.api(this.item().ep + '/' + row.id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:s})})
                .then(r => { if (r.ok) this.loadSection(true); else alert('Aktion fehlgeschlagen (HTTP '+r.status+')'); });
        },
        applyStatus(s) {
            this.api(this.item().ep + '/' + this.detail.id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:s})})
                .then(r => { if (r.ok) { this.detail = null; this.loadSection(); } else this.toast('Aktion fehlgeschlagen (HTTP '+r.status+')'); });
        },
        appName(a) {
            const p = a.expert_profile?.person;
            return p ? (p.first_name + ' ' + p.last_name).trim() : (this.lookups['expert_profiles'] || {})[a.expert_profile_id] || '—';
        },
        appStatus(s) { return ({submitted:'EINGEREICHT', shortlisted:'VORGEMERKT', awarded:'VERGEBEN', rejected:'ABGELEHNT'})[s] || s; },
        loadApps(tid) {
            this.api('/api/v1/tenders/' + tid).then(r => r.ok ? r.json() : {applications: []}).then(d => {
                this.apps = d.applications || [];
            });
        },
        submitApp() {
            if (!this.appForm.expert_profile_id || !this.detail) return;
            this.api('/api/v1/tenders/' + this.detail.id + '/applications', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(this.appForm)})
                .then(r => { if (r.ok) { this.appForm = {expert_profile_id: '', proposal: '', price: ''}; this.loadApps(this.detail.id); this.loadSection(); } else this.toast('Bewerbung fehlgeschlagen (HTTP '+r.status+')'); });
        },
        setAppStatus(id, s) {
            this.api('/api/v1/tender-applications/' + id, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:s})})
                .then(r => { if (r.ok) { this.loadApps(this.detail.id); this.loadSection(); } else this.toast('Aktion fehlgeschlagen (HTTP '+r.status+')'); });
        },
        deleteApp(id) {
            if (!confirm('Bewerbung löschen?')) return;
            this.api('/api/v1/tender-applications/' + id, {method:'DELETE'})
                .then(() => this.loadApps(this.detail.id));
        },
        loadAnswers(qid) {
            this.api('/api/v1/questions/' + qid).then(r => r.ok ? r.json() : {answers: []}).then(d => {
                this.answers = d.answers || [];
            });
        },
        postAnswer() {
            const body = this.answerText.trim();
            if (!body || !this.detail) return;
            this.api('/api/v1/questions/' + this.detail.id + '/answers', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({body})})
                .then(r => { if (r.ok) { this.answerText = ''; this.loadAnswers(this.detail.id); this.loadSection(); } else this.toast('Antwort fehlgeschlagen (HTTP '+r.status+')'); });
        },
        acceptAnswer(id) {
            this.api('/api/v1/answers/' + id + '/accept', {method:'POST'})
                .then(r => { if (r.ok) { this.loadAnswers(this.detail.id); this.loadSection(); } else this.toast('Aktion fehlgeschlagen (HTTP '+r.status+')'); });
        },
        deleteAnswer(id) {
            if (!confirm('Antwort löschen?')) return;
            this.api('/api/v1/answers/' + id, {method:'DELETE'})
                .then(() => this.loadAnswers(this.detail.id));
        },
        edgeNeighbors(id) {
            const names = this.lookups.graph_entities || {};
            return this.allEdges
                .filter(e => (String(e.from_entity_id) === String(id) || String(e.to_entity_id) === String(id))
                    && String(e.from_entity_id) !== String(this.detail && this.detail.id) && String(e.to_entity_id) !== String(this.detail && this.detail.id))
                .map(e => {
                    const out = String(e.from_entity_id) === String(id);
                    const other = out ? e.to_entity_id : e.from_entity_id;
                    return {dir: out ? '→' : '←', relation: e.relation, other, name: names[other] || other};
                });
        },
        loadEntityEdges(id) {
            this.api('/api/v1/graph-edges').then(r => r.ok ? r.json() : []).then(d => {
                const rs = Array.isArray(d) ? d : (d.data || []);
                this.allEdges = rs;
                this.entityEdges = rs.filter(e => String(e.from_entity_id) === String(id) || String(e.to_entity_id) === String(id))
                    .map(e => {
                        const out = String(e.from_entity_id) === String(id);
                        const other = out ? e.to_entity_id : e.from_entity_id;
                        const names = this.lookups.graph_entities || {};
                        return {dir: out ? '→' : '←', relation: e.relation, other, name: names[other] || other};
                    });
            }).catch(() => this.entityEdges = []);
        },
        loadDocVersions(id) {
            this.api('/api/v1/documents/' + id).then(r => r.ok ? r.json() : {versions: []}).then(d => {
                this.docVersions = (d.versions || []).slice().sort((a,b) => b.version - a.version);
            });
        },
        uploadVersion() {
            const f = this.$refs.versionFile && this.$refs.versionFile.files[0];
            if (!f || !this.detail) return;
            const fd = new FormData();
            fd.append('file', f);
            this.api('/api/v1/documents/' + this.detail.id + '/versions', {method:'POST', body:fd})
                .then(r => { if (r.ok) { this.$refs.versionFile.value = ''; this.loadDocVersions(this.detail.id); this.loadSection(); } else this.toast('Upload fehlgeschlagen (HTTP '+r.status+')'); });
        },
        dlPath(row) {
            if (this.section === 'data-objects') return '/api/v1/data-objects/' + row.id + '/download';
            return '/api/v1/documents/' + row.id + '/download';
        },
        async downloadDoc(row) {
            const r = await this.api(this.dlPath(row));
            if (!r.ok) { this.toast('Download fehlgeschlagen'); return; }
            const blob = await r.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            const dispo = r.headers.get('Content-Disposition') || '';
            const m = dispo.match(/filename="?([^";]+)/);
            a.download = m ? m[1] : (row.title || 'document');
            a.click();
            URL.revokeObjectURL(a.href);
        },
        async createTenant() {
            const name = prompt('Name des neuen Mandanten:');
            if (!name || !name.trim()) return;
            const r = await fetch('/api/v1/tenants', {method:'POST', headers:{
                'Authorization': 'Bearer {{ $apiToken }}', 'Accept':'application/json',
                'Content-Type':'application/json'},
                body: JSON.stringify({name: name.trim()})});
            if (!r.ok) { this.toast('Anlegen fehlgeschlagen (HTTP '+r.status+')'); return; }
            const t = await r.json();
            this.tenantList.push({id: t.id, name: t.name});
            this.tenant = t.id;
            this.loadSection();
        },
        loadColPrefs() {
            try { return JSON.parse(localStorage.getItem('af_cols_' + this.section) || '{}'); } catch (e) { return {}; }
        },
        toggleCol(c) {
            this.hiddenCols[c] = !this.hiddenCols[c];
            this.saveColPrefs();
        },
        saveColPrefs() {
            try { localStorage.setItem('allocore.cols.' + this.section, JSON.stringify(this.hiddenCols)); } catch (e) {}
            try { localStorage.setItem('af_cols_' + this.section, JSON.stringify(this.hiddenCols)); } catch (e) {}
        },
        visCols() { return this.columns.filter(c => !this.hiddenCols[c]); },
        filtered() {
            if (!this.rows) return [];
            let rs = this.rows;
            if (this.overdueOnly) rs = rs.filter(r => this.overdue(r));
            if (this.dueSoonOnly) rs = rs.filter(r => this.dueSoon(r));
            if (this.myOnly) rs = rs.filter(r => String(r.assignee_id || r.responsible_id || '') === String(this.meId));
            if (this.statusFilter) rs = rs.filter(r => String(r.status || '') === this.statusFilter);
            const q = this.query.trim().toLowerCase();
            if (!q) return rs;
            return rs.filter(r => Object.entries(r).some(([k, v]) => {
                if (String(v).toLowerCase().includes(q)) return true;
                const rn = this.resolveId(k, v);
                return rn && String(rn).toLowerCase().includes(q);
            }));
        },
        statusOpts() {
            if (!this.rows) return [];
            return [...new Set(this.rows.map(r => r.status).filter(Boolean))].sort();
        },
        statusLabel(s) { return STATUS_DE[String(s).toLowerCase()] || s; },
        refSection(k) {
            const t = FKMAP[k];
            if (!t) return null;
            const key = ({expert_profiles: 'expert-profiles', graph_entities: 'graph-entities'})[t] || t;
            return GROUPS.flatMap(g => g.items).some(i => i.key === key) ? key : null;
        },
        fmtD(row, k) {
            const rn = this.resolveId(k, row[k]);
            if (rn) return rn;
            if (k === 'status') return this.statusLabel(row[k]);
            const v = row[k];
            if (typeof v === 'number' && k !== 'id' && !k.endsWith('_id')) {
                if (/price|amount|value|budget|revenue|ebitda|cashflow|liquidity|capital|cost|salary|hourly|invested|valuation/i.test(k)) return v.toLocaleString('de-DE', {maximumFractionDigits: 2}) + ' €';
                if (/pct|percent|progress|rate$|quote/i.test(k)) return v.toLocaleString('de-DE') + ' %';
                return v.toLocaleString('de-DE');
            }
            return this.fmt(v);
        },
        numericCol(c) {
            const rows = this.filtered();
            const vals = rows.map(r => r[c]).filter(v => v !== null && v !== undefined && v !== '');
            if (!vals.length || vals.length < 2) return false;
            return vals.every(v => typeof v === 'number' || (typeof v === 'string' && /^-?\d+(\.\d+)?$/.test(v.trim())));
        },
        colSum(c) {
            if (!this.numericCol(c)) return '';
            const sum = this.filtered().reduce((a, r) => a + (Number(r[c]) || 0), 0);
            if (/price|amount|value|budget|revenue|ebitda|cashflow|liquidity|capital|cost|salary|hourly|invested|valuation/i.test(c)) return 'Σ ' + sum.toLocaleString('de-DE', {maximumFractionDigits: 2}) + ' €';
            if (/pct|percent|progress|rate$|quote/i.test(c)) return 'Ø ' + (sum / this.filtered().length).toLocaleString('de-DE', {maximumFractionDigits: 1}) + ' %';
            return 'Σ ' + sum.toLocaleString('de-DE', {maximumFractionDigits: 2});
        },
        hasSums() { return this.visCols().some(c => this.numericCol(c)); },
        selSums() {
            const sel = (this.rows || []).filter(r => this.selected[r.id]);
            if (!sel.length) return '';
            return this.visCols().filter(c => {
                const vals = sel.map(r => r[c]).filter(v => v !== null && v !== undefined && v !== '');
                return vals.length && vals.every(v => typeof v === 'number' || (typeof v === 'string' && /^-?\d+(\.\d+)?$/.test(v.trim())));
            }).slice(0, 2).map(c => {
                const sum = sel.reduce((a, r) => a + (Number(r[c]) || 0), 0);
                return 'Σ ' + this.label(c) + ' ' + sum.toLocaleString('de-DE', {maximumFractionDigits: 2}) + (/price|amount|value|budget|revenue|ebitda|cashflow|liquidity|capital|cost|salary|hourly|invested|valuation/i.test(c) ? ' €' : '');
            }).join(' · ');
        },
        statusColor(v) {
            const map = {open:'#CA8A04',pending:'#CA8A04',in_progress:'#CA8A04',running:'#CA8A04',queued:'#5B6B7E',scheduled:'#5B6B7E',planned:'#5B6B7E',on_hold:'#CA8A04',warning:'#CA8A04',medium:'#CA8A04',critical:'#A6362E',high:'#A6362E',cancelled:'#A6362E',rejected:'#A6362E',overdue:'#A6362E',done:'#2E7D5B',completed:'#2E7D5B',approved:'#2E7D5B',accepted:'#2E7D5B',mitigated:'#2E7D5B',active:'#2E7D5B',awarded:'#2E7D5B',answered:'#2E7D5B',closed:'#2E7D5B',info:'#5B6B7E',low:'#2E7D5B'};
            return map[String(v).toLowerCase()] || '#5B6B7E';
        },
        linkOf(v) {
            if (typeof v !== 'string') return null;
            if (/^https?:\/\/\S+$/.test(v)) return v;
            if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) return 'mailto:' + v;
            if (/^[+\d][\d\s\/()-]{5,}$/.test(v)) return 'tel:' + v.replace(/\s/g, '');
            return null;
        },
        detailKeys() {
            return Object.keys(this.detail || {}).filter(k => (k === 'id' || !HIDE.has(k)) && !(typeof this.detail[k] === 'object' && this.detail[k] !== null && this.detail[k + '_id'] !== undefined));
        },
        fmt(v) {
            if (v === null || v === undefined) return '—';
            if (typeof v === 'boolean') return v ? 'Ja' : 'Nein';
            if (Array.isArray(v)) return v.length ? v.join(', ') : '—';
            if (typeof v === 'object') return Object.entries(v).map(([k2, v2]) => k2 + ': ' + (v2 === null || v2 === undefined ? '—' : (typeof v2 === 'object' ? JSON.stringify(v2) : String(v2)))).join(' · ');
            if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(v)) {
                const d = new Date(v);
                if (!isNaN(d)) return d.toLocaleString('de-DE', {dateStyle: 'medium', timeStyle: 'short'});
            }
            return String(v);
        },
        ago(ts) {
            if (!ts) return '';
            const s = (Date.now() - new Date(ts)) / 1000;
            if (s < 90) return 'gerade eben';
            if (s < 3600) return 'vor ' + Math.round(s / 60) + ' Min.';
            if (s < 86400) return 'vor ' + Math.round(s / 3600) + ' Std.';
            return 'vor ' + Math.round(s / 86400) + ' T';
        },
        eventLink(e) {
            const p = e && e.event_properties;
            if (!p || !p.subject || p.subject.id === undefined || p.subject.id === null) return null;
            const g = String(e.event_type || '').split('.')[0];
            const M = {task:'tasks',company:'companies',person:'persons',document:'documents',instruction:'instructions',inspection:'inspections',deadline:'deadlines',tender:'tenders',question:'questions',machine:'machines',production_order:'production-orders',financial_report:'financial-reports',leave_request:'leave-requests',participation:'participations',data_object:'data-objects',portfolio:'portfolios',investment:'investments',graph_entity:'graph-entities',graph_edge:'graph-edges',strategy:'strategies',project:'projects',measure:'measures',ai_analysis:'ai-analyses',risk_assessment:'risk-assessments',operating_instruction:'operating-instructions'};
            return M[g] ? '/app/' + M[g] + '?tenant=' + this.tenant + '&open=' + p.subject.id : null;
        },
        eventGroup(t) {
            const g = String(t || '').split('.')[0];
            return {task: 'Aufgabe', company: 'Unternehmen', person: 'Person', document: 'Dokument', instruction: 'Unterweisung', inspection: 'Prüfung', deadline: 'Frist', tender: 'Ausschreibung', question: 'Frage', user: 'Benutzer'}[g] || g;
        },
        eventLabel(t) {
            const a = String(t || '').split('.').pop();
            return {created: 'erstellt', updated: 'aktualisiert', deleted: 'gelöscht', completed: 'abgeschlossen', approved: 'genehmigt', awarded: 'vergeben', uploaded: 'hochgeladen', answered: 'beantwortet', created_event: 'erstellt'}[a] || a;
        },
        createFields() {
            const SKIP = new Set([...HIDE, 'status', 'created_by', 'updated_by', 'completed_at', 'approved_at', 'approved_by', 'awarded_at', 'current_version', 'file_path', 'mime_type', 'size_bytes']);
            const LONGTEXT = new Set(['description','content','notes','measures','bio','body','proposal','result','message','answer','question','summary','goal','scope','rationale','findings']);
            const src = (this.rows && this.rows[0]) || {};
            return Object.keys(src).filter(k => !SKIP.has(k) && (!k.endsWith('_id') || FKMAP[k])).slice(0, 12).map(k => ({
                key: k,
                type: FKMAP[k] ? 'fk' : (typeof src[k] === 'boolean' ? 'checkbox' : (typeof src[k] === 'number' ? 'number' : (/_at$/.test(k) ? 'datetime-local' : (/_date$/.test(k) ? 'date' : (LONGTEXT.has(k) ? 'textarea' : 'text'))))),
                table: FKMAP[k] || null,
                req: ['name', 'title'].includes(k),
            }));
        },
        fkOptions(table) {
            const m = this.lookups[table] || {};
            return Object.entries(m).sort((a,b) => String(a[1]).localeCompare(String(b[1]), 'de'));
        },
        writable() { return !['events','ai-analyses','metrics'].includes(this.section); },
        canEdit() { return this.writable() && this.section !== 'data-objects'; },
        canCreate() { return this.section === 'ai-analyses' || this.writable(); },
        openCreate() {
            if (this.section === 'ai-analyses') {
                this.api('/api/v1/ai-analyses', {method:'POST', headers:{'Content-Type':'application/json'}, body:'{}'})
                    .then(r => { if (r.ok) this.loadSection(); else this.toast('Analyse fehlgeschlagen (HTTP '+r.status+')'); });
                return;
            }
            this.editing = null; this.form = {}; this.formError = ''; this.formDirty = false; this.showCreate = true;
            this.$nextTick(() => { const el = document.querySelector('#createForm input, #createForm select'); if (el) el.focus(); });
        },
        views() {
            try { return JSON.parse(localStorage.getItem('af_views_' + this.section) || '{}'); } catch (e) { return {}; }
        },
        saveView() {
            const name = prompt('Name der Ansicht:');
            if (!name) return;
            const all = this.views();
            all[name] = {query: this.query, statusFilter: this.statusFilter, overdueOnly: this.overdueOnly, dueSoonOnly: this.dueSoonOnly, myOnly: this.myOnly, groupBy: this.groupBy, sortKey: this.sortKey, sortAsc: this.sortAsc, hiddenCols: this.hiddenCols};
            localStorage.setItem('af_views_' + this.section, JSON.stringify(all));
            this.viewPicker = false;
        },
        applyView(name) {
            const v = this.views()[name];
            if (!v) return;
            this.query = v.query || ''; this.statusFilter = v.statusFilter || ''; this.overdueOnly = !!v.overdueOnly; this.dueSoonOnly = !!v.dueSoonOnly; this.myOnly = !!v.myOnly;
            this.groupBy = v.groupBy || ''; this.sortKey = v.sortKey || ''; this.sortAsc = v.sortAsc !== false; this.hiddenCols = v.hiddenCols || {};
            this.viewPicker = false; this.toast('Ansicht „' + name + '“ angewendet.');
        },
        deleteView(name) {
            const all = this.views(); delete all[name];
            localStorage.setItem('af_views_' + this.section, JSON.stringify(all));
            this.viewPicker = false; this.viewPicker = true;
        },
        copySel() {
            const sel = (this.rows || []).filter(r => this.selected[r.id]);
            if (!sel.length) return;
            const cols = this.visCols();
            const tsv = [cols.map(c => this.label(c)).join('\t'), ...sel.map(r => cols.map(c => { let v = r[c]; const rn = this.resolveId(c, v); v = rn || v; return v === null || v === undefined ? '' : String(v).replace(/\t|\n/g, ' '); }).join('\t'))].join('\n');
            navigator.clipboard.writeText(tsv).then(() => this.toast(sel.length + ' Zeilen kopiert.'));
        },
        exportCsv(only) {
            const rows = only || this.sorted(this.filtered());
            if (!rows.length) return;
            const esc = v => '"' + String(v === null || v === undefined ? '' : v).replace(/"/g, '""') + '"';
            const csvVal = (r, c) => {
                let v = r[c];
                const rn = this.resolveId(c, v);
                if (rn) return rn;
                if (typeof v === 'boolean') return v ? 'Ja' : 'Nein';
                if (['status','severity','type'].includes(c) && v) return STATUS_DE[String(v).toLowerCase()] || v;
                if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(v)) return new Date(v).toLocaleDateString('de-DE');
                return v;
            };
            const cols = this.visCols();
            const lines = [cols.map(c => esc(this.label(c))).join(';'), ...rows.map(r => cols.map(c => esc(csvVal(r, c))).join(';'))];
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['\ufeff' + lines.join('\n')], {type:'text/csv'}));
            a.download = this.section + '.csv';
            a.click();
        },
        canImport() { return this.writable() && !['documents','data-objects','ai-analyses','events','metrics'].includes(this.section); },
        async importCsv() {
            const text = this.importText.trim();
            if (!text) return;
            const lines = text.split(/\r?\n/).filter(l => l.trim());
            if (lines.length < 2) { this.importErr = true; this.importResult = 'Mindestens Kopfzeile + eine Datenzeile nötig.'; return; }
            const delim = lines[0].includes(';') ? ';' : ',';
            const splitLine = l => {
                const out = []; let cur = '', q = false;
                for (const ch of l) { if (ch === '"') { q = !q; continue; } if (ch === delim && !q) { out.push(cur); cur = ''; continue; } cur += ch; }
                out.push(cur); return out.map(s => s.trim());
            };
            const heads = splitLine(lines[0]);
            const valid = new Set(this.createFields().map(f => f.key));
            const unknown = heads.filter(h => !valid.has(h));
            if (unknown.length) { this.importErr = true; this.importResult = 'Unbekannte Spalten: ' + unknown.join(', '); return; }
            this.importing = true; this.importErr = false; this.importResult = '';
            let ok = 0, fail = 0;
            for (const l of lines.slice(1)) {
                const cells = splitLine(l); const body = {};
                heads.forEach((h, i) => { if (cells[i] !== undefined && cells[i] !== '') body[h] = cells[i]; });
                const r = await this.api(this.item().ep, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)}).catch(() => null);
                if (r && r.ok) ok++; else fail++;
            }
            this.importing = false;
            this.importErr = fail > 0;
            this.importResult = ok + ' importiert' + (fail ? ', ' + fail + ' fehlgeschlagen' : '') + '.';
            if (ok) this.loadSection();
        },
        openDuplicate() {
            this.editing = null;
            const fields = this.createFields();
            this.form = {};
            fields.forEach(f => { let v = this.detail[f.key]; if (f.type === 'datetime-local' && v) v = String(v).replace(' ', 'T').slice(0, 16); this.form[f.key] = v === null ? '' : v; });
            this.formError = ''; this.showCreate = true;
            fields.forEach(f => { const v = this.detail[f.key]; this.form[f.key] = v === null ? '' : v; });
            this.formError = ''; this.formDirty = false; this.showCreate = true;
            this.$nextTick(() => { const el = document.querySelector('#createForm input, #createForm select'); if (el) el.focus(); });
        },
        openEdit() {
            this.editing = this.detail;
            const fields = this.createFields();
            this.form = {};
            fields.forEach(f => { let v = this.editing[f.key]; if (f.type === 'datetime-local' && v) v = String(v).replace(' ', 'T').slice(0, 16); this.form[f.key] = v === null ? '' : v; });
            this.formError = ''; this.showCreate = true;
            fields.forEach(f => { const v = this.editing[f.key]; this.form[f.key] = v === null ? '' : v; });
            this.formError = ''; this.formDirty = false; this.showCreate = true;
            this.$nextTick(() => { const el = document.querySelector('#createForm input, #createForm select'); if (el) el.focus(); });
        },
        submitCreate() {
            this.formError = '';
            const body = {};
            this.createFields().forEach(f => {
                let v = this.form[f.key];
                if (f.type === 'datetime-local' && v) v = String(v).replace('T', ' ') + (String(v).length === 16 ? ':00' : '');
                if (v !== undefined && v !== '') body[f.key] = v;
            });
            const method = this.editing ? 'PUT' : 'POST';
            const url = this.item().ep + (this.editing ? '/' + this.editing.id : '');
            let fetchOpts = {method, headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)};
            if (['documents','data-objects'].includes(this.section) && !this.editing) {
                const f = this.$refs.fileInput && this.$refs.fileInput.files[0];
                if (!f) { this.formError = 'Bitte eine Datei wählen.'; return; }
                const fd = new FormData();
                fd.append(this.section === 'data-objects' ? 'name' : 'title', body.title || body.name || f.name);
                if (body.category) fd.append('category', body.category);
                fd.append('file', f);
                fetchOpts = {method: 'POST', body: fd};
            }
            this.api(url, fetchOpts)
                .then(r => {
                    if (!r.ok) { this.formError = 'HTTP '+r.status+' — Pflichtfelder fehlen?'; return null; }
                    this.showCreate = false; this.formDirty = false; this.detail = null; this.editing = null; this.loadSection(); return r.json();
                    if (!r.ok) {
                        return r.json().then(d => {
                            const errs = d && d.errors ? Object.entries(d.errors).map(([k, ms]) => this.label(k) + ': ' + (Array.isArray(ms) ? ms[0] : ms)).join(' · ') : null;
                            this.formError = errs || (d && d.message ? d.message : 'HTTP ' + r.status + ' — Pflichtfelder fehlen?');
                        }).catch(() => { this.formError = 'HTTP ' + r.status + ' — Pflichtfelder fehlen?'; });
                    }
                    this.showCreate = false; this.detail = null; this.editing = null; this.loadSection(); return r.json();
                });
        },
        closeCreate() {
            if (this.showCreate && this.formDirty && !confirm('Ungespeicherte Änderungen verwerfen?')) return;
            this.showCreate = false; this.formDirty = false;
        },
        toggleSel(id) {
            const s = Object.assign({}, this.selected);
            if (s[id]) delete s[id]; else s[id] = true;
            this.selected = s;
        },
        toggleAll(on) {
            const s = {};
            if (on) this.sorted(this.filtered()).forEach(r => s[r.id] = true);
            this.selected = s;
        },
        selCount() { return Object.keys(this.selected).length; },
        bulkStatus(s) {
            const ids = Object.keys(this.selected);
            if (!ids.length) return;
            Promise.all(ids.map(id => this.api(this.item().ep + '/' + id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:s})})))
                .then(() => { this.selected = {}; this.loadSection(); });
        },
        bulkDelete() {
            const ids = Object.keys(this.selected);
            if (!ids.length || !confirm(ids.length + ' Einträge wirklich löschen?')) return;
            const snapshots = (this.rows || []).filter(r => this.selected[r.id]).map(r => ({...r}));
            Promise.all(ids.map(id => this.api(this.item().ep + '/' + id, {method:'DELETE'})))
                .then(() => {
                    this.selected = {}; this.loadSection();
                    this.toast(ids.length + ' gelöscht.', {label: 'Rückgängig', fn: () => this.restoreRows(snapshots)});
                });
        },
        copyVal(v) {
            const s = typeof v === 'object' ? JSON.stringify(v) : String(v);
            navigator.clipboard && navigator.clipboard.writeText(s).then(() => this.toast('Kopiert.')).catch(() => this.toast('Kopieren fehlgeschlagen.'));
        },
        restoreRows(rows) {
            Promise.all(rows.map(r => {
                const p = {};
                Object.keys(r).forEach(k => {
                    const v = r[k];
                    if (['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at'].includes(k)) return;
                    if (v === null || typeof v !== 'object') p[k] = v;
                });
                return this.api(this.item().ep, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(p)});
            })).then(rs => {
                this.toast(rs.every(r => r.ok) ? 'Wiederhergestellt.' : 'Teilweise fehlgeschlagen.');
                this.loadSection();
            });
        },
        deleteRow(row) {
            if (!row || !row.id || !confirm('Wirklich löschen?')) return;
            const snapshot = {...row};
            this.api(this.item().ep + '/' + row.id, {method: 'DELETE'}).then(() => {
                this.detail = null; this.loadSection();
                this.toast('Gelöscht.', {label: 'Rückgängig', fn: () => this.restoreRow(snapshot)});
            });
        },
        restoreRow(row) {
            const p = {};
            Object.keys(row).forEach(k => {
                const v = row[k];
                if (['id', 'tenant_id', 'created_at', 'updated_at', 'deleted_at'].includes(k)) return;
                if (v === null || typeof v !== 'object') p[k] = v;
            });
            this.api(this.item().ep, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(p)})
                .then(r => { this.toast(r.ok ? 'Wiederhergestellt.' : 'Wiederherstellen fehlgeschlagen.'); if (r.ok) this.loadSection(); });
        },
        loadLookups() {
            const SPECS = {
                persons: ['/api/v1/persons', r => (r.first_name||'')+' '+(r.last_name||'')],
                companies: ['/api/v1/companies', r => r.name],
                users: ['/api/v1/users', r => r.name],
                machines: ['/api/v1/machines', r => r.name],
                projects: ['/api/v1/projects', r => r.name],
                strategies: ['/api/v1/strategies', r => r.name],
                portfolios: ['/api/v1/portfolios', r => r.name],
                tenders: ['/api/v1/tenders', r => r.title],
                questions: ['/api/v1/questions', r => r.title],
                documents: ['/api/v1/documents', r => r.title],
                expert_profiles: ['/api/v1/expert-profiles', r => r.headline||r.id],
                graph_entities: ['/api/v1/graph-entities', r => r.name||r.id],
            };
            Object.keys(SPECS).forEach(k => {
                if (this.lookups[k]) return;
                this.api(SPECS[k][0]).then(r => r.ok ? r.json() : (Array.isArray(r)?r:{data:[]})).then(d => {
                    const rows = Array.isArray(d) ? d : (d.data || []);
                    const m = {};
                    rows.forEach(r => { m[r.id] = SPECS[k][1](r); });
                    this.lookups[k] = m;
                });
            });
        },
        resolveId(c, v) {
            const lk = FKMAP[c];
            if (!lk || !this.lookups[lk]) return null;
            return this.lookups[lk][v] || null;
        },
        kbd(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); this.palette = !this.palette; this.paletteQ = ''; return; }
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            const tag = (e.target.tagName || '').toLowerCase();
            if (['input', 'textarea', 'select'].includes(tag) || e.target.isContentEditable) return;
            if (e.key === '/') { e.preventDefault(); this.$refs.search && this.$refs.search.focus(); }
            else if (e.key === '?') { e.preventDefault(); this.kbdHelp = !this.kbdHelp; }
            else if (e.key === 'n') { if (this.canCreate() && !this.showCreate && !this.detail && !this.palette) this.openCreate(); }
            else if (e.key === 'e') { if (this.detail && !this.showCreate && this.canEdit()) this.openEdit(); }
            else if (e.key === 'r') { if (!this.detail && !this.showCreate && !this.palette && !['dashboard','executive'].includes(this.section)) this.loadSection(true); }
            else if (e.key === 'd') { if (this.detail && !this.showCreate && this.section !== 'documents' && this.canEdit()) this.openDuplicate(); }
            else if (e.key === 'i') { if (!this.detail && !this.showCreate && !this.palette && this.canImport()) { this.showImport = true; this.importText = ''; this.importResult = ''; } }
            else if (e.key === 'p') { if (this.detail && !this.showCreate) this.copyLink(); }
            else if (e.key === 'o') { if (!this.detail && !this.showCreate && !this.palette && this.filtered().length) this.detail = this.filtered()[0]; }
            else if (e.key === 'l') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.filtered().length > this.limit) this.limit = this.filtered().length; }
            else if (e.key === 't') { this.toggleDark(); }
            else if (e.key === 'x') { if (!this.detail && !this.showCreate && !this.palette && (this.query || this.statusFilter || this.overdueOnly || this.dueSoonOnly)) { this.query = ''; this.statusFilter = ''; this.overdueOnly = false; this.dueSoonOnly = false; } }
            else if (e.key === 'c') { if (!this.detail && !this.showCreate && !this.palette && this.rows) this.colPicker = !this.colPicker; }
            else if (e.key === 's') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.rows.some(r => this.dueSoon(r))) this.dueSoonOnly = !this.dueSoonOnly; }
            else if (e.key === 'u') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.rows.some(r => this.overdue(r))) this.overdueOnly = !this.overdueOnly; }
            else if (e.key === '.') { if (!this.detail && !this.showCreate && !this.palette && this.section !== 'dashboard') window.location.href = '/app/dashboard' + (this.tenant ? '?tenant=' + this.tenant : ''); }
            else if (/^[1-9]$/.test(e.key)) { if (!this.detail && !this.showCreate && !this.palette && this.sorted(this.filtered()).length >= +e.key) this.detail = this.sorted(this.filtered())[e.key - 1]; }
        },
        toggleDark() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try { localStorage.setItem('af_dark', this.dark ? '1' : '0'); } catch (e) {}
        },
        copyLink() {
            const url = location.origin + '/app/' + this.section + '?tenant=' + this.tenant + '&open=' + this.detail.id;
            navigator.clipboard.writeText(url).then(() => { this.linkCopied = true; setTimeout(() => this.linkCopied = false, 1500); });
        },
        copyJson() {
            if (!this.detail) return;
            navigator.clipboard.writeText(JSON.stringify(this.detail, null, 2)).then(() => { this.jsonCopied = true; setTimeout(() => this.jsonCopied = false, 1500); });
        },
        hasNav(dir) {
            if (!this.detail) return false;
            const rs = this.sorted(this.filtered());
            const i = rs.findIndex(r => String(r.id) === String(this.detail.id));
            return i >= 0 && !!rs[i + dir];
        },
        navDetail(dir) {
            const rs = this.sorted(this.filtered());
            const i = rs.findIndex(r => String(r.id) === String(this.detail.id));
            const n = rs[i + dir];
            if (n) this.detail = n;
        },
        isNew(row) { const t = row.updated_at || row.created_at; return t && (Date.now() - new Date(t).getTime()) < 86400000; },
        detailPos() {
            if (!this.detail) return '';
            const rs = this.sorted(this.filtered());
            const i = rs.findIndex(r => String(r.id) === String(this.detail.id));
            return i < 0 ? '' : (i + 1) + ' / ' + rs.length;
        },
        dueKey(row) { return ['due_at','deadline','ends_on','due_date','end_date','next_due_at'].find(k => row[k]); },
        isOpenStatus(row) {
            const OPEN = ['open','pending','in_progress','running','queued','scheduled','planned','active','submitted','shortlisted','draft','on_hold'];
            return !row.status || OPEN.includes(String(row.status));
        },
        overdue(row) {
            const key = this.dueKey(row);
            if (!key || !this.isOpenStatus(row)) return false;
            const d = new Date(row[key]);
            return !isNaN(d) && d < new Date();
        },
        dueSoon(row) {
            const key = this.dueKey(row);
            if (!key || !this.isOpenStatus(row)) return false;
            const d = new Date(row[key]), now = new Date();
            return !isNaN(d) && d >= now && d <= new Date(now.getTime() + 7*864e5);
        },
        eintrag(n) { return n === 1 ? 'Eintrag' : 'Einträge'; },
        detailTitle() {
            if (!this.detail) return this.title() + ' · Details';
            const d = this.detail;
            const name = d.name || d.title || d.headline || d.subject || d.order_no || (d.first_name ? [d.first_name, d.last_name].filter(Boolean).join(' ') : null) || d.file_name || d.email;
            return name ? this.title() + ' · ' + name : this.title() + ' · Details';
        },
        label(c) {
            const L = {name:'Name',title:'Titel',first_name:'Vorname',last_name:'Nachname',email:'E-Mail',phone:'Telefon',type:'Typ',status:'Status',description:'Beschreibung',content:'Inhalt',category:'Kategorie',subject:'Betreff',area:'Bereich',hazard:'Gefährdung',risk_level:'Risikostufe',measures:'Maßnahmen',result:'Ergebnis',notes:'Notizen',progress:'Fortschritt',quantity:'Menge',order_no:'Auftrag-Nr.',product:'Produkt',scrap_qty:'Ausschuss',headline:'Schlagzeile',bio:'Bio',skills:'Skills',hourly_rate:'Stundensatz',budget:'Budget',price:'Preis',proposal:'Angebot',stake_pct:'Anteil %',invested_amount:'Investiert',current_valuation:'Bewertung',capital_need:'Kapitalbedarf',revenue:'Umsatz',cashflow:'Cashflow',ebitda:'EBITDA',liquidity:'Liquidität',period:'Periode',legal_form:'Rechtsform',street:'Straße',zip:'PLZ',city:'Stadt',country:'Land',version:'Version',valid_from:'Gültig ab',interval_months:'Intervall (Mon.)',capacity_units_per_day:'Kapazität/Tag',asset_class:'Anlageklasse',cost_basis:'Kostenbasis',current_value:'Aktueller Wert',currency:'Währung',due_at:'Fällig',deadline_at:'Frist',scheduled_at:'Geplant',starts_on:'Von',ends_on:'Bis',starts_at:'Start',ends_at:'Ende',acquired_at:'Erworben',valued_at:'Bewertet am',body:'Inhalt',is_accepted:'Akzeptiert',document_id:'Dokument'};
            if (!L[c] && c.endsWith('_id')) {
                const F = {person_id:'Person',company_id:'Unternehmen',machine_id:'Maschine',task_id:'Aufgabe',question_id:'Frage',answer_id:'Antwort',tender_id:'Ausschreibung',project_id:'Projekt',strategy_id:'Strategie',measure_id:'Maßnahme',portfolio_id:'Portfolio',investment_id:'Investment',participation_id:'Beteiligung',expert_profile_id:'Experte',instruction_id:'Unterweisung',inspection_id:'Prüfung',risk_assessment_id:'Gefährdungsbeurteilung',financial_report_id:'Finanzbericht',leave_request_id:'Abwesenheit',parent_id:'Übergeordnet',responsible_id:'Verantwortlich',assignee_id:'Zugewiesen',created_by:'Erstellt von',updated_by:'Geändert von',approved_by:'Genehmigt von',awarded_by:'Vergeben von',user_id:'Benutzer',document_id:'Dokument'};
                if (F[c]) return F[c];
                return c.slice(0, -3).replace(/_/g,' ').replace(/^\w/, s => s.toUpperCase());
            }
            return L[c] || c.replace(/_/g,' ').replace(/^\w/, s => s.toUpperCase());
        },
        cell(row, c) {
            let v = row[c];
            if (v === null || v === undefined) return '—';
            if (c === 'id' && typeof v === 'string' && v.length > 8) return `<button onclick="event.stopPropagation();navigator.clipboard.writeText('${v}')" title="ID kopieren: ${v}" class="font-mono text-[11px] text-[#5B6B7E] hover:text-[#CA8A04]">${v.slice(0, 8)}…</button>`;
            const rn = this.resolveId(c, v);
            if (rn) return `<span title="${v}">${rn}</span>`;
            if (typeof v === 'boolean') return v ? 'Ja' : 'Nein';
            if (typeof v === 'string' && /^\d+(\.\d+)?$/.test(v) && c !== 'id' && !c.endsWith('_id') && !/_date|_at|no$|number|phone|zip/i.test(c)) v = parseFloat(v);
            if (typeof v === 'number' && c !== 'id' && !c.endsWith('_id') && Number.isFinite(v)) {
                if (/_?size_?bytes?$|bytes/i.test(c)) {
                    const u = ['B','KB','MB','GB']; let s = v, i = 0;
                    while (s >= 1024 && i < 3) { s /= 1024; i++; }
                    return s.toLocaleString('de-DE', {maximumFractionDigits: i ? 1 : 0}) + ' ' + u[i];
                }
                if (/progress|pct|percent|rate$|quote|completion/i.test(c) && v >= 0 && v <= 100) {
                    const col = v >= 100 ? '#2E7D5B' : v >= 50 ? '#CA8A04' : '#A6362E';
                    return `<span class="inline-flex items-center gap-2"><span class="inline-block w-16 h-1.5 rounded-full bg-[#E4E9F0] overflow-hidden"><span class="block h-full rounded-full" style="width:${Math.min(100,v)}%;background:${col}"></span></span><span>${v}%</span></span>`;
                }
                const f = Math.abs(v) % 1 ? v.toLocaleString('de-DE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : v.toLocaleString('de-DE');
                return /price|amount|value|cost|rate|budget|revenue|ebitda|salary|euro|eur/i.test(c) ? f + ' €' : f;
            }
            if (c === 'status' || c === 'severity' || c === 'type') {
                const map = {open:'#CA8A04',pending:'#CA8A04',in_progress:'#CA8A04',running:'#CA8A04',queued:'#5B6B7E',scheduled:'#5B6B7E',planned:'#5B6B7E',on_hold:'#CA8A04',critical:'#A6362E',high:'#A6362E',warning:'#CA8A04',cancelled:'#A6362E',rejected:'#A6362E',done:'#2E7D5B',completed:'#2E7D5B',approved:'#2E7D5B',accepted:'#2E7D5B',mitigated:'#2E7D5B',active:'#2E7D5B',awarded:'#2E7D5B',info:'#5B6B7E'};
                const col = map[String(v).toLowerCase()] || '#5B6B7E';
                const txt = STATUS_DE[String(v).toLowerCase()] || v;
                return `<span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full" style="background:${col}"></span>${txt}</span>`;
            }
            if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(v)) {
                const d = new Date(v);
                let out = d.toLocaleDateString('de-DE', {weekday: 'short'}) + ', ' + d.toLocaleDateString('de-DE');
                if (d.getHours() !== 0 || d.getMinutes() !== 0) out += ' ' + d.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});
                if (/due|deadline|scheduled/.test(c)) {
                    const days = Math.ceil((d - Date.now()) / 86400000);
                    const rel = days === -1 ? 'gestern' : days === 0 ? 'heute' : days === 1 ? 'morgen' : days < 0 ? `vor ${-days} T` : `in ${days} T`;
                    if (days < 0) out += ` <span class="text-[#A6362E]">(${rel})</span>`;
                    else if (days <= 7) out += ` <span class="text-[#CA8A04]">(${rel})</span>`;
                }
                return out;
            }
            if (typeof v === 'string' && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) && /mail/i.test(c))
                return `<a href="mailto:${v}" @click.stop class="text-[#CA8A04] hover:underline">${v}</a>`;
            if (typeof v === 'string' && /^[+0-9][0-9\s\/()-]{5,}$/.test(v) && /phone|tel|mobile/i.test(c))
                return `<a href="tel:${v.replace(/[^+0-9]/g,'')}" @click.stop class="text-[#CA8A04] hover:underline">${v}</a>`;
            if (typeof v === 'string' && /^https?:\/\/\S+$/.test(v))
                return `<a href="${v}" target="_blank" rel="noopener" @click.stop class="text-[#CA8A04] hover:underline">${v.length > 60 ? v.slice(0,60)+'…' : v}</a>`;
            if (typeof v === 'string' && v.length > 80) return `<span title="${String(v).replace(/"/g,'&quot;')}">${v.slice(0,80)}…</span>`;
            if (typeof v === 'string' && this.query) {
                const q = this.query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                v = String(v).replace(new RegExp('(' + q + ')', 'ig'), '<mark class="bg-[#FACC15]/40 rounded-sm px-0.5">$1</mark>');
            }
            return v;
        },
    }
}
</script>
</body>
</html>
