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
</head>
<body class="font-sans antialiased bg-[#F6F7F9] text-[#1A2433]">
<div class="min-h-screen flex flex-col lg:flex-row" x-data="workspace(@js($section))" x-cloak
     @keydown.escape.window="detail = null; showCreate = false; navOpen = false; palette = false"
     @keydown.arrowright.window="detail && navDetail(1)"
     @keydown.arrowleft.window="detail && navDetail(-1)"
     @keydown.window="
        if (($event.ctrlKey || $event.metaKey) && $event.key === 'k') { $event.preventDefault(); palette = !palette; paletteQ = ''; }
        else if (!$event.ctrlKey && !$event.metaKey && !$event.altKey && !/^(input|textarea|select)$/i.test($event.target.tagName)) {
            if ($event.key === '/') { $event.preventDefault(); if ($refs.search) $refs.search.focus(); }
            else if ($event.key === 'n' && !detail && !showCreate && !palette && canCreate()) openCreate();
        }">

    {{-- Mobile top bar --}}
    <div class="lg:hidden flex items-center justify-between px-4 h-14 bg-[#0B0B0F] text-white sticky top-0 z-30 shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <img src="{{ asset('logo-mark.png') }}" alt="ALLOCORE" class="h-7 w-auto">
            <span class="font-semibold tracking-tight">ALLO<span class="text-[#FACC15]">CORE</span></span>
        </a>
        <button @click="navOpen = !navOpen" class="p-2 -mr-2 text-[#9CA3AF] hover:text-white" title="Menü">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
    </div>

    {{-- Backdrop (mobile) --}}
    <div x-show="navOpen" @click="navOpen = false" class="lg:hidden fixed inset-0 bg-black/50 z-30" x-transition.opacity></div>

    {{-- Sidebar --}}
    <aside class="bg-[#0B0B0F] text-white w-64 flex flex-col fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-200 lg:translate-x-0 lg:static lg:min-h-screen lg:sticky lg:top-0 lg:shrink-0"
           :class="navOpen ? 'translate-x-0' : '-translate-x-full'">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-5 h-16 border-b border-[#1A1A1F]">
            <img src="{{ asset('logo-mark.png') }}" alt="ALLOCORE" class="h-9 w-auto">
            <span class="font-semibold tracking-tight text-[17px]">ALLO<span class="text-[#FACC15]">CORE</span></span>
        </a>

        {{-- Tenant picker --}}
        <div class="px-4 py-4 border-b border-[#1A1A1F]">
            <label class="block text-[10px] font-medium tracking-wide text-[#9CA3AF] mb-1.5">MANDANT</label>
            <select x-model="tenant" @change="loadSection()"
                    class="w-full rounded-lg bg-[#1A1A1F] border-[#2A2A31] text-white text-sm py-2 focus:border-[#FACC15] focus:ring-[#FACC15]/30">
                <option value="">— wählen —</option>
                <template x-for="t in tenantList" :key="t.id">
                    <option :value="t.id" x-text="t.name"></option>
                </template>
            </select>
            <button @click="createTenant()" class="mt-2 w-full text-left text-[11px] text-[#9CA3AF] hover:text-[#FACC15]">+ Neuer Mandant</button>
        </div>

        <nav class="flex-1 overflow-y-auto py-3 text-[13px]">
            <template x-for="group in groups" :key="group.label">
                <div class="mb-1">
                    <button @click="collapsed[group.label] = !collapsed[group.label]"
                            class="w-full flex items-center justify-between px-5 pt-4 pb-1.5 text-[10px] font-semibold tracking-widest text-[#6B7280] hover:text-[#9CA3AF] transition">
                        <span x-text="group.label"></span>
                        <svg class="w-2.5 h-2.5 transition-transform" :class="collapsed[group.label] && !group.items.some(i => i.key === section) ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <template x-for="item in group.items" :key="item.key">
                        <a x-show="!collapsed[group.label] || group.items.some(i => i.key === section)"
                           :href="'/app/' + item.key + (tenant ? '?tenant='+tenant : '')"
                           class="flex items-center gap-3 px-5 py-2 transition"
                           :class="section === item.key
                               ? 'text-white bg-[#1A1A1F] border-r-2 border-[#FACC15]'
                               : 'text-[#9CA3AF] hover:text-white hover:bg-[#141419]'">
                            <span x-text="item.label"></span>
                        </a>
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
                <h1 class="font-semibold text-lg tracking-tight text-[#0B0B0F]" x-text="title()"></h1>
                <p class="text-xs text-[#5B6B7E]" x-text="subtitle()"></p>
            </div>
            <span x-show="loading" class="text-xs text-[#9CA3AF]">Lädt…</span>
        </header>

        <div class="p-6 space-y-5">
            <div x-show="error" class="bg-white border border-[#A6362E]/40 rounded-xl px-4 py-3 text-sm text-[#A6362E]" x-text="error"></div>

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
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                        <template x-for="m in kpiCards" :key="m.key">
                            <a :href="m.to ? '/app/' + m.to + (tenant ? '?tenant='+tenant : '') : '#'"
                               class="bg-white border border-[#E4E9F0] rounded-xl px-5 py-4 block transition"
                               :class="m.to ? 'hover:border-[#CA8A04]/60 hover:shadow-sm cursor-pointer' : 'cursor-default'">
                                <div class="text-[11px] font-medium text-[#5B6B7E]" x-text="m.label"></div>
                                <div class="mt-1.5 font-mono text-2xl font-semibold tracking-tight text-[#0B0B0F]" x-text="metric(m.key)"></div>
                                <div class="mt-0.5 text-[11px] font-mono" :class="trend(m.key).direction === 'up' ? 'text-[#2E7D5B]' : (trend(m.key).direction === 'down' ? 'text-[#A6362E]' : 'text-[#9CA3AF]')" x-text="trend(m.key).delta === null ? '' : (trend(m.key).direction === 'up' ? '▲ +' : (trend(m.key).direction === 'down' ? '▼ ' : '')) + (trend(m.key).delta ?? '')"></div>
                                <div class="mt-2 h-0.5 w-8 rounded-full bg-[#FACC15]"></div>
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
                    <div x-show="dueSoon.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <a :href="'/app/deadlines?tenant=' + tenant" class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E] flex items-center justify-between hover:text-[#CA8A04]">
                            Nächste Fristen
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                        <div class="divide-y divide-[#F0F3F7]">
                            <template x-for="d in dueSoon" :key="d.id">
                                <a :href="'/app/deadlines?tenant=' + tenant + '&open=' + d.id" class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-[#FAFBFC]">
                                    <span class="text-sm text-[#1A2433] truncate" x-text="d.title"></span>
                                    <span class="text-[11px] font-mono shrink-0" :class="new Date(d.due_at) < new Date() ? 'text-[#A6362E]' : 'text-[#9CA3AF]'" x-text="new Date(d.due_at).toLocaleDateString('de-DE')"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                    <div x-show="events.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E]">Letzte Ereignisse</div>
                        <div class="divide-y divide-[#F0F3F7] max-h-64 overflow-y-auto">
                            <template x-for="(e, i) in events" :key="i">
                                <div class="px-5 py-2.5 flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#1A2433] truncate" x-text="e.event_type"></span>
                                    <span class="text-[11px] text-[#9CA3AF] font-mono shrink-0" x-text="fmt(e.created_at)"></span>
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
                    <div x-show="rows !== null" class="flex items-center justify-between gap-3 px-5 py-3 border-b border-[#E4E9F0]">
                        <span class="text-xs text-[#5B6B7E] shrink-0" x-text="filtered().length + ' / ' + (rows ? rows.length : 0) + ' Einträge'"></span>
                        <input x-ref="search" x-model="query" placeholder="Suchen… (/)" class="w-48 rounded-lg border-[#D6DEE9] text-xs py-1.5 focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                        <div class="relative shrink-0">
                            <button @click="colPicker = !colPicker" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition">Spalten</button>
                            <div x-show="colPicker" @click.outside="colPicker = false" class="absolute right-0 mt-1.5 w-48 bg-white border border-[#E4E9F0] rounded-lg shadow-lg py-1 z-20 max-h-64 overflow-y-auto" style="display:none">
                                <template x-for="c in columns" :key="c">
                                    <label class="flex items-center gap-2 px-3 py-1.5 text-xs text-[#1A2433] hover:bg-[#FAFBFC] cursor-pointer">
                                        <input type="checkbox" :checked="!hiddenCols[c]" @change="toggleCol(c)" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                        <span x-text="label(c)"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <button @click="exportCsv()" title="CSV exportieren" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition shrink-0">CSV</button>
                        <button x-show="canCreate()" @click="openCreate()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition shrink-0">+ Neu</button>
                    </div>
                    <div x-show="rows && (statusOpts().length > 1 || rows.some(r => overdue(r)))" class="flex flex-wrap items-center gap-1.5 px-5 py-2 border-b border-[#E4E9F0]">
                        <button x-show="rows.some(r => overdue(r))" @click="overdueOnly = !overdueOnly" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="overdueOnly ? 'border-[#A6362E] bg-[#A6362E] text-white' : 'border-[#A6362E]/40 text-[#A6362E] hover:bg-[#A6362E]/5'">Überfällig</button>
                        <button x-show="rows.some(r => dueSoon(r))" @click="dueSoonOnly = !dueSoonOnly" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="dueSoonOnly ? 'border-[#B45309] bg-[#B45309] text-white' : 'border-[#B45309]/40 text-[#B45309] hover:bg-[#B45309]/5'">≤ 7 Tage</button>
                        <button @click="statusFilter = ''" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="statusFilter === '' ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'">Alle</button>
                        <template x-for="s in statusOpts()" :key="s">
                            <button @click="statusFilter = statusFilter === s ? '' : s" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                    :class="statusFilter === s ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                    x-text="statusLabel(s)"></button>
                        </template>
                    </div>
                    <div x-show="rows && filtered().length === 0" class="px-6 py-12 text-center">
                        <p class="text-sm text-[#5B6B7E]" x-text="query || statusFilter || overdueOnly || dueSoonOnly ? 'Keine Einträge für diese Filter.' : 'Keine Einträge vorhanden.'"></p>
                        <button x-show="query || statusFilter || overdueOnly || dueSoonOnly" @click="query = ''; statusFilter = ''; overdueOnly = false; dueSoonOnly = false"
                                class="mt-3 text-xs px-3.5 py-2 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition">Filter zurücksetzen</button>
                        <button x-show="canCreate() && !query && !statusFilter && !overdueOnly && !dueSoonOnly" @click="openCreate()"
                                class="mt-3 text-xs px-3.5 py-2 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition">+ Ersten Eintrag erstellen</button>
                    </div>
                    <table x-show="rows && filtered().length" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[#E4E9F0] bg-[#FAFBFC] text-left">
                                <template x-for="c in visCols()" :key="c">
                                    <th @click="sort(c)" class="px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E] cursor-pointer select-none hover:text-[#0B0B0F]">
                                        <span x-text="label(c)"></span><span class="ml-1 text-[#CA8A04]" x-text="sortKey===c ? (sortAsc?'▲':'▼') : ''"></span>
                                    </th>
                                </template>
                                <th x-show="sectionActions().length" class="px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E] w-28">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in sorted(filtered()).slice(0, limit)" :key="idx">
                                <tr @click="detail = row" :class="overdue(row) ? 'bg-[#A6362E]/5' : ''" class="border-b border-[#F0F3F7] last:border-b-0 hover:bg-[#FAFBFC] cursor-pointer">
                                    <template x-for="c in visCols()" :key="c">
                                        <td class="px-5 py-3 text-[#1A2433]" x-html="cell(row, c)"></td>
                                    </template>
                                    <td x-show="sectionActions().length" @click.stop class="px-5 py-3">
                                        <div class="flex gap-1">
                                            <template x-for="a in rowActions(row).slice(0, 2)" :key="a[1]">
                                                <button @click="applyRowStatus(row, a[1])" class="text-[10px] px-2 py-1 rounded-md border border-[#CA8A04]/50 text-[#CA8A04] hover:bg-[#CA8A04]/10 whitespace-nowrap" x-text="a[0]"></button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <div x-show="filtered().length > limit" class="px-5 py-3 border-t border-[#E4E9F0] text-center">
                        <button @click="limit += 100" class="text-xs text-[#CA8A04] hover:underline">
                            Mehr laden (<span x-text="filtered().length - limit"></span> weitere)
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Detail drawer --}}
        <div x-show="detail" class="fixed inset-0 z-40" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="detail = null"></div>
            <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white shadow-xl flex flex-col">
                <div class="px-6 py-4 border-b border-[#E4E9F0] flex items-center justify-between">
                    <h2 class="font-semibold text-[#0B0B0F]" x-text="title() + ' · Details'"></h2>
                    <div class="flex items-center gap-1">
                        <button @click="navDetail(-1)" class="p-1.5 rounded-lg text-[#9CA3AF] hover:text-[#0B0B0F] hover:bg-[#F0F3F7] transition" title="Vorheriger (←)"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
                        <button @click="navDetail(1)" class="p-1.5 rounded-lg text-[#9CA3AF] hover:text-[#0B0B0F] hover:bg-[#F0F3F7] transition" title="Nächster (→)"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                    </div>
                    <button @click="detail = null" class="text-[#5B6B7E] hover:text-[#0B0B0F]">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-6">
                    <dl class="space-y-3 text-sm">
                        <template x-for="k in Object.keys(detail || {})" :key="k">
                            <div class="flex gap-3">
                                <dt class="w-36 shrink-0 text-[#5B6B7E]" x-text="label(k)"></dt>
                                <dd class="min-w-0 font-mono text-[13px] text-[#1A2433] break-words">
                                    <span x-text="fmtD(detail, k)"></span>
                                    <a x-show="refSection(k) && detail[k]" :href="'/app/' + refSection(k) + '?tenant=' + tenant + '&open=' + detail[k]"
                                       class="ml-1.5 text-[#CA8A04] hover:underline text-[11px] font-sans whitespace-nowrap">öffnen →</a>
                                </dd>
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
                        <a :href="'/app/graph-entities?tenant=' + tenant + '&open=' + e.other"
                           class="flex items-center gap-2 rounded-lg border border-[#E4E9F0] px-3 py-2 text-xs hover:border-[#CA8A04]/60 transition">
                            <span class="font-mono text-[#9CA3AF] w-4 shrink-0" x-text="e.dir"></span>
                            <span class="text-[#5B6B7E]" x-text="e.relation"></span>
                            <span class="ml-auto font-medium text-[#1A2433] truncate" x-text="e.name"></span>
                        </a>
                    </template>
                    <div x-show="entityEdges.length === 0" class="text-xs text-[#9CA3AF]">Keine Kanten zu dieser Entität.</div>
                </div>
                <div x-show="detail && (detail.created_at || detail.updated_at)" class="px-6 py-2.5 border-t border-[#F0F3F7] text-[10px] text-[#9CA3AF] flex gap-4">
                    <span x-show="detail && detail.created_at">Erstellt: <span x-text="detail && new Date(detail.created_at).toLocaleString('de-DE')"></span></span>
                    <span x-show="detail && detail.updated_at">Geändert: <span x-text="detail && new Date(detail.updated_at).toLocaleString('de-DE')"></span></span>
                </div>
                <div class="px-6 py-4 border-t border-[#E4E9F0] flex justify-end gap-2">
                    <button @click="copyLink()" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" x-text="linkCopied ? 'Kopiert' : 'Link'"></button>
                    <button x-show="['documents','data-objects'].includes(section)" @click="downloadDoc(detail)" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10">Download</button>
                    <button x-show="canEdit() && section !== 'documents'" @click="openDuplicate()" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]">Duplizieren</button>
                    <button x-show="canEdit()" @click="openEdit()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F]">Bearbeiten</button>
                    <button x-show="writable()" @click="deleteRow(detail)" class="text-xs px-3 py-1.5 border border-[#A6362E]/40 text-[#A6362E] rounded-lg hover:bg-[#A6362E]/5">Löschen</button>
                </div>
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
                            <span x-text="it.label"></span>
                            <span class="ml-2 text-[10px] text-[#9CA3AF] tracking-wide" x-text="it.group"></span>
                        </a>
                    </template>
                    <div x-show="paletteItems().length === 0" class="px-5 py-6 text-center text-xs text-[#9CA3AF]">Kein Modul gefunden.</div>
                </div>
            </div>
        </div>

        {{-- Create modal --}}
        <div x-show="showCreate" class="fixed inset-0 z-40 flex items-center justify-center" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="showCreate = false"></div>
            <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl">
                <div class="px-6 py-4 border-b border-[#E4E9F0]">
                    <h2 class="font-semibold text-[#0B0B0F]" x-text="(editing ? 'Bearbeiten: ' : 'Neu: ') + title()"></h2>
                </div>
                <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                    <template x-for="f in createFields()" :key="f.key">
                        <div>
                            <label class="block text-[13px] font-medium text-[#42536A] mb-1" x-text="label(f.key)"></label>
                            <select x-show="f.type === 'fk'" x-model="form[f.key]"
                                    class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                                <option value="">— wählen —</option>
                                <template x-for="o in fkOptions(f.table)" :key="o[0]">
                                    <option :value="o[0]" x-text="o[1]"></option>
                                </template>
                            </select>
                            <input x-show="f.type !== 'fk'" x-model="form[f.key]" :type="f.type"
                                   class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                        </div>
                    </template>
                    <div x-show="['documents','data-objects'].includes(section) && !editing">
                        <label class="block text-[13px] font-medium text-[#42536A] mb-1">Datei</label>
                        <input type="file" x-ref="fileInput" class="w-full text-sm">
                    </div>
                    <div x-show="formError" class="text-xs text-[#A6362E]" x-text="formError"></div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreate = false" class="text-sm px-4 py-2 text-[#5B6B7E]">Abbrechen</button>
                        <button type="submit" class="text-sm px-4 py-2 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F]">Speichern</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
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
    const FKMAP = {person_id:'persons',company_id:'companies',machine_id:'machines',project_id:'projects',strategy_id:'strategies',portfolio_id:'portfolios',tender_id:'tenders',question_id:'questions',document_id:'documents',expert_profile_id:'expert_profiles',responsible_id:'users',assignee_id:'users',owner_id:'users',asked_by:'users',approved_by:'users',answered_by:'users',created_by:'users',uploaded_by:'users',assigned_to:'persons',from_entity_id:'graph_entities',to_entity_id:'graph_entities',subject_id:'graph_entities'};
    const STATUS_DE = {open:'Offen',pending:'Ausstehend',in_progress:'Läuft',active:'Aktiv',done:'Fertig',completed:'Abgeschlossen',approved:'Genehmigt',archived:'Archiviert',draft:'Entwurf',maintenance:'Wartung',retired:'Ausgemustert',awarded:'Vergeben',info:'Info',warning:'Warnung',critical:'Kritisch',high:'Hoch',medium:'Mittel',low:'Niedrig',scheduled:'Geplant',cancelled:'Abgesagt',rejected:'Abgelehnt',answered:'Beantwortet',closed:'Geschlossen',submitted:'Eingereicht',shortlisted:'Vorauswahl',queued:'Warteschlange',running:'Läuft',mitigated:'Gemindert',accepted:'Akzeptiert',planned:'Geplant',on_hold:'Pausiert',inactive:'Inaktiv'};
    const HIDE = new Set(['id','tenant_id','created_at','updated_at','deleted_at','pivot','data','roles','permissions','email_verified_at']);
    const KPI = [
        {key:'companies',label:'Unternehmen',to:'companies'},{key:'persons',label:'Personen',to:'persons'},
        {key:'documents',label:'Dokumente',to:'documents'},{key:'tasks_open',label:'Offene Aufgaben',to:'tasks'},
        {key:'instructions',label:'Unterweisungen',to:'instructions'},{key:'compliance_rate',label:'Compliance %'},
        {key:'deadlines_open',label:'Offene Fristen',to:'deadlines'},{key:'risk_high',label:'Hohe Risiken',to:'risk-assessments'},
        {key:'tenders_open',label:'Offene Ausschreibungen',to:'tenders'},{key:'expert_profiles',label:'Experten',to:'expert-profiles'},
        {key:'questions',label:'Fragen',to:'questions'},{key:'inspections',label:'Prüfungen',to:'inspections'},
    ];
    return {
        section: initial, groups: GROUPS, kpiCards: KPI,
        tenant: '', rows: null, columns: [], metrics: null, insights: [], events: [], trends: [], exec: null, execReports: [], lookups: {}, navOpen: false, collapsed: {}, dueSoon: [], openTasks: [],
        tenantList: {{ \Illuminate\Support\Js::from($tenants->map(fn($t) => ['id' => $t->id, 'name' => $t->name])) }},
        loading: false, error: '', detail: null, showCreate: false, form: {}, formError: '', query: '', editing: null,
        sortKey: '', sortAsc: true, limit: 100, statusFilter: '', overdueOnly: false, dueSoonOnly: false, linkCopied: false, hiddenCols: {}, colPicker: false, docVersions: [], entityEdges: [], answers: [], answerText: '', apps: [], appForm: {expert_profile_id: '', proposal: '', price: ''}, palette: false, paletteQ: '',
        init() {
            const t = new URLSearchParams(location.search).get('tenant');
            if (t) this.tenant = t;
            if (this.tenant) this.loadSection();
            this.$watch('detail', v => {
                this.answers = []; this.answerText = ''; this.apps = []; this.appForm = {expert_profile_id: '', proposal: '', price: ''}; this.docVersions = []; this.entityEdges = [];
                if (v && this.section === 'questions') this.loadAnswers(v.id);
                if (v && this.section === 'tenders') this.loadApps(v.id);
                if (v && this.section === 'documents') this.loadDocVersions(v.id);
                if (v && this.section === 'graph-entities') this.loadEntityEdges(v.id);
            });
        },
        item() {
            return GROUPS.flatMap(g => g.items).find(i => i.key === this.section) || {label:this.section};
        },
        title() { return this.item().label; },
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
        execLabel(k) { const M = {companies:'Unternehmen',persons:'Personen',tasks_open:'Offene Aufgaben',deadlines_open:'Offene Fristen',high_risks:'Hohe Risiken',data_objects:'Data Lake'}; return M[k] || k; },
        createExecReport() {
            const title = prompt('Report-Titel', 'Executive Report ' + new Date().toLocaleDateString('de-DE'));
            if (!title) return;
            this.api('/api/v1/exec-reports', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({title})})
                .then(r => { if (r.ok) this.loadSection(); else alert('Report fehlgeschlagen (HTTP '+r.status+')'); });
        },
        subtitle() { return (this.section === 'dashboard' ? 'Unternehmenssteuerung' : 'Modul ' + (this.item().label||this.section)) + ' · ' + this.tenantName(); },
        metric(k) { const v = this.metrics && this.metrics[k]; return v ? parseFloat(v.value) : '–'; },
        trend(k) { const t = this.trends.find(x => x.metric === k); return t || {delta: null, direction: 'unknown'}; },
        api(path, opts={}) {
            opts.headers = Object.assign({
                'Authorization': 'Bearer {{ $apiToken }}',
                'X-Tenant': this.tenant, 'Accept': 'application/json',
            }, opts.headers||{});
            return fetch(path, opts);
        },
        loadSection() {
            if (!this.tenant) { this.rows = null; return; }
            if (this._loadedTenant !== this.tenant) { this.lookups = {}; this._loadedTenant = this.tenant; }
            this.loading = true; this.error = ''; this.limit = 100; this.statusFilter = ''; this.overdueOnly = false; this.dueSoonOnly = false; this.hiddenCols = this.loadColPrefs(); this.colPicker = false;
            const url = new URL(location.href); url.searchParams.set('tenant', this.tenant);
            history.replaceState(null,'',url);
            if (this.section === 'dashboard') {
                this.api('/api/v1/metrics').then(r => r.ok ? r.json() : (this.error='HTTP '+r.status, null))
                    .then(d => { this.metrics = d; this.loading = false; });
                this.api('/api/v1/insights').then(r => r.ok ? r.json() : [])
                    .then(d => this.insights = d.filter(i => i.code !== 'all_clear'));
                this.api('/api/v1/events').then(r => r.ok ? r.json() : [])
                    .then(d => this.events = (Array.isArray(d) ? d : (d.data || [])).slice(-15).reverse());
                this.api('/api/v1/analytics/trends').then(r => r.ok ? r.json() : [])
                    .then(d => this.trends = Array.isArray(d) ? d : (d.data || []));
                this.api('/api/v1/deadlines').then(r => r.ok ? r.json() : [])
                    .then(d => {
                        const rs = Array.isArray(d) ? d : (d.data || []);
                        this.dueSoon = rs.filter(x => x.status !== 'completed' && x.due_at)
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
                }).then(d => { if (d) this.exec = d; this.loading = false; });
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
                if (rows.length) {
                    const keys = Object.keys(rows[0]).filter(k => !HIDE.has(k) && typeof rows[0][k] !== 'object');
                    this.columns = keys.slice(0, 7);
                } else this.columns = [];
                const oid = new URLSearchParams(location.search).get('open');
                if (oid) { const r = rows.find(x => String(x.id) === oid); if (r) this.detail = r; }
                this.loading = false;
            });
        },
        sort(c) { if (this.sortKey === c) this.sortAsc = !this.sortAsc; else { this.sortKey = c; this.sortAsc = true; } },
        sorted(rows) {
            if (!this.sortKey) return rows;
            const k = this.sortKey, dir = this.sortAsc ? 1 : -1;
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
                .then(r => { if (r.ok) { this.detail = null; this.loadSection(); } else alert('Aktion fehlgeschlagen (HTTP '+r.status+')'); });
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
                .then(r => { if (r.ok) { this.appForm = {expert_profile_id: '', proposal: '', price: ''}; this.loadApps(this.detail.id); this.loadSection(); } else alert('Bewerbung fehlgeschlagen (HTTP '+r.status+')'); });
        },
        setAppStatus(id, s) {
            this.api('/api/v1/tender-applications/' + id, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:s})})
                .then(r => { if (r.ok) { this.loadApps(this.detail.id); this.loadSection(); } else alert('Aktion fehlgeschlagen (HTTP '+r.status+')'); });
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
                .then(r => { if (r.ok) { this.answerText = ''; this.loadAnswers(this.detail.id); this.loadSection(); } else alert('Antwort fehlgeschlagen (HTTP '+r.status+')'); });
        },
        acceptAnswer(id) {
            this.api('/api/v1/answers/' + id + '/accept', {method:'POST'})
                .then(r => { if (r.ok) { this.loadAnswers(this.detail.id); this.loadSection(); } else alert('Aktion fehlgeschlagen (HTTP '+r.status+')'); });
        },
        deleteAnswer(id) {
            if (!confirm('Antwort löschen?')) return;
            this.api('/api/v1/answers/' + id, {method:'DELETE'})
                .then(() => this.loadAnswers(this.detail.id));
        },
        loadEntityEdges(id) {
            this.api('/api/v1/graph-edges').then(r => r.ok ? r.json() : []).then(d => {
                const rs = Array.isArray(d) ? d : (d.data || []);
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
                .then(r => { if (r.ok) { this.$refs.versionFile.value = ''; this.loadDocVersions(this.detail.id); this.loadSection(); } else alert('Upload fehlgeschlagen (HTTP '+r.status+')'); });
        },
        dlPath(row) {
            if (this.section === 'data-objects') return '/api/v1/data-objects/' + row.id + '/download';
            return '/api/v1/documents/' + row.id + '/download';
        },
        async downloadDoc(row) {
            const r = await this.api(this.dlPath(row));
            if (!r.ok) { alert('Download fehlgeschlagen'); return; }
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
            if (!r.ok) { alert('Anlegen fehlgeschlagen (HTTP '+r.status+')'); return; }
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
            try { localStorage.setItem('af_cols_' + this.section, JSON.stringify(this.hiddenCols)); } catch (e) {}
        },
        visCols() { return this.columns.filter(c => !this.hiddenCols[c]); },
        filtered() {
            if (!this.rows) return [];
            let rs = this.rows;
            if (this.overdueOnly) rs = rs.filter(r => this.overdue(r));
            if (this.dueSoonOnly) rs = rs.filter(r => this.dueSoon(r));
            if (this.statusFilter) rs = rs.filter(r => String(r.status || '') === this.statusFilter);
            const q = this.query.trim().toLowerCase();
            if (!q) return rs;
            return rs.filter(r => Object.values(r).some(v => String(v).toLowerCase().includes(q)));
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
            return this.fmt(row[k]);
        },
        fmt(v) {
            if (v === null || v === undefined) return '—';
            if (typeof v === 'object') return JSON.stringify(v);
            return String(v);
        },
        createFields() {
            const SKIP = new Set([...HIDE, 'status', 'created_by', 'updated_by', 'completed_at', 'approved_at', 'approved_by', 'awarded_at', 'current_version', 'file_path', 'mime_type', 'size_bytes']);
            const src = (this.rows && this.rows[0]) || {};
            return Object.keys(src).filter(k => !SKIP.has(k) && (!k.endsWith('_id') || FKMAP[k])).slice(0, 12).map(k => ({
                key: k,
                type: FKMAP[k] ? 'fk' : (typeof src[k] === 'number' ? 'number' : (/_at$|_date$/.test(k) ? 'date' : 'text')),
                table: FKMAP[k] || null,
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
                    .then(r => { if (r.ok) this.loadSection(); else alert('Analyse fehlgeschlagen (HTTP '+r.status+')'); });
                return;
            }
            this.editing = null; this.form = {}; this.formError = ''; this.showCreate = true;
        },
        exportCsv() {
            const rows = this.sorted(this.filtered());
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
            const lines = [this.columns.map(c => esc(this.label(c))).join(';'), ...rows.map(r => this.columns.map(c => esc(csvVal(r, c))).join(';'))];
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['\ufeff' + lines.join('\n')], {type:'text/csv'}));
            a.download = this.section + '.csv';
            a.click();
        },
        openDuplicate() {
            this.editing = null;
            const fields = this.createFields();
            this.form = {};
            fields.forEach(f => { const v = this.detail[f.key]; this.form[f.key] = v === null ? '' : v; });
            this.formError = ''; this.showCreate = true;
        },
        openEdit() {
            this.editing = this.detail;
            const fields = this.createFields();
            this.form = {};
            fields.forEach(f => { const v = this.editing[f.key]; this.form[f.key] = v === null ? '' : v; });
            this.formError = ''; this.showCreate = true;
        },
        submitCreate() {
            this.formError = '';
            const body = {};
            this.createFields().forEach(f => { if (this.form[f.key] !== undefined && this.form[f.key] !== '') body[f.key] = this.form[f.key]; });
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
                    this.showCreate = false; this.detail = null; this.editing = null; this.loadSection(); return r.json();
                });
        },
        deleteRow(row) {
            if (!row || !row.id || !confirm('Wirklich löschen?')) return;
            this.api(this.item().ep + '/' + row.id, {method: 'DELETE'}).then(() => { this.detail = null; this.loadSection(); });
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
        copyLink() {
            const url = location.origin + '/app/' + this.section + '?tenant=' + this.tenant + '&open=' + this.detail.id;
            navigator.clipboard.writeText(url).then(() => { this.linkCopied = true; setTimeout(() => this.linkCopied = false, 1500); });
        },
        navDetail(dir) {
            const rs = this.sorted(this.filtered());
            const i = rs.findIndex(r => String(r.id) === String(this.detail.id));
            const n = rs[i + dir];
            if (n) this.detail = n;
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
        label(c) {
            const L = {name:'Name',title:'Titel',first_name:'Vorname',last_name:'Nachname',email:'E-Mail',phone:'Telefon',type:'Typ',status:'Status',description:'Beschreibung',content:'Inhalt',category:'Kategorie',subject:'Betreff',area:'Bereich',hazard:'Gefährdung',risk_level:'Risikostufe',measures:'Maßnahmen',result:'Ergebnis',notes:'Notizen',progress:'Fortschritt',quantity:'Menge',order_no:'Auftrag-Nr.',product:'Produkt',scrap_qty:'Ausschuss',headline:'Schlagzeile',bio:'Bio',skills:'Skills',hourly_rate:'Stundensatz',budget:'Budget',price:'Preis',proposal:'Angebot',stake_pct:'Anteil %',invested_amount:'Investiert',current_valuation:'Bewertung',capital_need:'Kapitalbedarf',revenue:'Umsatz',cashflow:'Cashflow',ebitda:'EBITDA',liquidity:'Liquidität',period:'Periode',legal_form:'Rechtsform',street:'Straße',zip:'PLZ',city:'Stadt',country:'Land',version:'Version',valid_from:'Gültig ab',interval_months:'Intervall (Mon.)',capacity_units_per_day:'Kapazität/Tag',asset_class:'Anlageklasse',cost_basis:'Kostenbasis',current_value:'Aktueller Wert',currency:'Währung',due_at:'Fällig',deadline_at:'Frist',scheduled_at:'Geplant',starts_on:'Von',ends_on:'Bis',starts_at:'Start',ends_at:'Ende',acquired_at:'Erworben',valued_at:'Bewertet am',body:'Inhalt',is_accepted:'Akzeptiert',document_id:'Dokument'};
            return L[c] || c.replace(/_/g,' ').replace(/^\w/, s => s.toUpperCase());
        },
        cell(row, c) {
            let v = row[c];
            if (v === null || v === undefined) return '—';
            const rn = this.resolveId(c, v);
            if (rn) return `<span title="${v}">${rn}</span>`;
            if (typeof v === 'boolean') return v ? 'Ja' : 'Nein';
            if (c === 'status' || c === 'severity' || c === 'type') {
                const map = {open:'#CA8A04',pending:'#CA8A04',in_progress:'#CA8A04',running:'#CA8A04',queued:'#5B6B7E',scheduled:'#5B6B7E',planned:'#5B6B7E',on_hold:'#CA8A04',critical:'#A6362E',high:'#A6362E',warning:'#CA8A04',cancelled:'#A6362E',rejected:'#A6362E',done:'#2E7D5B',completed:'#2E7D5B',approved:'#2E7D5B',accepted:'#2E7D5B',mitigated:'#2E7D5B',active:'#2E7D5B',awarded:'#2E7D5B',info:'#5B6B7E'};
                const col = map[String(v).toLowerCase()] || '#5B6B7E';
                const txt = STATUS_DE[String(v).toLowerCase()] || v;
                return `<span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full" style="background:${col}"></span>${txt}</span>`;
            }
            if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(v)) {
                const d = new Date(v);
                let out = d.toLocaleDateString('de-DE');
                if (/due|deadline|scheduled/.test(c)) {
                    const days = Math.ceil((d - Date.now()) / 86400000);
                    if (days < 0) out += ` <span class="text-[#A6362E]">(vor ${-days} T)</span>`;
                    else if (days <= 7) out += ` <span class="text-[#CA8A04]">(in ${days} T)</span>`;
                }
                return out;
            }
            if (typeof v === 'string' && v.length > 80) return v.slice(0,80)+'…';
            return v;
        },
    }
}
</script>
</body>
</html>
