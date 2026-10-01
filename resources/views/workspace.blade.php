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
    <meta name="theme-color" content="#0B0B0F">
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#FACC15">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=jetbrains-mono:500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.dark-mode')
</head>
<body class="font-sans antialiased bg-[#F6F7F9] text-[#1A2433]">
<a href="#hauptinhalt" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[60] focus:bg-[#0B0B0F] focus:text-[#FACC15] focus:px-3 focus:py-2 focus:rounded-lg focus:text-xs"><span x-text="t('Zum Inhalt springen')"></span></a>
<div class="min-h-screen flex flex-col lg:flex-row" x-data="workspace(@js($section))" x-cloak
     @keydown.escape.window="detail = null; closeCreate(); showCreate = false; navOpen = false; palette = false; colPicker = false; viewPicker = false; kbdHelp = false; showImport = false; confirmDel = false; notif = false; pwOpen = false; tenantModal = false; endTour()"
     @keydown.arrowright.window="detail && navDetail(1)"
     @keydown.arrowleft.window="detail && navDetail(-1)"
     @keydown.home.window="detail && (detail = sorted(filtered())[0])"
     @keydown.end.window="detail && (detail = sorted(filtered())[sorted(filtered()).length - 1])"
     @keydown.window="kbd($event)"
     @beforeprint.window="limit = 100000">

    {{-- Mobile top bar --}}
    <div class="lg:hidden flex items-center justify-between px-4 h-14 bg-[#0B0B0F] text-white sticky top-0 z-30 shrink-0 print:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <img src="{{ asset('logo-mark.png') }}" alt="ALLOCORE" class="h-7 w-auto">
            <span class="font-semibold tracking-tight">ALLO<span class="text-[#FACC15]">CORE</span></span>
        </a>
        <span class="text-[13px] text-[#9CA3AF] truncate"><span class="text-[#FACC15] mr-1.5" x-text="icons[section] || ''"></span><span x-text="title()"></span></span>
        <button @click="navOpen = !navOpen" class="p-2 -mr-2 text-[#9CA3AF] hover:text-white" :title="t('Menü')" :aria-label="t('Navigation umschalten')" :aria-expanded="navOpen">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
    </div>

    {{-- Backdrop (mobile) --}}
    <div x-show="navOpen" @click="navOpen = false" class="lg:hidden fixed inset-0 bg-black/50 z-30" x-transition.opacity aria-hidden="true"></div>

    {{-- Sidebar --}}
    <aside data-tour="nav" class="bg-[#0B0B0F] text-white w-64 flex flex-col fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-200 lg:translate-x-0 lg:static lg:min-h-screen lg:sticky lg:top-0 lg:shrink-0 print:hidden"
           :class="navOpen ? 'translate-x-0' : '-translate-x-full'">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-5 h-16 border-b border-[#1A1A1F]">
            <img src="{{ asset('logo-mark.png') }}" alt="ALLOCORE" class="h-9 w-auto">
            <span class="font-semibold tracking-tight text-[17px]">ALLO<span class="text-[#FACC15]">CORE</span></span>
        </a>

        {{-- Tenant picker --}}
        <div data-tour="tenant" class="px-4 py-4 border-b border-[#1A1A1F]">
            <label class="block text-[10px] font-medium tracking-wide text-[#9CA3AF] mb-1.5"><span x-text="t('MANDANT')"></span></label>
            <select x-model="tenant" @change="loadSection(); loadNavBadges()"
                    class="w-full rounded-lg bg-[#1A1A1F] border-[#2A2A31] text-white text-sm py-2 focus:border-[#FACC15] focus:ring-[#FACC15]/30">
                <option value=""><span x-text="t('— wählen —')"></span></option>
                <template x-for="t in sortedTenants()" :key="t.id">
                    <option :value="t.id" x-text="t.name"></option>
                </template>
            </select>
            <div x-show="tenantInfo" class="mt-1.5 text-[11px] text-[#5B6B7E]" x-text="tenantInfo ? tenantInfo.members_count + ' ' + t('Mitglied(er)') : ''"></div>
            <div class="mt-2 flex gap-3">
                <button @click="createTenant()" class="text-left text-[11px] text-[#9CA3AF] hover:text-[#FACC15]" x-text="'+ ' + t('Neuer Mandant')"></button>
                <button x-show="tenant && hasPerm('roles.manage')" @click="renameTenant()" class="text-left text-[11px] text-[#9CA3AF] hover:text-[#FACC15]" x-text="'&#9998; ' + t('Umbenennen')"></button>
                <button x-show="tenant" @click="leaveTenant()" class="text-left text-[11px] text-[#9CA3AF] hover:text-[#A6362E]" :title="t('Mitgliedschaft in diesem Mandanten beenden')" x-text="'&#9094; ' + t('Verlassen')"></button>
                <button @click="setLang(lang === 'de' ? 'en' : 'de')" class="ml-auto text-[11px] text-[#9CA3AF] hover:text-[#FACC15]" :title="lang === 'de' ? 'Switch to English' : t('Auf Deutsch wechseln')" x-text="lang === 'de' ? 'EN' : 'DE'"></button>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto py-3 text-[13px]" :aria-label="t('Hauptnavigation')">
            <div class="px-4 pb-2">
                <div class="relative">
                    <input data-tour="navq" x-model="navQ" :placeholder="t('Module filtern…')" @keydown.enter.prevent="const m = groups.flatMap(g => g.items).find(i => i.label.toLowerCase().includes(navQ.toLowerCase())); if (m) location.href = '/app/' + m.key + (tenant ? '?tenant=' + tenant : '')" class="w-full bg-[#141419] text-[#9CA3AF] text-[11px] pl-2.5 pr-6 py-1.5 rounded-lg border-0 focus:ring-1 focus:ring-[#FACC15] placeholder-[#4B5563]">
                    <button x-show="navQ" @click="navQ = ''" :title="t('Filter löschen')" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[#4B5563] hover:text-[#FACC15] text-xs">&times;</button>
                </div>
            </div>
            <div x-show="pins.length" class="mb-1" style="display:none">
                <div class="px-5 pt-3 pb-1.5 text-[10px] font-semibold tracking-widest text-[#6B7280]"><span x-text="t('FAVORITEN')"></span></div>
                <template x-for="pk in pins" :key="pk">
                    <a :href="'/app/' + pk + (tenant ? '?tenant='+tenant : '')"
                       class="flex items-center gap-3 px-5 py-2 transition"
                       :class="section === pk ? 'text-white bg-[#1A1A1F] border-r-2 border-[#FACC15]' : 'text-[#9CA3AF] hover:text-white hover:bg-[#141419]'">
                        <span class="text-[#CA8A04] text-xs">★</span>
                        <span x-text="iLabel(groups.flatMap(g => g.items).find(i => i.key === pk) || {key: pk, label: pk})"></span>
                    </a>
                </template>
            </div>
            <div x-show="recent.length" class="px-5 pb-3">
                <div class="text-[10px] font-semibold tracking-widest text-[#6B7280] pb-1.5" x-text="t('Zuletzt').toUpperCase()"></div>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="r in recent" :key="r">
                        <a :href="'/app/' + r + (tenant ? '?tenant='+tenant : '')" class="px-2 py-1 rounded-full bg-[#1A1A1F] text-[11px] text-[#9CA3AF] hover:text-[#FACC15] transition"><span class="opacity-70 mr-1" x-text="icons[r] || '·'"></span><span x-text="sectionLabel(r)"></span></a>
                    </template>
                </div>
            </div>
            <div class="flex justify-end pr-4 -mb-1">
                <button @click="toggleAllGroups()" class="text-[10px] text-[#4B5563] hover:text-[#9CA3AF] transition" :title="allCollapsed() ? 'Alle Gruppen aufklappen' : t('Alle Gruppen einklappen')" x-text="allCollapsed() ? '▸ ' + t('alle auf') : '▾ ' + t('alle zu')"></button>
            </div>
            <template x-for="group in visGroups()" :key="group.label">
                <div class="mb-1">
                    <button @click="toggleGroup(group.label)"
                            class="w-full flex items-center justify-between px-5 pt-4 pb-1.5 text-[10px] font-semibold tracking-widest text-[#6B7280] hover:text-[#9CA3AF] transition">
                        <span><span x-text="gLabel(group.label)"></span><span class="ml-1.5 font-normal text-[#4B5563]" x-text="'· ' + group.items.length"></span></span>
                        <span class="flex items-center gap-1.5">
                            <span x-show="collapsed[group.label] && group.items.some(i => navBadges[i.key] > 0)" class="w-1.5 h-1.5 rounded-full bg-[#A6362E]"></span>
                            <svg class="w-2.5 h-2.5 transition-transform" :class="collapsed[group.label] && !group.items.some(i => i.key === section) ? '-rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>
                    <template x-for="item in group.items" :key="item.key">
                        <div x-show="(navQ === '' && (!collapsed[group.label] || group.items.some(i => i.key === section))) || (navQ !== '' && item.label.toLowerCase().includes(navQ.toLowerCase()))"
                           class="flex items-center gap-1 transition pr-2"
                           :class="section === item.key
                               ? 'text-white bg-[#1A1A1F] border-r-2 border-[#FACC15]'
                               : 'text-[#9CA3AF] hover:text-white hover:bg-[#141419]'">
                            <a :href="'/app/' + item.key + (tenant ? '?tenant='+tenant : '')"
                               class="flex-1 px-5 py-2 flex items-center gap-2.5"><span class="w-4 text-center text-[11px] opacity-70" x-text="icons[item.key] || '·'"></span><span x-text="iLabel(item)"></span></a>
                            <a x-show="navBadges[item.key] > 0" x-text="navBadges[item.key]"
                               :href="'/app/' + item.key + '?' + (item.key === 'notifications' ? 'unread=1' : 'overdue=1') + (tenant ? '&tenant='+tenant : '')"
                               :title="item.key === 'notifications' ? 'Ungelesene anzeigen' : 'Überfällige Einträge anzeigen'"
                               class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-[#A6362E] text-white min-w-[1.1rem] text-center hover:bg-[#8C2B24]"></a>
                            <a x-show="navBadgesToday[item.key] > 0" x-text="navBadgesToday[item.key]"
                               :href="'/app/' + item.key + '?today=1' + (tenant ? '&tenant='+tenant : '')"
                               :title="t('Heute fällige Einträge anzeigen')"
                               class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-[#CA8A04] text-black min-w-[1.1rem] text-center hover:bg-[#A16207]"></a>
                            <a x-show="navBadgesWeek[item.key] > 0" x-text="navBadgesWeek[item.key]"
                               :href="'/app/' + item.key + '?dueSoon=1' + (tenant ? '&tenant='+tenant : '')"
                               :title="t('≤7 Tage fällige Einträge anzeigen')"
                               class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-[#B45309] text-white min-w-[1.1rem] text-center hover:bg-[#92400E]"></a>
                        </div>
                    </template>
                </div>
            </template>
        </nav>

        <div class="px-5 py-4 border-t border-[#1A1A1F] flex items-center justify-between">
            <div class="min-w-0">
                <div class="text-sm font-medium truncate" :title="(me && me.email ? me.email : '') + (me && me.last_login_at ? ' · letzte Anmeldung ' + new Date(me.last_login_at).toLocaleString((this.lang==='en'?'en-GB':'de-DE')) : '')">{{ $user->name }}</div>
                <div class="text-[11px] text-[#6B7280] truncate">{{ $user->email }}</div>
                <div x-show="me && me.roles && me.roles.length" class="mt-1 flex flex-wrap gap-1">
                    <template x-for="r in (me ? (me.roles || []) : [])" :key="r">
                        <a :href="'/app/users?role=' + encodeURIComponent(r)" class="text-[9px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-[#FACC15] text-black hover:bg-[#FDE047]" x-text="roleLabel(r)" :title="'Team: ' + roleLabel(r)"></a>
                    </template>
                </div>
            </div>
            <button :title="t('Profil bearbeiten')" @click="pwOpen = true; pwErr = ''; pwForm = {name: (me && me.name) || '', email: (me && me.email) || '', current:'',next:'',confirm:''}; loadLoginHistory()" class="text-[#9CA3AF] hover:text-white mr-3">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z"/></svg>
            </button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button :title="t('Abmelden')" class="text-[#9CA3AF] hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H9"/></svg>
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <main id="hauptinhalt" class="flex-1 min-w-0" tabindex="-1">
        <header class="bg-white border-b border-[#E4E9F0] px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="font-semibold text-lg tracking-tight text-[#0B0B0F] flex items-center gap-2">
                    <span class="text-[#CA8A04] text-base" x-text="icons[section] || ''"></span>
                    <span x-text="title()"></span>
                    <button @click="togglePin(section)" :title="pins.includes(section) ? 'Aus Favoriten entfernen' : t('Zu Favoriten hinzufügen')"
                            class="text-sm transition" :class="pins.includes(section) ? 'text-[#CA8A04]' : 'text-[#D6DEE9] hover:text-[#CA8A04]'">★</button>
                </h1>
                <div x-show="groupOf(section)" class="text-[10px] font-semibold tracking-widest text-[#9CA3AF] mb-0.5">
                    <span x-text="groupOf(section)"></span><span class="mx-1.5">›</span><span x-text="title()"></span>
                </div>
                <p class="text-xs text-[#5B6B7E]" x-text="subtitle()"></p>
            </div>
            <div class="text-right">
                <span x-show="loading" class="text-xs text-[#9CA3AF]"><span x-text="t('Lädt…')"></span></span>
                <span x-show="!loading && lastLoad" class="text-[11px] text-[#9CA3AF]" x-text="lastLoad ? t('Stand') + ' ' + lastLoad.toLocaleTimeString((this.lang==='en'?'en-GB':'de-DE'), {hour: '2-digit', minute: '2-digit'}) : ''"></span>
            </div>
            <div class="flex items-center gap-3">
                <div class="relative" x-show="tenant" data-tour="bell">
                    <button @click="notif = !notif" :aria-label="t('Benachrichtigungen')" :aria-expanded="notif" :title="'Benachrichtigungen' + (overdueTotal() ? ' — ' + overdueTotal() + ' überfällig' : '')" class="relative text-[#9CA3AF] hover:text-[#CA8A04] transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <span x-show="overdueTotal() > 0" x-text="overdueTotal() + unreadNotifs()" class="absolute -top-1.5 -right-1.5 min-w-[16px] h-4 px-0.5 rounded-full bg-[#A6362E] text-white text-[9px] font-bold leading-4 text-center"></span>
                        <span x-show="overdueTotal() === 0 && (todayTotal() + unreadNotifs()) > 0" x-text="todayTotal() + unreadNotifs()" class="absolute -top-1.5 -right-1.5 min-w-[16px] h-4 px-0.5 rounded-full bg-[#CA8A04] text-[#0B0B0F] text-[9px] font-bold leading-4 text-center"></span>
                    </button>
                    <div x-show="notif" @click.outside="notif = false" class="absolute right-0 mt-1.5 w-72 bg-white border border-[#E4E9F0] rounded-lg shadow-lg py-2 z-30" style="display:none">
                        <div class="px-3 pb-1.5 flex items-center justify-between">
                            <span class="text-[10px] font-semibold tracking-widest text-[#9CA3AF]"><span x-text="t('BENACHRICHTIGUNGEN')"></span></span>
                            <span class="flex items-center gap-2">
                                <button x-show="unreadNotifs()" @click="markAllNotifsRead()" class="text-[10px] text-[#CA8A04] hover:underline"><span x-text="t('alle gelesen')"></span></button>
                                <a :href="'/app/notifications?tenant=' + tenant" class="text-[10px] text-[#CA8A04] hover:underline"><span x-text="t('alle →')"></span></a>
                            </span>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                        <template x-for="n in dbNotifs">
                            <a :href="notifLink(n) || ('/app/notifications?tenant=' + tenant)" @click="n.read || markNotifRead(n)" class="px-3 py-1.5 flex items-start gap-2 text-xs hover:bg-[#FAFBFC]" :class="n.read && 'opacity-50'">
                                <span class="w-1.5 h-1.5 mt-1 rounded-full shrink-0" :class="n.read ? 'bg-[#D1D5DB]' : 'bg-[#CA8A04]'"></span>
                                <span class="flex-1"><span x-text="n.title"></span><span class="block text-[10px] text-[#9CA3AF]" x-text="(n.kind ? (nKind(n.kind)) + (n.due_at || n.code ? ' · ' : '') : '') + (n.due_at ? (lang === 'en' ? 'Due ' : 'Fällig ') + n.due_at : '') + (n.due_at && n.code ? ' · ' : '') + (n.code ? n.code : '')"></span></span>
                                <span class="text-[9px] text-[#9CA3AF] font-mono shrink-0" x-text="n.rel"></span>
                                <button x-show="n.kind" @click.prevent.stop="toggleMute(n.kind)" :aria-label="((me && me.muted_kinds || []).includes(n.kind) ? 'Stummschaltung für Art aufheben' : 'Art stummschalten')" :title="((me && me.muted_kinds || []).includes(n.kind) ? 'Stummschaltung aufheben' : 'Art stummschalten')" class="shrink-0 leading-none" :class="((me && me.muted_kinds || []).includes(n.kind)) ? 'text-[#CA8A04]' : 'text-[#9CA3AF] hover:text-[#CA8A04]'">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                </button>
                                <button @click.prevent.stop="dismissNotif(n)" :aria-label="t('Benachrichtigung entfernen')" :title="t('Entfernen')" class="text-[#9CA3AF] hover:text-[#A6362E] shrink-0 leading-none">×</button>
                            </a>
                        </template>
                        </div>
                        <div x-show="!overdueSections().length && !todaySections().length && !dbNotifs.length" class="px-3 py-2 text-xs text-[#5B6B7E]"><span x-text="t('Alles im grünen Bereich.')"></span></div>
                        <template x-for="o in overdueSections()"><a :href="'/app/' + o.key + '?tenant=' + tenant + '&overdue=1'" class="px-3 py-1.5 flex items-center justify-between gap-3 text-xs hover:bg-[#FAFBFC]"><span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-[#A6362E]"></span><span class="w-4 text-center text-[11px] text-[#9CA3AF]" x-text="icons[o.key] || '·'"></span><span x-text="o.label"></span></span><span class="font-mono text-[#A6362E]" x-text="o.count"></span></a></template>
                        <template x-for="o in todaySections()"><a :href="'/app/' + o.key + '?tenant=' + tenant + '&today=1'" class="px-3 py-1.5 flex items-center justify-between gap-3 text-xs hover:bg-[#FAFBFC]"><span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-[#CA8A04]"></span><span class="w-4 text-center text-[11px] text-[#9CA3AF]" x-text="icons[o.key] || '·'"></span><span x-text="o.label"></span></span><span class="font-mono text-[#CA8A04]" x-text="o.count"></span></a></template>
                        <div x-show="weekSections().length" class="px-3 pt-1.5 pb-0.5 flex items-center justify-between">
                            <span class="text-[9px] uppercase tracking-wider text-[#9CA3AF]"><span x-text="t('Bald fällig')"></span></span>
                            <select x-model.number="navWindow" @change="localStorage.setItem('af_navwindow', navWindow); loadNavBadges()" :title="t('Fenster für »bald fällig«-Badges (Tage)')" class="text-[10px] border border-[#E4E9F0] rounded px-1 py-0 text-[#5B6B7E]">
                                <option :value="7">≤7T</option><option :value="14">≤14T</option><option :value="30">≤30T</option>
                            </select>
                        </div>
                        <template x-for="o in weekSections()"><a :href="'/app/' + o.key + '?tenant=' + tenant + '&dueSoon=1'" class="px-3 py-1.5 flex items-center justify-between gap-3 text-xs hover:bg-[#FAFBFC]"><span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-[#B45309]"></span><span class="w-4 text-center text-[11px] text-[#9CA3AF]" x-text="icons[o.key] || '·'"></span><span x-text="o.label + ' ≤' + navWindow + 'T'"></span></span><span class="font-mono text-[#B45309]" x-text="o.count"></span></a></template>
                    </div>
                </div>
                <button data-tour="dark" @click="toggleDark()" :aria-label="t('Darstellung umschalten')" :title="dark ? 'Helle Darstellung (t)' : 'Dunkle Darstellung (t)'" class="text-[#9CA3AF] hover:text-[#CA8A04] transition">
                    <svg x-show="!dark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg x-show="dark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>
                <button @click="startTour()" :title="t('Rundgang starten')" :aria-label="t('Rundgang starten')" class="text-[#9CA3AF] hover:text-[#CA8A04] transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z"/></svg>
                </button>
                <button x-show="tenant && !['dashboard','executive'].includes(section)" @click="loadSection(true)" :title="t('Aktualisieren (r)')" :aria-label="t('Liste aktualisieren')"
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
            <div x-show="newToken" class="bg-[#FFFBEB] border border-[#CA8A04]/50 rounded-xl px-4 py-3 flex items-start justify-between gap-3" x-cloak>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-[#0B0B0F]"><span x-text="t('Neuer API-Token — nur einmal sichtbar. Jetzt kopieren:')"></span></p>
                    <code class="block mt-1 text-[11px] font-mono text-[#5B6B7E] break-all" x-text="newToken"></code>
                </div>
                <span class="shrink-0 flex items-center gap-2">
                    <button @click="navigator.clipboard.writeText(newToken).then(() => toast('Token kopiert'))" class="text-xs px-2.5 py-1 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition"><span x-text="t('Kopieren')"></span></button>
                    <button @click="newToken = null" class="text-[#9CA3AF] hover:text-[#A6362E] leading-none" :aria-label="t('Schließen')">&times;</button>
                </span>
            </div>
            <div x-show="error" class="bg-white border border-[#A6362E]/40 rounded-xl px-4 py-3 text-sm text-[#A6362E] flex items-start justify-between gap-3">
                <span x-text="error"></span>
                <span class="shrink-0 flex items-center gap-3">
                    <button @click="error = ''; loadSection()" class="text-xs underline underline-offset-2 text-[#A6362E] hover:text-[#0B0B0F] transition"><span x-text="t('Erneut versuchen')"></span></button>
                    <button @click="error = ''" class="text-[#A6362E]/60 hover:text-[#A6362E] leading-none" :aria-label="t('Fehler schließen')">&times;</button>
                </span>
            </div>

            {{-- Dashboard --}}
            <template x-if="section === 'dashboard'">
                <div class="space-y-5">
                    <div data-tour="globalsearch" class="relative bg-white border border-[#E4E9F0] rounded-xl px-4 py-3">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-[#9CA3AF] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                            <input x-model.debounce.300ms="dashQ" @keydown.enter.prevent="if (dashHits.length) location.href = '/app/' + dashHits[0].section + '?tenant=' + tenant + '&open=' + encodeURIComponent(dashHits[0].id)" :placeholder="t('Globale Suche — Firma, Aufgabe, Dokument…')"
                                   class="w-full text-sm outline-none placeholder-[#9CA3AF] bg-transparent">
                            <button x-show="dashQ" @click="dashQ = ''; dashHits = []" :title="t('Suche löschen')" class="text-[#9CA3AF] hover:text-[#CA8A04] text-sm leading-none shrink-0">&times;</button>
                        </div>
                        <div x-show="dashHits.length" class="mt-2 pt-2 border-t border-[#EEF1F5] space-y-0.5" x-cloak>
                            <template x-for="h in dashHits" :key="h.section + '-' + h.id">
                                <a :href="'/app/' + h.section + '?tenant=' + tenant + '&open=' + encodeURIComponent(h.id)"
                                   class="flex items-center justify-between gap-3 px-2 py-1.5 rounded-lg text-sm hover:bg-[#FAFBFC] transition">
                                    <span class="truncate"><span class="text-[11px] text-[#9CA3AF] mr-1.5" x-text="icons[h.section] || '·'"></span><span x-text="h.label || h.id"></span></span>
                                    <span class="text-[10px] font-semibold tracking-widest text-[#9CA3AF] shrink-0" x-text="sectionLabel(h.section)"></span>
                                </a>
                            </template>
                        </div>
                        <div x-show="dashQ && dashQ.trim().length >= 3 && !dashHits.length" class="mt-2 pt-2 border-t border-[#EEF1F5] text-xs text-[#9CA3AF]" x-cloak><span x-text="t('Keine Treffer.')"></span></div>
                    </div>
                    <div x-show="visibleInsights().length || insDismissed.length" data-tour="insights" class="space-y-2">
                        <div x-show="visibleInsights().some(i => i.severity === 'warning' || i.severity === 'info') || insDismissed.length" class="flex gap-1.5">
                        <button x-show="insDismissed.length" @click="insDismissed = []; localStorage.removeItem('af_insdismissed')"
                                class="text-[11px] px-2 py-1 rounded-full border border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]"
                                x-text="'Ausgeblendete: ' + insDismissed.length + ' \u21ba'"></button>
                            <template x-for="s in [['',t('Alle')],['critical',t('Kritisch')],['warning',t('Warnung')],['info','Info']]" :key="s[0]">
                                <button @click="insightSev = s[0]" class="text-[11px] px-2 py-1 rounded-full border transition"
                                        :class="insightSev === s[0] ? 'bg-[#0B0B0F] text-[#FACC15] border-[#0B0B0F]' : 'bg-white text-[#5B6B7E] border-[#D6DEE9] hover:border-[#CA8A04]'" x-text="s[1] + ' · ' + (s[0] ? visibleInsights().filter(i => i.severity === s[0]).length : visibleInsights().length)"></button>
                            </template>
                        </div>
                        <template x-for="i in visibleInsights().filter(x => !insightSev || x.severity === insightSev)" :key="i.code">
                            <div class="relative">
                            <button @click="dismissInsight(i)" :title="t('Ausblenden')" class="absolute top-1.5 right-1.5 z-10 h-5 w-5 rounded text-[#9CA3AF] hover:text-[#A6362E] hover:bg-[#A6362E]/10 text-sm leading-none">&times;</button>
                            <a :href="insightSection(i.code) ? '/app/' + insightSection(i.code) + '?tenant=' + tenant + (insightFilter(i.code) ? '&' + insightFilter(i.code) : '') : '#'"
                               class="flex items-start gap-3 rounded-lg border bg-white px-4 py-3 text-sm transition"
                               :class="{'border-[#A6362E]/40': i.severity==='critical','border-[#CA8A04]/50': i.severity==='warning','border-[#D6DEE9]': i.severity==='info','hover:shadow-sm': insightSection(i.code)}">
                                <span class="mt-0.5 inline-block h-2 w-2 rounded-full shrink-0"
                                      :class="{'bg-[#A6362E]': i.severity==='critical','bg-[#CA8A04]': i.severity==='warning','bg-[#5B6B7E]': i.severity==='info'}"></span>
                                <span x-text="i.message"></span>
                                <span x-show="insightSection(i.code)" class="ml-auto shrink-0 flex items-center gap-1.5">
                                    <span class="text-[11px] text-[#9CA3AF]" x-text="icons[insightSection(i.code)] || ''"></span>
                                    <svg class="h-4 w-4 text-[#9CA3AF]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            </a>
                            </div>
                        </template>
                    </div>
                    <div x-show="tenant && metrics && !insights.length && !openTasks.length && !upcoming.length && !events.length" class="bg-white border border-[#E4E9F0] rounded-xl px-6 py-8 text-center">
                        <div class="text-sm text-[#5B6B7E]"><span x-text="t('Noch keine Einträge für diesen Mandanten.')"></span></div>
                        <button @click="seedDemo()" :disabled="seeding" class="mt-3 text-xs px-4 py-2 bg-[#0B0B0F] text-[#FACC15] rounded-lg hover:opacity-90 disabled:opacity-50" x-text="seeding ? 'Lade Demo-Daten…' : 'Demo-Daten laden'"></button>
                    </div>
                    <div x-show="tenant && metrics && !visibleInsights().length" class="flex items-center gap-3 rounded-lg border border-[#2E7D5B]/30 bg-[#2E7D5B]/5 px-4 py-3 text-sm text-[#2E7D5B]">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Alles im grünen Bereich — keine offenen Hinweise.
                        <button x-show="insDismissed.length" @click="insDismissed = []; localStorage.removeItem('af_insdismissed')"
                                class="ml-auto text-[11px] px-2 py-1 rounded-full border border-[#2E7D5B]/30 text-[#2E7D5B] hover:bg-[#2E7D5B]/10"
                                x-text="'Ausgeblendete: ' + insDismissed.length + ' \u21ba'"></button>
                    </div>
                    <div data-tour="kpis" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                        <template x-for="m in kpiCards" :key="m.key">
                            <a :href="m.to ? '/app/' + m.to + (tenant ? '?tenant='+tenant : '') : '#'"
                               class="bg-white border border-[#E4E9F0] rounded-xl px-5 py-4 block transition"
                               :class="m.to ? 'hover:border-[#CA8A04]/60 hover:shadow-sm cursor-pointer' : 'cursor-default'">
                                <div class="text-[11px] font-medium text-[#5B6B7E]" x-text="kLabel(m)"></div>
                                <div class="mt-1.5 font-mono text-2xl font-semibold tracking-tight text-[#0B0B0F]" x-text="kpiValue(m)"></div>
                                <div class="mt-0.5 text-[11px] font-mono" :class="trend(m.key).direction === 'up' ? 'text-[#2E7D5B]' : (trend(m.key).direction === 'down' ? 'text-[#A6362E]' : 'text-[#9CA3AF]')" x-text="trend(m.key).delta === null ? '' : (trend(m.key).direction === 'up' ? '▲ +' : (trend(m.key).direction === 'down' ? '▼ ' : '')) + (trend(m.key).delta ?? '')"></div>
                                <div class="mt-2 flex items-end justify-between gap-2">
                                    <div class="h-0.5 w-8 rounded-full bg-[#FACC15] mb-1"></div>
                                    <svg x-show="(spark[m.key] || []).length > 1" :title="(spark[m.key] || []).length > 1 ? t('60-Tage-Verlauf: ') + spark[m.key][0] + ' → ' + spark[m.key][spark[m.key].length - 1] : ''" viewBox="0 0 96 24" preserveAspectRatio="none" class="h-6 w-24"
                                         :class="trend(m.key).direction === 'up' ? 'text-[#2E7D5B]' : (trend(m.key).direction === 'down' ? 'text-[#A6362E]' : 'text-[#CA8A04]')">
                                        <path :d="sparkPath(m.key)" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </a>
                        </template>
                        <a x-show="overdueSections().length" :href="'/app/' + (overdueSections()[0] || {key: 'dashboard'}).key + '?tenant=' + tenant + '&overdue=1'"
                           class="bg-[#A6362E]/5 border border-[#A6362E]/40 rounded-xl px-5 py-4 block transition hover:border-[#A6362E] hover:shadow-sm">
                            <div class="text-[11px] font-medium text-[#A6362E]"><span x-text="t('Überfällig gesamt')"></span></div>
                            <div class="mt-1.5 font-mono text-2xl font-semibold tracking-tight text-[#A6362E]" x-text="overdueSections().reduce((s, x) => s + x.count, 0)"></div>
                            <div class="mt-0.5 text-[11px] font-mono text-[#A6362E]/70" x-text="overdueSections().length + (overdueSections().length === 1 ? ' Sektion' : ' Sektionen')"></div>
                            <div class="mt-2 flex items-end"><div class="h-0.5 w-8 rounded-full bg-[#A6362E] mb-1"></div></div>
                        </a>
                        <a x-show="todaySections().length" :href="'/app/' + (todaySections()[0] || {key: 'dashboard'}).key + '?tenant=' + tenant + '&today=1'"
                           class="bg-[#FACC15]/10 border border-[#CA8A04]/40 rounded-xl px-5 py-4 block transition hover:border-[#CA8A04] hover:shadow-sm">
                            <div class="text-[11px] font-medium text-[#B45309]"><span x-text="t('Heute fällig')"></span></div>
                            <div class="mt-1.5 font-mono text-2xl font-semibold tracking-tight text-[#0B0B0F]" x-text="todaySections().reduce((s, x) => s + x.count, 0)"></div>
                            <div class="mt-0.5 text-[11px] font-mono text-[#B45309]/80" x-text="todaySections().length + (todaySections().length === 1 ? ' Sektion' : ' Sektionen')"></div>
                            <div class="mt-2 flex items-end"><div class="h-0.5 w-8 rounded-full bg-[#CA8A04] mb-1"></div></div>
                        </a>
                    </div>
                    <div x-show="openTasks.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E] flex items-center justify-between gap-2">
                            <a :href="'/app/tasks?tenant=' + tenant" class="flex items-center gap-1.5 hover:text-[#CA8A04]">
                                Offene Aufgaben
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                            <button @click="dashMyOnly = !dashMyOnly" class="text-[10px] px-2 py-0.5 rounded-full border transition"
                                    :class="dashMyOnly ? 'border-[#CA8A04] bg-[#CA8A04] text-black' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]'"><span x-text="t('Mir zugewiesen')"></span></button>
                        </div>
                        <div class="divide-y divide-[#F0F3F7]">
                            <div x-show="dashMyOnly && !openTasks.some(t => String(t.assignee_id || t.responsible_id || t.owner_id || '') === String(meId))" class="px-5 py-4 text-xs text-[#9CA3AF]"><span x-text="t('Ihnen ist nichts offen zugewiesen.')"></span></div>
                            <template x-for="t in openTasks.filter(t => !dashMyOnly || String(t.assignee_id || t.responsible_id || t.owner_id || '') === String(meId))" :key="t.id">
                                <div class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-[#FAFBFC]">
                                    <a :href="'/app/tasks?tenant=' + tenant + '&open=' + t.id" class="text-sm text-[#1A2433] truncate hover:text-[#CA8A04] transition" x-text="t.title"></a>
                                    <span class="flex items-center gap-2.5 shrink-0">
                                        <span class="text-[11px] font-mono" x-html="dueRel(t.due_at)"></span>
                                        <button @click="completeDashTask(t)" :title="t('Erledigt markieren')" class="h-4.5 w-4.5 p-0.5 rounded border border-[#D6DEE9] text-transparent hover:border-[#2E7D5B] hover:text-[#2E7D5B] transition">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </button>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div x-show="upcoming.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <a :href="'/app/deadlines?tenant=' + tenant" class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E] flex items-center justify-between hover:text-[#CA8A04]">
                            Nächste Fristen
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                        <div class="divide-y divide-[#F0F3F7]">
                            <template x-for="d in upcoming" :key="d.sec + '-' + d.id">
                                <a :href="'/app/' + d.sec + '?tenant=' + tenant + '&open=' + d.id" class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-[#FAFBFC]">
                                    <span class="text-sm text-[#1A2433] truncate flex items-center gap-1.5"><span class="text-[10px] text-[#9CA3AF]" x-text="icons[d.sec] || ''"></span><span class="truncate" x-text="d.title"></span></span>
                                    <span class="text-[11px] font-mono shrink-0" x-html="dueRel(d.due)"></span>
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
                                    <span class="text-sm text-[#1A2433] truncate"><span class="text-[11px] text-[#9CA3AF] mr-1.5" x-text="icons[o.key] || '·'"></span><span x-text="o.label"></span></span>
                                    <span class="text-[11px] font-mono text-[#A6362E] shrink-0" x-text="o.count + ' ' + t('überfällig')"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                    <div x-show="todaySections().length" class="bg-white border border-[#CA8A04]/30 rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-[#CA8A04]/20 text-xs font-medium text-[#B45309] flex items-center justify-between">
                            Heute fällige Einträge
                            <span class="font-mono" x-text="todaySections().reduce((s, x) => s + x.count, 0)"></span>
                        </div>
                        <div class="divide-y divide-[#F0F3F7]">
                            <template x-for="o in todaySections()" :key="o.key">
                                <a :href="'/app/' + o.key + '?tenant=' + tenant + '&today=1'" class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-[#FAFBFC] transition">
                                    <span class="text-sm text-[#1A2433] truncate"><span class="text-[11px] text-[#9CA3AF] mr-1.5" x-text="icons[o.key] || '·'"></span><span x-text="o.label"></span></span>
                                    <span class="text-[11px] font-mono text-[#B45309] shrink-0" x-text="o.count + ' ' + t('heute')"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                    <div x-show="trends.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E] flex items-center justify-between"><span x-text="t('Metrik-Trends')"></span> <span class="text-[10px] font-mono text-[#9CA3AF]" x-text="trends.length + ' Metriken'"></span></div>
                        <div class="divide-y divide-[#F0F3F7] max-h-64 overflow-y-auto">
                            <template x-for="t in trends" :key="t.metric">
                                <div class="px-5 py-2.5 flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#1A2433] font-mono truncate" x-text="t.metric"></span>
                                    <span class="text-[11px] font-mono shrink-0" :class="t.direction === 'up' ? 'text-[#047857]' : (t.direction === 'down' ? 'text-[#A6362E]' : 'text-[#9CA3AF]')"
                                        x-text="(t.direction === 'up' ? '▲ ' : t.direction === 'down' ? '▼ ' : '→ ') + (t.delta === null ? '—' : (t.delta > 0 ? '+' : '') + t.delta) + ' → ' + t.value"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div x-show="evSummary" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E] flex justify-between items-center"><span x-text="t('Aktivität (')"></span><span x-text="evWindow"></span> Tage)
                            <select x-model.number="evWindow" @change="api('/api/v1/events/summary?days=' + evWindow).then(r => r.ok ? r.json() : null).then(d => evSummary = d)" class="text-[10px] font-mono border border-[#E4E9F0] rounded px-1 py-0.5 bg-white dark:bg-zinc-900">
                                <option :value="7">7T</option><option :value="14">14T</option><option :value="30">30T</option>
                            </select> <span class="text-[10px] font-mono text-[#9CA3AF]" x-text="evSummary ? evSummary.total + ' ' + t('gesamt') + (evSummary.avg_per_day ? ' · Ø ' + evSummary.avg_per_day + (lang==='en'?'/day':'/Tag') : '') + (evSummary.last_event_at ? ' · ' + t('Stand') + ' ' + new Date(evSummary.last_event_at).toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : '') : ''"></span></div>
                        <div class="px-5 py-4">
                            <div class="flex items-end gap-1.5 h-16">
                                <template x-for="d in evDays()" :key="d.key">
                                    <a :href="'/app/events?tenant=' + tenant + '&day=' + d.key" class="flex-1 flex flex-col items-center gap-1 hover:bg-[#FAFBFC] rounded" :title="d.label + ': ' + d.n + t(' Ereignisse — Tag filtern')">
                                        <div class="w-full bg-[#F0F3F7] rounded-sm flex items-end" style="height: 100%;">
                                            <div class="w-full bg-[#CA8A04] rounded-sm transition-all" :style="'height:' + Math.max(4, d.pct) + '%'"></div>
                                        </div>
                                        <span class="text-[9px] font-mono text-[#9CA3AF]" x-text="d.label.split(' ')[0]"></span>
                                    </a>
                                </template>
                            </div>
                            <div class="flex items-end gap-1.5 h-10 mt-2" x-show="evSummary && evSummary.by_weekday">
                                <template x-for="w in evWeekdays()" :key="'wd'+w.d">
                                    <a :href="'/app/events?tenant=' + tenant + '&wd=' + w.d" class="flex-1 flex flex-col items-center gap-0.5 hover:bg-[#FAFBFC] rounded" :title="w.label + ': ' + w.n + t(' Ereignisse — Wochentag filtern')">
                                        <div class="w-full bg-[#F0F3F7] rounded-sm flex items-end" style="height: 100%;"><div class="w-full bg-[#8A6A00] rounded-sm transition-all" :style="'height:' + Math.max(4, w.pct) + '%'"></div></div>
                                        <span class="text-[9px] font-mono text-[#9CA3AF]" x-text="w.label"></span>
                                    </a>
                                </template>
                            </div>
                            <div class="flex items-end gap-1 h-10 mt-2" x-show="evSummary && evSummary.by_hour">
                                <template x-for="h in evHours()" :key="'hr'+h.h">
                                    <a :href="'/app/events?tenant=' + tenant + '&hr=' + h.h" class="flex-1 flex flex-col items-center gap-0.5 hover:bg-[#FAFBFC] rounded" :title="h.h + ' Uhr: ' + h.n + t(' Ereignisse — Stunde filtern')">
                                        <div class="w-full bg-[#F0F3F7] rounded-sm flex items-end" style="height: 100%;"><div class="w-full bg-[#4B5563] rounded-sm transition-all" :style="'height:' + Math.max(4, h.pct) + '%'"></div></div>
                                        <span class="text-[9px] font-mono text-[#9CA3AF]" x-show="h.h % 4 === 0" x-text="h.h"></span>
                                    </a>
                                </template>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mt-3">
                                <template x-for="[g, n] in evGroups()" :key="g">
                                    <a :href="'/app/events?tenant=' + tenant + '&eg=' + g" class="text-[10px] px-2 py-0.5 rounded-full border border-[#E4E9F0] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]" x-text="eventGroup(g) + ' · ' + n"></a>
                                </template>
                                <template x-for="a in evActorsTop()" :key="'w'+a.id">
                                    <a :href="'/app/events?tenant=' + tenant + '&ea=' + a.id" class="text-[10px] px-2 py-0.5 rounded-full border border-dashed border-[#E4E9F0] text-[#9CA3AF] hover:border-[#CA8A04] hover:text-[#CA8A04]" :title="t('Auslöser: ') + a.name" x-text="a.name + ' · ' + a.events"></a>
                                </template>
                                <a x-show="evSummary && evSummary.busiest_day" :href="'/app/events?tenant=' + tenant + '&day=' + (evSummary && evSummary.busiest_day ? evSummary.busiest_day.date : '')" class="text-[10px] px-2 py-0.5 rounded-full bg-[#CA8A04]/10 border border-[#CA8A04]/40 text-[#8A6A00] hover:border-[#CA8A04]" :title="t('Meiste Ereignisse an einem Tag — Tag filtern')" x-text="evSummary && evSummary.busiest_day ? t('Top-Tag') + ' ' + new Date(evSummary.busiest_day.date + 'T00:00:00').toLocaleDateString((this.lang==='en'?'en-GB':'de-DE'), {day:'2-digit', month:'2-digit'}) + ' · ' + evSummary.busiest_day.events : ''"></a>
                                <template x-for="s in evSubjectsTop()" :key="'s'+s.id">
                                    <a :href="topSubjectLink(s)" class="text-[10px] px-2 py-0.5 rounded-full bg-[#FFFDF2] border border-[#EAD98B] text-[#8A6A00] hover:border-[#CA8A04]" :title="'Betreffend: ' + s.title" x-text="s.title + ' · ' + s.events"></a>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div x-show="events.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E] flex justify-between items-center"><span x-text="t('Letzte Ereignisse')"></span> <a :href="'/app/events?tenant=' + tenant" class="text-[10px] text-[#CA8A04] hover:underline font-normal"><span x-text="t('Alle →')"></span></a></div>
                        <div class="divide-y divide-[#F0F3F7] max-h-64 overflow-y-auto">
                            <template x-for="(e, i) in events" :key="i">
                                <div>
                                <a x-show="eventLink(e)" :href="eventLink(e)" class="px-5 py-2.5 flex items-center justify-between gap-4 hover:bg-[#FAFBFC] transition">
                                    <span class="text-sm text-[#1A2433] truncate"><span class="text-[11px] font-semibold tracking-wide text-[#CA8A04] uppercase" x-text="eventGroup(e.event_type)"></span> <span x-text="eventLabel(e.event_type)"></span><span x-show="e.event_properties && e.event_properties.subject && e.event_properties.subject.title" class="text-[#5B6B7E]" x-text="' · ' + e.event_properties.subject.title"></span><span x-show="e.meta_data && e.meta_data.actor" class="text-[#9CA3AF]" x-text="' · ' + (e.meta_data && e.meta_data.actor ? e.meta_data.actor.name : '')"></span></span>
                                    <span class="text-[11px] text-[#9CA3AF] font-mono shrink-0" x-text="ago(e.created_at)"></span>
                                </a>
                                <div x-show="!eventLink(e)" class="px-5 py-2.5 flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#1A2433] truncate"><span class="text-[11px] font-semibold tracking-wide text-[#CA8A04] uppercase" x-text="eventGroup(e.event_type)"></span> <span x-text="eventLabel(e.event_type)"></span><span x-show="e.meta_data && e.meta_data.actor" class="text-[#9CA3AF]" x-text="' · ' + (e.meta_data && e.meta_data.actor ? e.meta_data.actor.name : '')"></span></span>
                                    <span class="text-[11px] text-[#9CA3AF] font-mono shrink-0" x-text="ago(e.created_at)"></span>
                                </div>
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
                                <button @click="createExecReport()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F]"><span x-text="t('+ Report erstellen')"></span></button>
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
                                        <template x-for="h in [t('Mandant'),t('Unternehmen'),'Personen','Offene Aufgaben','Offene Fristen','Hohe Risiken','Data Lake','Audits offen','Festst. offen',t('Urlaub offen'),t('Aufträge offen'),'Compliance %']" :key="h">
                                            <th scope="col" class="px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E]" x-text="h"></th>
                                        </template>
                                    </tr></thead>
                                    <tbody>
                                        <template x-for="t in exec.tenants" :key="t.id">
                                            <tr class="border-b border-[#F0F3F7] last:border-b-0">
                                                <td class="px-5 py-3 font-medium"><a :href="'/app/dashboard?tenant=' + t.id" :title="'Zu ' + t.name + t(' wechseln')" class="text-[#0B0B0F] hover:text-[#CA8A04] transition" x-text="t.name"></a></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.companies"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.persons"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.tasks_open"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.deadlines_open"></td>
                                                <td class="px-5 py-3 font-mono" :class="t.high_risks > 0 ? 'text-[#A6362E] font-semibold' : ''" x-text="t.high_risks"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.data_objects"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.audits_open"></td>
                                                <td class="px-5 py-3 font-mono" :class="t.findings_open > 0 ? 'text-[#B45309] font-semibold' : ''" x-text="t.findings_open"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.leave_pending"></td>
                                                <td class="px-5 py-3 font-mono" x-text="t.orders_open"></td>
                                                <td class="px-5 py-3 font-mono" :class="t.compliance_rate < 50 ? 'text-[#A6362E] font-semibold' : (t.compliance_rate < 80 ? 'text-[#B45309]' : 'text-[#15803D]')" x-text="t.compliance_rate"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div x-show="execReports.length" class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                                <div class="px-5 py-3 border-b border-[#E4E9F0] text-xs font-medium text-[#5B6B7E]"><span x-text="t('Reports')"></span></div>
                                <div class="divide-y divide-[#F0F3F7]">
                                    <template x-for="r in execReports" :key="r.id">
                                        <div>
                                            <div @click="toggleReport(r)" class="px-5 py-2.5 flex items-center justify-between gap-4 cursor-pointer hover:bg-[#FAFBFC]">
                                                <span class="text-sm text-[#1A2433]" x-text="r.title"></span>
                                                <span class="flex items-center gap-3">
                                                    <span class="text-[11px] text-[#9CA3AF] font-mono" x-text="fmt(r.created_at)"></span>
                                                    <span class="text-[10px] text-[#CA8A04]" x-text="reportOpen[r.id] ? '▾' : '▸'"></span>
                                                </span>
                                            </div>
                                            <div x-show="reportOpen[r.id]" class="px-5 pb-3" x-transition>
                                                <div x-show="!reportData[r.id]" class="text-[11px] text-[#9CA3AF] py-2"><span x-text="t('Lade Report…')"></span></div>
                                                <template x-if="reportData[r.id] && reportData[r.id].payload">
                                                    <div class="space-y-1">
                                                        <template x-for="t in (reportData[r.id].payload.tenants || [])" :key="t.id">
                                                            <div class="flex items-center justify-between text-[12px] py-1 border-b border-[#F0F3F7] last:border-0">
                                                                <span class="font-medium text-[#1A2433]" x-text="t.name"></span>
                                                                <span class="text-[#5B6B7E] font-mono text-[11px]" x-text="(t.companies||0)+' Unt · '+(t.persons||0)+' Pers · '+(t.tasks_open||0)+' Aufg · '+(t.high_risks||0)+' Risiko · '+(t.compliance_rate!=null?t.compliance_rate+'%':'—')+' Compl.'"></span>
                                                            </div>
                                                        </template>
                                                        <div x-show="reportData[r.id].payload.totals" class="text-[11px] text-[#9CA3AF] pt-1" x-text="'Gesamt: ' + (reportData[r.id].payload.totals.tenants || 0) + ' Mandanten'"></div>
                                                    </div>
                                                </template>
                                            </div>
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
                            <span x-text="filtered().length + ' / ' + (rowsTotal || (rows ? (rows||[]).length : 0)) + ' ' + eintrag(rowsTotal || (rows ? (rows||[]).length : 0))"></span>
                            <span x-show="rows && (rows||[]).some(r => overdue(r))" class="text-[#A6362E]" x-text="'· ' + (rows||[]).filter(r => overdue(r)).length + ' ' + t('überfällig')"></span>
                            <span x-show="rows && (rows||[]).some(r => dueSoon(r))" class="text-[#B45309]" x-text="'· ' + (rows||[]).filter(r => dueSoon(r)).length + t(' ≤ 7 Tage')"></span>
                        </span>
                        <div class="relative" data-tour="listsearch">
                            <input x-ref="search" x-model.debounce.200ms="query" @keydown.enter="if (filtered().length) { detail = sorted(filtered())[0]; $event.target.blur(); }" :placeholder="t('Suchen in ') + title() + '… (/)'" class="w-40 sm:w-48 lg:w-64 rounded-lg border-[#D6DEE9] text-xs py-1.5 pr-6 focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                            <button x-show="query" @click="query = ''; $refs.search.focus()" class="absolute right-1.5 top-1/2 -translate-y-1/2 text-[#9CA3AF] hover:text-[#5B6B7E] text-xs leading-none" :aria-label="t('Suche löschen')">&times;</button>
                        </div>
                        <div class="relative shrink-0">
                            <button @click="colPicker = !colPicker" :title="t('Spalten (c)')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition"><span x-text="t('Spalten')"></span><span x-show="Object.values(hiddenCols).filter(Boolean).length" class="ml-1 text-[#CA8A04]" x-text="'(' + Object.values(hiddenCols).filter(Boolean).length + ')'"></span></button>
                            <button @click="compact = !compact; try { localStorage.setItem('af_density', compact ? '1' : '0'); } catch (e) {}" :title="compact ? 'Normale Zeilenhöhe' : 'Kompakte Zeilenhöhe'"
                                    class="text-xs px-3 py-1.5 border rounded-lg transition shrink-0"
                                    :class="compact ? 'border-[#CA8A04] text-[#CA8A04] bg-[#CA8A04]/5' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]'">≡</button>
                            <div x-show="colPicker" @click.outside="colPicker = false" class="absolute right-0 mt-1.5 w-48 bg-white border border-[#E4E9F0] rounded-lg shadow-lg py-1 z-20 max-h-64 overflow-y-auto" style="display:none">
                                <button x-show="Object.values(hiddenCols).some(Boolean)" @click="hiddenCols = {}; saveColPrefs()" class="w-full text-left px-3 py-1.5 text-xs text-[#CA8A04] hover:bg-[#CA8A04]/10 border-b border-[#E4E9F0]"><span x-text="t('Alle einblenden')"></span></button>
                                <template x-for="c in columns" :key="c">
                                    <label class="flex items-center gap-2 px-3 py-1.5 text-xs text-[#1A2433] hover:bg-[#FAFBFC] cursor-pointer">
                                        <input type="checkbox" :aria-label="t('Spalte ') + label(c)" :checked="!hiddenCols[c]" @change="toggleCol(c)" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                        <span x-text="label(c)"></span>
                                    </label>
                                </template>
                            </div>
                        </div>
                        <select x-model="groupBy" :title="t('Gruppieren nach')" class="text-xs px-2 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg bg-white hover:border-[#CA8A04] transition shrink-0 max-w-[10rem]">
                            <option value=""><span x-text="t('Keine Gruppierung')"></span></option>
                            <option x-show="rows && (rows||[]).some(r => r.updated_at)" value="__period"><span x-text="t('Zeitraum')"></span></option>
                            <template x-for="c in columns" :key="'g-'+c">
                                <option :value="c" x-text="label(c)"></option>
                            </template>
                        </select>
                        <button x-show="groupBy && Object.keys(collapsedGroups).length" @click="collapsedGroups = {}; localStorage.removeItem('af_gc_' + section)" :title="t('Alle Gruppen aufklappen')" class="text-[11px] px-2 py-1.5 text-[#9CA3AF] hover:text-[#CA8A04] transition shrink-0"><span x-text="t('alle auf')"></span></button>
                        <div class="relative shrink-0">
                            <button @click="viewPicker = !viewPicker" :title="t('Gespeicherte Ansichten')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition"><span x-text="t('Ansichten')"></span><span x-show="Object.keys(views()).length" class="ml-1 text-[#CA8A04]" x-text="'(' + Object.keys(views()).length + ')'"></span></button>
                            <div x-show="viewPicker" @click.outside="viewPicker = false" class="absolute right-0 mt-1.5 w-56 bg-white border border-[#E4E9F0] rounded-lg shadow-lg py-1 z-20" style="display:none">
                                <button @click="saveView()" class="w-full text-left px-3 py-1.5 text-xs text-[#CA8A04] hover:bg-[#CA8A04]/10 border-b border-[#E4E9F0]">+ <span x-text="t('Aktuelle Ansicht speichern')"></span></button>
                                <p x-show="!Object.keys(views()).length" class="px-3 py-2 text-[11px] text-[#9CA3AF]"><span x-text="t('Noch keine Ansichten.')"></span></p>
                                <template x-for="(v, name) in views()" :key="name">
                                    <div class="flex items-center hover:bg-[#FAFBFC]">
                                        <button @click="applyView(name)" class="flex-1 text-left px-3 py-1.5 text-xs text-[#1A2433] truncate" x-text="name"></button>
                                        <button @click="deleteView(name)" class="px-2 text-[#9CA3AF] hover:text-[#A6362E] text-xs" :title="t('Ansicht löschen')">&times;</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <button @click="exportCsv()" :title="t('CSV exportieren')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition shrink-0">CSV</button>
                        <button x-show="section === 'notifications'" @click="exportNotifs()" :title="t('Benachrichtigungen als NDJSON exportieren (aktive Filter werden übernommen)')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition shrink-0">NDJSON</button>
                        <button x-show="section === 'events'" @click="exportEvents()" :title="t('Serverseitiger Audit-Export (NDJSON, alle Einträge)')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition shrink-0">NDJSON</button>
                        <button x-show="canImport()" @click="showImport = true; importText = ''; importResult = ''" :title="t('CSV importieren (i)')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition shrink-0">CSV ↑</button>
                        <button @click="window.print()" :title="t('Drucken')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition shrink-0"><span x-text="t('Drucken')"></span></button>
                        <button x-show="section === 'tokens' && rows && (rows||[]).length > 1" @click="revokeAllTokens()" :title="t('Alle API-Token widerrufen')" class="text-xs px-3 py-1.5 border border-[#A6362E] text-[#A6362E] rounded-lg hover:bg-[#A6362E] hover:text-white transition shrink-0"><span x-text="t('Alle widerrufen')"></span></button>
                        <button data-tour="new" x-show="canCreate()" @click="openCreate()" :title="t('Neu anlegen: ') + title() + ' (n)'" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition shrink-0"x-text="'+ ' + t('Neu')"></button>
                    </div>
                    <div x-show="rows && recentRows().some(r => r.key === section)" class="flex items-center gap-1.5 px-5 py-1.5 border-b border-[#E4E9F0] print:hidden overflow-x-auto">
                        <span class="text-[10px] font-semibold tracking-widest text-[#9CA3AF] shrink-0" x-text="t('Zuletzt').toUpperCase()"></span>
                        <template x-for="rr in recentRows().filter(r => r.key === section).slice(0, 5)" :key="rr.key + rr.id">
                            <span class="inline-flex items-center border rounded-full transition group/rr"
                                    :class="detail && String(detail.id) === String(rr.id) ? 'border-[#CA8A04] text-[#CA8A04] bg-[#CA8A04]/5' : 'border-[#E4E9F0] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]'">
                                <button @click="const t = (rows || []).find(x => String(x.id) === String(rr.id)); if (t) detail = t"
                                        class="text-[11px] pl-2 py-0.5 whitespace-nowrap" x-text="'↻ ' + (rr.name || rr.id)"></button>
                                <button @click.stop="removeRecentRow(rr)" :title="t('Entfernen')"
                                        class="text-[10px] px-1 text-[#9CA3AF] hover:text-[#A6362E] opacity-0 group-hover/rr:opacity-100 transition">&times;</button>
                            </span>
                        </template>
                        <button x-show="recentRows().filter(r => r.key === section).length > 1" @click="clearRecentSection()" :title="t('Zuletzt-Liste für diese Sektion leeren')" class="ml-auto shrink-0 text-[10px] text-[#9CA3AF] hover:text-[#A6362E]"><span x-text="t('leeren')"></span> ×</button>
                    </div>
                    <div x-show="rows && (statusOpts().length > 1 || (rows||[]).some(r => overdue(r)) || section === 'notifications' || section === 'tooldata')" class="flex flex-wrap items-center gap-1.5 px-5 py-2 border-b border-[#E4E9F0] print:hidden">
                        <button x-show="(rows||[]).some(r => overdue(r))" @click="overdueOnly = !overdueOnly" :title="t('Überfällig (u)')" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="overdueOnly ? 'border-[#A6362E] bg-[#A6362E] text-white' : 'border-[#A6362E]/40 text-[#A6362E] hover:bg-[#A6362E]/5'"
                                x-text="t('Überfällig') + ' · ' + (rows||[]).filter(r => overdue(r)).length"></button>
                        <button x-show="(rows||[]).some(r => dueToday(r))" @click="dueTodayOnly = !dueTodayOnly" :title="t('Heute fällig')" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="dueTodayOnly ? 'border-[#CA8A04] bg-[#CA8A04] text-black' : 'border-[#CA8A04]/40 text-[#B45309] hover:bg-[#CA8A04]/10'"
                                x-text="t('Heute · ') + (rows||[]).filter(r => dueToday(r)).length"></button>
                        <button x-show="(rows||[]).some(r => dueSoon(r))" @click="dueSoonOnly = !dueSoonOnly" :title="t('≤7 Tage (s)')" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="dueSoonOnly ? 'border-[#B45309] bg-[#B45309] text-white' : 'border-[#B45309]/40 text-[#B45309] hover:bg-[#B45309]/5'"
                                x-text="t('≤ 7 Tage · ') + (rows||[]).filter(r => dueSoon(r)).length"></button>
                        <button x-show="(rows||[]).some(r => 'assignee_id' in r || 'responsible_id' in r || 'owner_id' in r)" @click="myOnly = !myOnly" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="myOnly ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"><span x-text="t('Mir zugewiesen')"></span></button>
                        <button x-show="(rows||[]).some(r => 'assignee_id' in r || 'responsible_id' in r || 'owner_id' in r || 'assigned_to' in r) && (rows||[]).some(r => !(r.assignee_id || r.responsible_id || r.owner_id || r.assigned_to))" @click="unassignedOnly = !unassignedOnly" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="unassignedOnly ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                x-text="'Ohne Verantwortlichen · ' + (rows||[]).filter(r => !(r.assignee_id || r.responsible_id || r.owner_id || r.assigned_to)).length"></button>
                        <button x-show="section === 'events'" @click="evDay = new Date().toISOString().slice(0,10)" :class="evDay === new Date().toISOString().slice(0,10) ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'" class="text-[11px] px-2.5 py-1 rounded-full border transition"><span x-text="t('Heute')"></span></button>
                        <button x-show="section === 'events'" @click="evActor = evActor === 'me' ? '' : 'me'" :class="evActor === 'me' ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'" class="text-[11px] px-2.5 py-1 rounded-full border transition"><span x-text="t('Von mir')"></span></button>
                        <button x-show="section === 'events' && evActorName" @click="evActorName = ''" class="text-[11px] px-2.5 py-1 rounded-full border border-[#0B0B0F] bg-[#0B0B0F] text-white transition">von: <span x-text="evActorName"></span> ×</button>
                            <button x-show="evDay" @click="evDay = ''" class="text-[11px] px-2.5 py-1 rounded-full border border-[#CA8A04] text-[#CA8A04] transition" x-text="t('Tag') + ': ' + new Date(evDay + 'T12:00').toLocaleDateString((this.lang==='en'?'en-GB':'de-DE')) + ' ×'"></button>
                            <button x-show="evWeekday" @click="evWeekday = ''" class="text-[11px] px-2.5 py-1 rounded-full border border-[#CA8A04] text-[#CA8A04] transition" x-text="t('Wochentag: ') + ['','So','Mo','Di','Mi','Do','Fr','Sa'][evWeekday] + ' ×'"></button>
                            <button x-show="evHour !== ''" @click="evHour = ''" class="text-[11px] px-2.5 py-1 rounded-full border border-[#CA8A04] text-[#CA8A04] transition" x-text="t('Stunde: ') + String(evHour).padStart(2,'0') + ' Uhr ×'"></button>
                            <template x-for="t in evTypeGroups()" :key="'eg'+t.g">
                            <button x-show="section === 'events'" @click="evGroup = evGroup === t.g ? '' : t.g" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                    :class="evGroup === t.g ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                    x-text="t.g + ' · ' + t.events"></button>
                        </template>
                        <template x-for="tt in tdTypes()" :key="'tt'+tt.k">
                            <button x-show="section === 'tooldata'" @click="tdType = tdType === tt.k ? '' : tt.k; columns = tdColumns()" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                    :class="tdType === tt.k ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                    x-text="tt.label + ' · ' + tt.n"></button>
                        </template>
                        <template x-for="a in (evActors || [])" :key="'ea'+a.id">
                            <button x-show="section === 'events'" @click="evActor = String(evActor) === String(a.id) ? '' : String(a.id)" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                    :class="String(evActor) === String(a.id) ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                    x-text="t('von ') + a.name + ' · ' + a.events"></button>
                        </template>
                        <button @click="statusFilter = ''" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="statusFilter === '' ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                x-text="'Alle · ' + (rows||[]).length"></button>
                        <template x-for="s in statusOpts()" :key="s">
                            <button @click="statusFilter = statusFilter === s ? '' : s" class="text-[11px] px-2.5 py-1 rounded-full border transition inline-flex items-center gap-1.5"
                                    :class="statusFilter === s ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'">
                                <span class="w-1.5 h-1.5 rounded-full" :style="'background:' + statusColor(s)"></span>
                                <span x-text="statusLabel(s) + ' · ' + (rows||[]).filter(r => String(r.status) === s).length"></span>
                            </button>
                        </template>
                        <template x-for="sv in severityOpts()" :key="'sev-' + sv">
                            <button @click="severityFilter = severityFilter === sv ? '' : sv" class="text-[11px] px-2.5 py-1 rounded-full border transition inline-flex items-center gap-1.5"
                                    :class="severityFilter === sv ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'">
                                <span class="w-1.5 h-1.5 rounded-full" :style="'background:' + statusColor(sv)"></span>
                                <span x-text="t('Schwere ') + statusLabel(sv) + ' · ' + (rows||[]).filter(r => String(r.severity) === sv).length"></span>
                            </button>
                        </template>
                        <template x-for="k in [...new Set([...notifKinds, ...(rows || []).map(r => r.kind)].filter(Boolean))]" :key="'kind-' + k">
                            <button x-show="section === 'notifications'" @click="kindFilter = kindFilter === k ? '' : k" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                    :class="kindFilter === k ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                    x-text="t('Art ') + (nKind(k)) + ' · ' + (rows||[]).filter(r => r.kind === k).length"></button>
                        </template>
                        <template x-for="c in [...new Set([...notifCodes, ...(rows || []).map(r => r.code)].filter(Boolean))]" :key="'code-' + c">
                            <button x-show="section === 'notifications'" @click="codeFilter = codeFilter === c ? '' : c" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                    :class="codeFilter === c ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                    x-text="'Code ' + c + ' · ' + (rows||[]).filter(r => r.code === c).length"></button>
                        </template>
                        <span x-show="section === 'notifications' && notifStats" class="text-[11px] px-2.5 py-1 rounded-full border border-[#D6DEE9] text-[#5B6B7E]"
                              x-text="notifStats ? 'Gesamt ' + notifStats.total + ' · Ungelesen ' + notifStats.unread + ' · Gelesen ' + notifStats.read + t(' · Stumm ') + notifStats.muted : ''"></span>
                        <button x-show="section === 'notifications' && rows && (rows||[]).some(r => !r.read)" @click="unreadOnly = !unreadOnly" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="unreadOnly ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                x-text="'Ungelesen · ' + (rows||[]).filter(r => !r.read).length"></button>
                        <button x-show="section === 'notifications' && rows && (rows||[]).some(r => r.muted)" @click="mutedOnly = !mutedOnly" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                :class="mutedOnly ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'"
                                x-text="t('Stumm · ') + (rows||[]).filter(r => r.muted).length"></button>
                        <button x-show="section === 'notifications' && rows && (rows||[]).some(r => !r.read)" @click="markAllNotifsRead(); toast('Alle als gelesen markiert')" class="text-[11px] px-2.5 py-1 rounded-full border border-[#CA8A04]/50 text-[#CA8A04] hover:bg-[#CA8A04]/10 transition"><span x-text="t('Alle gelesen')"></span></button>
                        <button x-show="section === 'notifications' && rows && (rows||[]).some(r => r.read)" @click="deleteReadNotifs()" class="text-[11px] px-2.5 py-1 rounded-full border border-[#D6DEE9] text-[#5B6B7E] hover:border-[#A6362E] hover:text-[#A6362E] transition"><span x-text="t('Gelesene entfernen')"></span></button>
                        <button x-show="section === 'notifications' && rows && (rows||[]).length > 1" @click="if (confirm(t('Alle Benachrichtigungen entfernen?'))) this.api('/api/v1/notifications', {method: 'DELETE'}).then(r => r.ok ? r.json() : null).then(d => { if (d) { this.toast((d.deleted ?? 0) + ' entfernt'); this.loadSection(); } })" class="text-[11px] px-2.5 py-1 rounded-full border border-[#D6DEE9] text-[#5B6B7E] hover:border-[#A6362E] hover:text-[#A6362E] transition"><span x-text="t('Alle entfernen')"></span></button>
                        <template x-for="rn in roleOpts()" :key="'role-' + rn">
                            <button @click="roleFilter = roleFilter === rn ? '' : rn" class="text-[11px] px-2.5 py-1 rounded-full border transition"
                                    :class="roleFilter === rn ? 'border-[#0B0B0F] bg-[#0B0B0F] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04]'">
                                <span x-text="t('Rolle ') + roleLabel(rn) + ' · ' + (rows||[]).filter(r => (r.role_names || []).includes(rn)).length"></span>
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
                        <p class="text-sm text-[#5B6B7E]" x-text="query || statusFilter || severityFilter || roleFilter || kindFilter || codeFilter || unreadOnly || mutedOnly || evGroup || evDay || evActor || evActorName || evWeekday || evHour !== '' || overdueOnly || dueSoonOnly || dueTodayOnly || myOnly || unassignedOnly ? t('Keine Einträge für diese Filter.') : t('Keine Einträge vorhanden.')"></p>
                        <button x-show="query || statusFilter || severityFilter || roleFilter || kindFilter || codeFilter || unreadOnly || mutedOnly || evGroup || evDay || evActor || overdueOnly || dueSoonOnly || dueTodayOnly || myOnly || unassignedOnly" @click="query = ''; statusFilter = ''; severityFilter = ''; roleFilter = ''; kindFilter = ''; codeFilter = ''; unreadOnly = false; mutedOnly = false; evGroup = ''; evDay = ''; evActor = ''; evActorName = ''; evWeekday = ''; evHour = ''; overdueOnly = false; dueSoonOnly = false; dueTodayOnly = false; myOnly = false; unassignedOnly = false"
                                :title="t('Filter zurücksetzen (x)')" class="mt-3 text-xs px-3.5 py-2 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04] transition"><span x-text="t('Filter zurücksetzen')"></span></button>
                        <button x-show="canCreate() && !query && !statusFilter && !severityFilter && !roleFilter && !kindFilter && !codeFilter && !unreadOnly && !mutedOnly && !evGroup && !overdueOnly && !dueSoonOnly && !dueTodayOnly && !myOnly && !unassignedOnly" @click="openCreate()"
                                class="mt-3 text-xs px-3.5 py-2 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition"><span x-text="t('+ Ersten Eintrag erstellen')"></span></button>
                    </div>
                    <div x-show="selCount() > 0" class="flex flex-wrap items-center gap-2 px-5 py-2.5 border-b border-[#E4E9F0] bg-[#FFFBEB]">
                        <span class="text-xs font-semibold text-[#0B0B0F]"><span x-text="selCount()"></span> <span x-text="t('ausgewählt')"></span></span>
                        <span x-show="selSums()" class="text-[11px] text-[#5B6B7E]" x-text="selSums()"></span>
                        <template x-for="a in sectionActions()" :key="a[1]">
                            <button @click="bulkStatus(a[1])" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10" x-text="a[0]"></button>
                        </template>
                        <button @click="copySel()" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" :title="t('Als TSV in die Zwischenablage')"><span x-text="t('Kopieren')"></span></button>
                        <button @click="invertSel()" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" :title="t('Auswahl umkehren (sichtbare Zeilen)')"><span x-text="t('Invertieren')"></span></button>
                        <button @click="exportCsv((this.rows || []).filter(r => selected[r.id]))" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]">CSV</button>
                        <button @click="exportJson((this.rows || []).filter(r => selected[r.id]))" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" :title="t('Ausgewählte Zeilen als JSON-Datei')">JSON</button>
                        <template x-if="section === 'notifications'">
                            <span class="inline-flex gap-2">
                                <button @click="notifBatch('read')" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10" :title="t('Ausgewählte als gelesen markieren')"><span x-text="t('Gelesen')"></span></button>
                                <button @click="notifBatch('unread')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" :title="t('Ausgewählte als ungelesen markieren')"><span x-text="t('Ungelesen')"></span></button>
                            </span>
                        </template>
                        <button @click="bulkDelete()" class="text-xs px-3 py-1.5 border border-[#A6362E]/40 text-[#A6362E] rounded-lg hover:bg-[#A6362E]/5"><span x-text="t('Löschen')"></span></button>
                        <button @click="selected = {}" class="text-xs px-3 py-1.5 text-[#5B6B7E] hover:text-[#0B0B0F]"><span x-text="t('Auswahl aufheben')"></span></button>
                    </div>
                    <div class="max-h-[70vh] overflow-y-auto">
                    <table x-show="rows && filtered().length" class="w-full text-sm" :aria-busy="!rows">
                        <thead>
                            <tr class="border-b border-[#E4E9F0] bg-[#FAFBFC] text-left">
                                <th x-show="writable()" scope="col" class="px-4 py-3 w-10">
                                    <input type="checkbox" :aria-label="t('Alle sichtbaren Zeilen auswählen')" @change="toggleAll($event.target.checked)" :checked="sorted(filtered()).length > 0 && selCount() === sorted(filtered()).length"
                                           x-effect="$el.indeterminate = selCount() > 0 && selCount() < sorted(filtered()).length"
                                           class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                </th>
                                <th scope="col" class="px-3 py-3 text-[11px] font-semibold tracking-wide text-[#9CA3AF] w-8">#</th>
                                <template x-for="c in visCols()" :key="c">
                                    <th scope="col" @click="sort(c)" :title="'Sortieren: ' + label(c) + (sortKey===c ? (sortAsc ? ' (aufsteigend)' : ' (absteigend)') : '')" class="sticky top-0 z-10 bg-[#FAFBFC] px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E] cursor-pointer select-none hover:text-[#0B0B0F]">
                                        <span x-text="label(c)"></span><span class="ml-1 text-[#CA8A04]" x-text="sortKey===c ? (sortAsc?'▲':'▼') : ''"></span>
                                    </th>
                                </template>
                                <th x-show="sectionActions().length || canEdit()" scope="col" class="px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E] w-28"><span x-text="t('Aktionen')"></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="it in renderRows()" :key="it.t === 'h' ? 'h-'+it.label : it.r.id">
                                <tr @click="it.t === 'r' ? (detail = it.r) : toggleGroupHeader(it.label)" @dblclick="it.t === 'r' && canEdit() && (detail = it.r, openEdit())"
                                    :class="it.t === 'h' ? 'bg-[#F0F3F7] hover:bg-[#E4E9F0] cursor-pointer' : ([overdue(it.r) ? 'bg-[#A6362E]/5' : '', selected[it.r.id] ? 'bg-[#FFFBEB]' : '', detail && detail.id === it.r.id ? 'bg-[#FACC15]/10' : '', it.i % 2 ? 'bg-[#FAFBFC]/50' : '', section === 'notifications' && (it.r.read || it.r.muted) ? 'opacity-50' : ''].join(' ') + ' hover:bg-[#F3F6FA] cursor-pointer')"
                                    class="border-b border-[#F0F3F7] last:border-b-0" :title="it.t === 'r' ? 'Doppelklick: Bearbeiten' : 'Klick: einklappen/ausklappen'">
                                    <template x-if="it.t === 'h'">
                                        <td :colspan="visCols().length + 1 + (writable() ? 1 : 0) + (sectionActions().length || canEdit() ? 1 : 0)" class="px-5 py-2 text-[11px] font-semibold text-[#5B6B7E]">
                                            <span x-text="collapsedGroups[it.label] ? '▸' : '▾'" class="mr-1.5 text-[#CA8A04]"></span><span x-text="it.disp || it.label || '—'"></span> <span class="font-normal text-[#9CA3AF]" x-text="'· ' + it.count"></span><span x-show="it.overdue" class="ml-1.5 font-normal text-[#A6362E]" x-text="it.overdue + ' ' + t('überfällig')"></span>
                                        </td>
                                    </template>
                                    <template x-if="it.t === 'r'">
                                        <template x-for="e in it.cells" :key="e.t + ':' + (e.c || '')">
                                            <td x-show="e.t === 'cb' ? writable() : (e.t === 'act' ? (sectionActions().length || canEdit()) : true)"
                                                :class="e.t === 'cb' ? 'px-4 py-3 w-10' : (e.t === 'n' ? 'px-3 py-3 text-[#9CA3AF] text-xs tabular-nums' : (e.t === 'act' ? 'px-5 py-3 w-28' : 'px-5 text-[#1A2433] ' + (compact ? 'py-1.5 text-xs' : 'py-3')))"
                                                class="px-5 py-3">
                                                <template x-if="e.t === 'cb'">
                                                    <input type="checkbox" :aria-label="t('Zeile auswählen: ') + (it.r.name || it.r.title || it.r.headline || it.r.id)" @click.stop @change="toggleSel(it.r.id)" :checked="!!selected[it.r.id]"
                                                           class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                                </template>
                                                <template x-if="e.t === 'n'">
                                                    <span x-text="it.i + 1"></span>
                                                </template>
                                                <template x-if="e.t === 'c'">
                                                    <span><span x-html="cell(it.r, e.c)"></span><span x-show="e.c === visCols()[0] && isNew(it.r)" class="ml-1.5 text-[9px] font-bold px-1 py-0.5 rounded bg-[#FACC15] text-[#0B0B0F] align-middle"><span x-text="t('NEU')"></span></span></span>
                                                </template>
                                                <template x-if="e.t === 'act'">
                                                    <div class="flex gap-1 items-center" @click.stop>
                                                        <template x-for="a in rowActions(it.r).slice(0, 2)" :key="a[1]">
                                                            <button @click="applyRowStatus(it.r, a[1])" class="text-[10px] px-2 py-1 rounded-md border border-[#CA8A04]/50 text-[#CA8A04] hover:bg-[#CA8A04]/10 whitespace-nowrap" x-text="a[0]"></button>
                                                        </template>
                                                        <button x-show="['documents','data-objects'].includes(section)" @click="downloadDoc(it.r)" :title="t('Herunterladen')" class="text-[11px] px-1.5 py-1 rounded-md border border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]">&#11123;</button>
                                                        <button x-show="canEdit() && section !== 'documents'" @click="detail = it.r; openDuplicate()" :title="t('Duplizieren')" class="text-[11px] px-1.5 py-1 rounded-md border border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]">&#10697;</button>
                                                        <button x-show="canEdit()" @click="detail = it.r; openEdit()" :title="t('Bearbeiten')" class="text-[11px] px-1.5 py-1 rounded-md border border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]">&#9998;</button>
                                                        <button x-show="canEdit()" @click="deleteRow(it.r)" :title="t('Löschen')" class="text-[11px] px-1.5 py-1 rounded-md border border-[#A6362E]/40 text-[#A6362E] hover:bg-[#A6362E]/5">&#128465;</button>
                                                    </div>
                                                </template>
                                            </td>
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
                            <span x-text="t('Mehr laden') + ' ('"></span><span x-text="filtered().length - limit"></span> <span x-text="t('weitere') + ')'"></span>
                        </button>
                        <button x-show="filtered().length - limit > 100" @click="limit = filtered().length" :title="t('Alle laden (l)')" class="text-xs text-[#5B6B7E] hover:text-[#CA8A04] hover:underline">
                            <span x-text="t('Alle laden')"></span>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Detail drawer --}}
        <div x-show="detail" class="fixed inset-0 z-40" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="detail = null"></div>
            <div x-ref="drawer" tabindex="-1" class="absolute inset-y-0 right-0 w-full bg-white shadow-xl flex flex-col transition-[max-width] duration-200 outline-none" :class="drawerWide ? 'max-w-2xl' : 'max-w-md'" role="dialog" aria-modal="true" :aria-label="title() + ' · Details'">
                <div class="px-6 py-4 border-b border-[#E4E9F0] flex items-center justify-between">
                    <h2 class="font-semibold text-[#0B0B0F] flex items-center gap-2 min-w-0"><span class="shrink-0 text-[#CA8A04] text-sm" x-text="icons[section] || ''"></span><span class="truncate" x-text="detailTitle()"></span><span x-show="overdue(detail)" class="shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded bg-[#A6362E] text-white"><span x-text="t('ÜBERFÄLLIG')"></span></span></h2>
                    <div class="flex items-center gap-1">
                        <button @click="drawerWide = !drawerWide; try { localStorage.setItem('af_drawer_wide', drawerWide ? '1' : '0'); } catch (e) {}" class="p-1.5 rounded-lg text-[#9CA3AF] hover:text-[#0B0B0F] hover:bg-[#F0F3F7] transition" :title="drawerWide ? 'Schmal' : t('Breit')">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v3m0 0l-3-3m3 3l3-3M16 21v-3m0 0l-3 3m3-3l3 3"/></svg>
                        </button>
                        <button @click="navDetail(-1)" :disabled="!hasNav(-1)" :class="hasNav(-1) ? 'text-[#9CA3AF] hover:text-[#0B0B0F] hover:bg-[#F0F3F7]' : 'text-[#E4E9F0] cursor-not-allowed'" class="p-1.5 rounded-lg transition" :title="t('Vorheriger (←)')"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
                        <button @click="navDetail(1)" :disabled="!hasNav(1)" :class="hasNav(1) ? 'text-[#9CA3AF] hover:text-[#0B0B0F] hover:bg-[#F0F3F7]' : 'text-[#E4E9F0] cursor-not-allowed'" class="p-1.5 rounded-lg transition" :title="t('Nächster (→)')"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                        <span class="text-[11px] font-mono text-[#9CA3AF] px-1" x-text="detailPos()"></span>
                    </div>
                    <button x-show="!['dashboard','executive'].includes(section)" @click="reloadRow()" class="text-[#5B6B7E] hover:text-[#CA8A04] inline-block" :class="rowLoading && 'animate-spin'" :title="t('Aktualisieren')">&#8635;</button>
                    <button @click="detail = null" class="text-[#5B6B7E] hover:text-[#0B0B0F]" :title="t('Schließen (Esc)')" :aria-label="t('Schließen')">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-6">
                    <dl class="space-y-3 text-sm">
                        <template x-for="k in detailKeys()" :key="k">
                            <div class="flex gap-3 group">
                                <dt class="w-36 shrink-0 text-[#5B6B7E]" :title="k" x-text="label(k)"></dt>
                                <dd class="min-w-0 flex-1 font-mono text-[13px] text-[#1A2433] break-words">
                                    <div x-show="section === 'ai-analyses' && k === 'findings' && Array.isArray(detail[k])" class="space-y-1.5 font-sans">
                                        <template x-for="(f, i) in detail[k]" :key="i">
                                            <div class="flex items-start gap-2">
                                                <span class="mt-1 h-2 w-2 rounded-full shrink-0" :style="'background:' + statusColor(f.severity)"></span>
                                                <div class="min-w-0">
                                                    <span class="text-[#1A2433]" x-text="f.message"></span>
                                                    <a x-show="insightSection(f.code)" :href="'/app/' + insightSection(f.code) + '?tenant=' + tenant + (insightFilter(f.code) ? '&' + insightFilter(f.code) : '')"
                                                       class="ml-1.5 text-[11px] text-[#CA8A04] hover:underline whitespace-nowrap" x-text="'→ ' + sectionLabel(insightSection(f.code) || '')"></a>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                    <span x-show="!linkOf(detail[k]) && !(k === 'status' || k === 'severity' || k === 'risk_level') && !(section === 'ai-analyses' && k === 'findings' && Array.isArray(detail[k]))" x-text="fmtD(detail, k)"></span>
                                    <span x-show="(k === 'status' || k === 'severity' || k === 'risk_level')" class="inline-flex items-center gap-1.5 font-sans text-[13px]"><span class="h-2 w-2 rounded-full" :style="'background:' + statusColor(detail[k])"></span><span x-text="statusLabel(detail[k])"></span></span>
                                    <a x-show="linkOf(detail[k])" :href="linkOf(detail[k])" :target="/^https?:/.test(linkOf(detail[k]) || '') ? '_blank' : null" rel="noopener"
                                       class="text-[#CA8A04] hover:underline break-all" x-text="fmtD(detail, k)"></a>
                                    <a x-show="refSection(k) && detail[k]" :href="'/app/' + refSection(k) + '?tenant=' + tenant + '&open=' + detail[k]"
                                       class="ml-1.5 text-[#CA8A04] hover:underline text-[11px] font-sans whitespace-nowrap"><span x-text="t('öffnen →')"></span></a>
                                </dd>
                                <button x-show="detail[k] !== null && detail[k] !== undefined && detail[k] !== ''"
                                        @click="copyVal(detail[k])" :title="t('Wert kopieren')"
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
                <div x-show="section === 'notifications'" class="px-6 py-3 border-t border-[#E4E9F0] flex flex-wrap gap-2">
                    <button @click="toggleNotifRead(detail)" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10" x-text="detail && detail.read ? 'Als ungelesen markieren' : t('Als gelesen markieren')"></button>
                    <button @click="dismissNotif(detail)" class="text-xs px-3 py-1.5 border border-[#A6362E]/50 text-[#A6362E] rounded-lg hover:bg-[#A6362E]/10"><span x-text="t('Entfernen')"></span></button>
                </div>
                <div x-show="section === 'tenders'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-3">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]"><span x-text="t('BEWERBUNGEN')"></span></div>
                    <div x-show="!apps.length" class="text-xs text-[#9CA3AF]"><span x-text="t('Noch keine Bewerbungen.')"></span></div>
                    <template x-for="a in apps" :key="a.id">
                        <div class="rounded-lg border px-3 py-2.5" :class="a.status === 'awarded' ? 'border-[#2E7D5B]/50 bg-[#2E7D5B]/5' : 'border-[#E4E9F0]'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium text-[#1A2433]" x-text="appName(a)"></span>
                                <span class="text-[10px] font-semibold" :class="a.status === 'awarded' ? 'text-[#2E7D5B]' : a.status === 'rejected' ? 'text-[#A6362E]' : 'text-[#5B6B7E]'" x-text="appStatus(a.status)"></span>
                            </div>
                            <p x-show="a.proposal" class="mt-1 text-sm text-[#42536A] whitespace-pre-wrap" x-text="a.proposal"></p>
                            <div x-show="a.price" class="mt-1 text-xs text-[#5B6B7E]"><span x-text="a.price"></span> €</div>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <template x-for="t in [['shortlisted',t('Vormerken')],['awarded',t('Vergeben')],['rejected','Ablehnen']]" :key="t[0]">
                                    <button x-show="a.status !== t[0]" @click="setAppStatus(a.id, t[0])" class="text-[11px] font-medium text-[#CA8A04] hover:underline" x-text="(lang==='en' ? (T_EN[t[1]]||t[1]) : t[1])"></button>
                                </template>
                                <button @click="deleteApp(a.id)" class="text-[11px] text-[#A6362E] hover:underline"><span x-text="t('Löschen')"></span></button>
                            </div>
                        </div>
                    </template>
                    <form x-show="detail && detail.status === 'open'" @submit.prevent="submitApp()" class="space-y-2 rounded-lg border border-dashed border-[#D6DEE9] p-3">
                        <select x-model="appForm.expert_profile_id" class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                            <option value=""><span x-text="t('— Experte wählen —')"></span></option>
                            <template x-for="o in fkOptions('expert_profiles')" :key="o[0]">
                                <option :value="o[0]" x-text="o[1]"></option>
                            </template>
                        </select>
                        <textarea x-model="appForm.proposal" rows="2" :placeholder="t('Angebot / Beschreibung…')"
                                  class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30"></textarea>
                        <input x-model="appForm.price" type="number" min="0" step="0.01" :placeholder="t('Preis (€)')"
                               class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                        <div class="flex justify-end">
                            <button type="submit" :disabled="!appForm.expert_profile_id" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] disabled:opacity-40"><span x-text="t('Bewerbung anlegen')"></span></button>
                        </div>
                    </form>
                </div>
                <div x-show="section === 'questions'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-3">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]"><span x-text="t('ANTWORTEN')"></span></div>
                    <div x-show="!answers.length" class="text-xs text-[#9CA3AF]"><span x-text="t('Noch keine Antworten.')"></span></div>
                    <template x-for="ans in answers" :key="ans.id">
                        <div class="rounded-lg border px-3 py-2.5" :class="ans.is_accepted ? 'border-[#2E7D5B]/50 bg-[#2E7D5B]/5' : 'border-[#E4E9F0]'">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium text-[#1A2433]" x-text="ans.author?.name || resolveId('answered_by', ans.answered_by) || '—'"></span>
                                <span x-show="ans.is_accepted" class="text-[10px] font-semibold text-[#2E7D5B]"><span x-text="t('AKZEPTIERT')"></span></span>
                            </div>
                            <p class="mt-1 text-sm text-[#42536A] whitespace-pre-wrap" x-text="ans.body"></p>
                            <div class="mt-2 flex gap-3">
                                <button x-show="!ans.is_accepted" @click="acceptAnswer(ans.id)" class="text-[11px] font-medium text-[#2E7D5B] hover:underline"><span x-text="t('Akzeptieren')"></span></button>
                                <button @click="deleteAnswer(ans.id)" class="text-[11px] text-[#A6362E] hover:underline"><span x-text="t('Löschen')"></span></button>
                            </div>
                        </div>
                    </template>
                    <form @submit.prevent="postAnswer()" class="space-y-2">
                        <textarea x-model="answerText" rows="3" :placeholder="t('Antwort schreiben…')"
                                  class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30"></textarea>
                        <div class="flex justify-end">
                            <button type="submit" :disabled="!answerText.trim()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] disabled:opacity-40"><span x-text="t('Antworten')"></span></button>
                        </div>
                    </form>
                </div>
                <div x-show="section === 'users'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-3">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]"><span x-text="t('ROLLEN')"></span></div>
                    <template x-for="r in allRoles" :key="r.id">
                        <div>
                            <div class="flex items-center gap-2.5 text-sm text-[#1A2433]">
                                <input type="checkbox" :value="r.name" x-model="userRoles" :disabled="!hasPerm('roles.manage')" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30 disabled:opacity-50">
                                <span x-text="roleLabel(r.name)" :title="r.name"></span>
                                <span class="text-[11px] text-[#9CA3AF]" x-text="'(' + (r.permissions || []).length + ' Rechte)'"></span>
                                <button x-show="hasPerm('roles.manage')" @click="openRoleEdit(r)" class="text-[10px] text-[#9CA3AF] hover:text-[#CA8A04] underline underline-offset-2" :title="t('Rechte der Rolle bearbeiten')"><span x-text="t('bearbeiten')"></span></button>
                                <button x-show="hasPerm('roles.manage') && !['holding','administrator'].includes(r.name)" @click="deleteRole(r)" class="text-[10px] text-[#9CA3AF] hover:text-[#A6362E] underline underline-offset-2" :title="t('Rolle löschen')"><span x-text="t('löschen')"></span></button>
                            </div>
                            <div x-show="roleEdit === r.id" class="mt-1.5 ml-6 p-2.5 rounded-lg border border-[#E4E9F0] bg-[#F8FAFC] space-y-1.5">
                                <div class="flex flex-wrap gap-x-3 gap-y-1 max-h-40 overflow-y-auto">
                                    <template x-for="p in allPerms" :key="p">
                                        <label class="flex items-center gap-1.5 text-[11px] font-mono text-[#42536A]">
                                            <input type="checkbox" :value="p" x-model="rolePerms" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                            <span x-text="p"></span>
                                        </label>
                                    </template>
                                </div>
                                <div class="flex justify-end gap-2 pt-1">
                                    <button @click="roleEdit = null" class="text-[11px] text-[#9CA3AF] hover:text-[#1A2433]"><span x-text="t('Abbrechen')"></span></button>
                                    <button @click="saveRolePerms(r)" class="text-[11px] px-2.5 py-1 bg-[#0B0B0F] text-white rounded-md hover:bg-[#1A1A1F]"><span x-text="t('Rechte speichern')"></span></button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="hasPerm('roles.manage')" class="flex items-center gap-2">
                        <input x-model="newRole" :placeholder="t('neue_rolle')" class="flex-1 text-xs px-2 py-1.5 border border-[#D6DEE9] rounded-md bg-white font-mono" @keydown.enter.prevent="createRole()">
                        <button @click="createRole()" :disabled="!newRole.trim()" class="text-[11px] px-2.5 py-1.5 border border-[#D6DEE9] rounded-md hover:border-[#CA8A04] disabled:opacity-40" :title="t('Eigene Rolle anlegen')"><span x-text="t('+ Rolle')"></span></button>
                    </div>
                    <div x-show="!allRoles.length" class="text-xs text-[#9CA3AF]"><span x-text="t('Keine Rollen für diesen Mandanten.')"></span></div>
                    <div class="flex items-center justify-between" x-show="allRoles.length">
                        <button x-show="hasPerm('roles.manage') && detail && me && detail.id !== me.id" @click="removeMember()" class="text-xs px-3 py-1.5 border border-[#A6362E]/40 text-[#A6362E] rounded-lg hover:bg-[#A6362E]/10" :title="t('Mitglied aus diesem Mandanten entfernen')"><span x-text="t('Entfernen')"></span></button>
                        <button x-show="hasPerm('roles.manage')" @click="saveUserRoles()" :disabled="!userRoles.length" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] disabled:opacity-40 ml-auto"><span x-text="t('Rollen speichern')"></span></button>
                    </div>
                    <div x-show="userPerms.length" class="pt-2">
                        <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E] mb-1.5"><span x-text="t('BERECHTIGUNGEN')"></span></div>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="p in userPerms" :key="p">
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-[#F4F6F9] text-[#42536A] font-mono" x-text="p"></span>
                            </template>
                        </div>
                    </div>
                </div>
                <div x-show="section === 'documents'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-3">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]"><span x-text="t('VERSIONEN')"></span></div>
                    <template x-for="v in docVersions" :key="v.id">
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-[#E4E9F0] px-3 py-2">
                            <div class="min-w-0">
                                <span class="text-xs font-semibold text-[#0B0B0F]">v<span x-text="v.version"></span></span>
                                <span class="ml-2 text-xs text-[#42536A] truncate" x-text="v.original_name"></span>
                            </div>
                            <a :href="'/api/v1/documents/' + (detail ? detail.id : '') + '/download/' + v.id" class="text-[11px] font-medium text-[#CA8A04] hover:underline shrink-0"><span x-text="t('Download')"></span></a>
                        </div>
                    </template>
                    <form x-show="writable()" @submit.prevent="uploadVersion()" class="flex items-center gap-2">
                        <input type="file" x-ref="versionFile" class="min-w-0 flex-1 text-xs text-[#42536A]">
                        <button type="submit" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] shrink-0"><span x-text="t('Neue Version')"></span></button>
                    </form>
                </div>
                <div x-show="section === 'graph-entities'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-3">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]"><span x-text="t('VERKNÜPFUNGEN')"></span></div>
                    <template x-for="e in entityEdges" :key="e.relation + e.other">
                        <div>
                            <div class="flex items-center gap-2 rounded-lg border border-[#E4E9F0] px-3 py-2 text-xs hover:border-[#CA8A04]/60 transition">
                                <a :href="'/app/graph-entities?tenant=' + tenant + '&open=' + e.other" class="flex items-center gap-2 flex-1 min-w-0">
                                    <span class="font-mono text-[#9CA3AF] w-4 shrink-0" x-text="e.dir"></span>
                                    <span class="text-[#5B6B7E]" x-text="e.relation"></span>
                                    <span class="ml-auto font-medium text-[#1A2433] truncate" x-text="e.name"></span>
                                </a>
                                <button @click="expandedEdge = expandedEdge === e.relation + e.other ? null : e.relation + e.other"
                                        class="shrink-0 text-[#9CA3AF] hover:text-[#CA8A04] px-1" :title="t('Nachbarn zeigen')">
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
                                <div x-show="edgeNeighbors(e.other).length === 0" class="text-[11px] text-[#9CA3AF] px-3 py-1"><span x-text="t('Keine weiteren Kanten.')"></span></div>
                            </div>
                        </div>
                    </template>
                    <div x-show="entityEdges.length === 0" class="text-xs text-[#9CA3AF]"><span x-text="t('Keine Kanten zu dieser Entität.')"></span></div>
                    <a :href="'/app/graph-edges?tenant=' + tenant + '&new=1&from=' + (detail ? detail.id : '')" class="inline-block text-[11px] px-2.5 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]"><span x-text="t('+ Kante anlegen')"></span></a>
                </div>
                <div x-show="section === 'audits'" class="px-6 py-4 border-t border-[#E4E9F0] space-y-2">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]"><span x-text="t('FESTSTELLUNGEN')"></span> <span class="text-[#9CA3AF] font-normal" x-text="'(' + auditFindings.length + ')'"></span></div>
                    <template x-for="f in auditFindings" :key="f.id">
                        <a :href="'/app/audit-findings?tenant=' + tenant + '&open=' + f.id" class="flex items-center gap-2 rounded-lg border border-[#E4E9F0] px-3 py-2 text-xs hover:border-[#CA8A04]/60 transition">
                            <span class="w-2 h-2 rounded-full shrink-0" :class="statusColor(f.severity || f.status)"></span>
                            <span class="font-medium text-[#1A2433] truncate" x-text="f.title"></span>
                            <span class="ml-auto shrink-0 flex items-center gap-1.5">
                                <span x-show="f.due_at" class="text-[10px]" :class="overdue(f) ? 'text-[#A6362E] font-semibold' : 'text-[#9CA3AF]'" x-text="dueRel(f.due_at)"></span>
                                <span class="text-[#9CA3AF]" x-text="statusLabel(f.status)"></span>
                            </span>
                        </a>
                    </template>
                    <div x-show="auditFindings.length === 0" class="text-xs text-[#9CA3AF]"><span x-text="t('Keine Feststellungen.')"></span></div>
                    <a :href="'/app/audit-findings?tenant=' + tenant + '&new=1&audit=' + (detail ? detail.id : '')" class="inline-block text-[11px] px-2.5 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]"><span x-text="t('+ Feststellung anlegen')"></span></a>
                </div>
                <div x-show="section === 'integrations' && detail && detail.token" class="px-6 py-4 border-t border-[#E4E9F0]">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E] mb-1.5"><span x-text="t('WEBHOOK-URL')"></span></div>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 text-[11px] font-mono text-[#5B6B7E] break-all" x-text="webhookUrl()"></code>
                        <button @click="navigator.clipboard.writeText(webhookUrl()).then(() => toast('URL kopiert'))" class="text-xs px-2.5 py-1 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition shrink-0"><span x-text="t('Kopieren')"></span></button>
                    </div>
                    <p class="text-[11px] text-[#9CA3AF] mt-1">Externe Systeme senden Events per POST {type, subject?, …} an diese URL — jeder Aufruf landet im Event Store.</p>
                </div>
                <div x-show="section === 'connectors' && detail" class="px-6 py-4 border-t border-[#E4E9F0]">
                    <div class="flex items-center gap-2">
                        <button @click="runConnector()" :disabled="rowLoading" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition disabled:opacity-50"><span x-text="t('Jetzt abrufen')"></span></button>
                        <span class="text-[11px] text-[#5B6B7E]" x-text="detail && detail.last_status ? (t('Letzter Status: ') + detail.last_status) : t('Noch nie ausgeführt')"></span>
                    </div>
                </div>
                <div x-show="section === 'events' && detail && detail.meta_data && detail.meta_data.actor" class="px-6 py-4 border-t border-[#E4E9F0]">
                    <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E] mb-1.5"><span x-text="t('AUSLÖSER')"></span></div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#0B0B0F] text-[10px] font-bold text-[#FACC15]" x-text="(detail && detail.meta_data && detail.meta_data.actor && detail.meta_data.actor.name || '?').charAt(0).toUpperCase()"></span>
                        <span class="font-medium text-[#1A2433]" x-text="detail && detail.meta_data && detail.meta_data.actor ? detail.meta_data.actor.name : ''"></span>
                        <a class="text-[#CA8A04] hover:underline" :href="'mailto:' + (detail && detail.meta_data && detail.meta_data.actor && detail.meta_data.actor.email || '')" x-text="detail && detail.meta_data && detail.meta_data.actor ? detail.meta_data.actor.email : ''"></a>
                    </div>
                </div>
                <div x-show="rowEvents.length" class="px-6 py-4 border-t border-[#E4E9F0]">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]"><span x-text="t('VERLAUF')"></span> <span class="text-[#9CA3AF] font-normal" x-text="'(' + rowEvents.length + ')'"></span></div>
                        <a :href="'/app/events?tenant=' + tenant + '&q=' + encodeURIComponent((detail && detail.id) || '')" class="text-[10px] text-[#CA8A04] hover:underline" :title="t('Alle Ereignisse zu diesem Datensatz')"><span x-text="t('Alle →')"></span></a>
                    </div>
                    <template x-for="(e, i) in rowEvents.slice(0, evShown)" :key="i">
                        <a :href="'/app/events?tenant=' + tenant + '&open=' + e.id" class="flex items-center justify-between text-xs py-1 rounded hover:bg-[#FAFBFC] -mx-1 px-1">
                            <span class="text-[#1A2433]"><span class="text-[#CA8A04] font-semibold uppercase text-[10px] tracking-wide" x-text="eventGroup(e.event_type)"></span> <span x-text="eventLabel(e.event_type)"></span><span x-show="e.meta_data && e.meta_data.actor" class="text-[#9CA3AF]" x-text="' · ' + (e.meta_data && e.meta_data.actor ? e.meta_data.actor.name : '')"></span></span>
                            <span class="text-[10px] text-[#9CA3AF] font-mono" :title="e.created_at ? new Date(e.created_at).toLocaleString((this.lang==='en'?'en-GB':'de-DE')) : ''" x-text="ago(e.created_at)"></span>
                        </a>
                    </template>
                    <button x-show="rowEvents.length > evShown" @click="evShown += 20" class="text-[11px] text-[#CA8A04] hover:underline mt-1" x-text="'+ ' + Math.min(20, rowEvents.length - evShown) + ' weitere'"></button>
                </div>
                <div x-show="rowNotifs.length" class="px-6 py-4 border-t border-[#E4E9F0]">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-[10px] font-semibold tracking-widest text-[#5B6B7E]"><span x-text="t('BENACHRICHTIGUNGEN')"></span> <span class="text-[#9CA3AF] font-normal" x-text="'(' + rowNotifs.length + ')'"></span></div>
                        <a :href="'/app/notifications?tenant=' + tenant + '&q=' + encodeURIComponent((detail && detail.id) || '')" class="text-[10px] text-[#CA8A04] hover:underline" :title="t('Alle Benachrichtigungen zu diesem Datensatz')"><span x-text="t('Alle →')"></span></a>
                    </div>
                    <template x-for="(n, i) in rowNotifs.slice(0, 6)" :key="i">
                        <a :href="'/app/notifications?tenant=' + tenant + '&open=' + n.id" class="flex items-center justify-between text-xs py-1 rounded hover:bg-[#FAFBFC] -mx-1 px-1" :class="n.read ? 'opacity-60' : ''">
                            <span class="text-[#1A2433] truncate"><span class="text-[#CA8A04] font-semibold uppercase text-[10px] tracking-wide" x-text="nKind(n.kind)"></span> <span x-text="n.title"></span></span>
                            <span class="text-[10px] text-[#9CA3AF] font-mono ml-2 shrink-0" x-text="ago(n.created_at)"></span>
                        </a>
                    </template>
                </div>
                <div x-show="detail && (detail.created_at || detail.updated_at)" class="px-6 py-2.5 border-t border-[#F0F3F7] text-[10px] text-[#9CA3AF] flex gap-4">
                    <span x-show="detail && detail.created_at"><span x-text="t('Erstellt:')"></span> <span x-text="detail && new Date(detail.created_at).toLocaleString((this.lang==='en'?'en-GB':'de-DE'))"></span> <span class="text-[#CA8A04]" x-text="detail && '(' + relAgo(detail.created_at) + ')'"></span></span>
                    <span x-show="detail && detail.updated_at"><span x-text="t('Geändert:')"></span> <span x-text="detail && new Date(detail.updated_at).toLocaleString((this.lang==='en'?'en-GB':'de-DE'))"></span> <span class="text-[#CA8A04]" x-text="detail && '(' + relAgo(detail.updated_at) + ')'"></span></span>
                </div>
                <div class="px-6 py-4 border-t border-[#E4E9F0] flex justify-end gap-2">
                    <button @click="copyLink()" :title="t('Link kopieren (p)')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" x-text="linkCopied ? 'Kopiert' : 'Link'"></button>
                    <button @click="copyJson()" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" x-text="jsonCopied ? 'Kopiert' : 'JSON'"></button>
                    <button @click="copyText()" :title="t('Alle Felder als lesbarer Text')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" x-text="textCopied ? 'Kopiert' : t('Text')"></button>
                    <a x-show="section === 'events' && eventLink(detail)" :href="eventLink(detail)" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10"><span x-text="t('Datensatz öffnen')"></span></a>
                    <a x-show="section === 'notifications' && notifLink(detail)" :href="notifLink(detail)" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10"><span x-text="t('Zum Datensatz')"></span></a>
                    <button x-show="section === 'notifications' && detail && detail.kind" @click="toggleMute(detail.kind)" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]" x-text="(me && me.muted_kinds || []).includes(detail && detail.kind) ? 'Stummschaltung aufheben' : 'Art stummschalten'"></button>
                    <button x-show="section === 'notifications' && detail && detail.kind && rows && (rows||[]).some(r => r.kind === detail.kind && !r.read)" @click="api('/api/v1/notifications/read-all?kind=' + encodeURIComponent(detail.kind), {method: 'POST'}).then(r => { if (r.ok) { (rows || []).forEach(n => { if (n.kind === detail.kind) n.read = true; }); (dbNotifs || []).forEach(n => { if (n.kind === detail.kind) n.read = true; }); navBadges['notifications'] = unreadNotifs(); toast('Art als gelesen markiert'); } })" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]"><span x-text="t('Art gelesen')"></span></button>
                    <button x-show="section === 'notifications' && detail && detail.kind && rows && (rows||[]).filter(r => r.kind === detail.kind).length > 1" @click="confirmKindDel ? deleteKind(detail.kind) : (confirmKindDel = true, setTimeout(() => confirmKindDel = false, 3000))"
                            class="text-xs px-3 py-1.5 border rounded-lg transition"
                            :class="confirmKindDel ? 'border-[#A6362E] bg-[#A6362E] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#A6362E] hover:text-[#A6362E]'"
                            x-text="confirmKindDel ? 'Wirklich?' : t('Art entfernen')"></button>
                    <button x-show="section === 'notifications' && detail && detail.code && rows && (rows||[]).some(r => r.code === detail.code && !r.read)" @click="api('/api/v1/notifications/read-all?code=' + encodeURIComponent(detail.code), {method: 'POST'}).then(r => { if (r.ok) { (rows || []).forEach(n => { if (n.code === detail.code) n.read = true; }); (dbNotifs || []).forEach(n => { if (n.code === detail.code) n.read = true; }); navBadges['notifications'] = unreadNotifs(); toast('Code als gelesen markiert'); } })" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]"><span x-text="t('Code gelesen')"></span></button>
                    <button x-show="section === 'notifications' && detail && detail.code && rows && (rows||[]).filter(r => r.code === detail.code).length > 1" @click="confirmCodeDel ? deleteCode(detail.code) : (confirmCodeDel = true, setTimeout(() => confirmCodeDel = false, 3000))"
                            class="text-xs px-3 py-1.5 border rounded-lg transition"
                            :class="confirmCodeDel ? 'border-[#A6362E] bg-[#A6362E] text-white' : 'border-[#D6DEE9] text-[#5B6B7E] hover:border-[#A6362E] hover:text-[#A6362E]'"
                            x-text="confirmCodeDel ? 'Wirklich?' : t('Code entfernen')"></button>
                    <button x-show="['documents','data-objects'].includes(section)" @click="downloadDoc(detail)" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10"><span x-text="t('Download')"></span></button>
                    <button x-show="canEdit() && section !== 'documents'" @click="openDuplicate()" :title="t('Duplizieren (d)')" class="text-xs px-3 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04] hover:text-[#CA8A04]"><span x-text="t('Duplizieren')"></span></button>
                    <button x-show="canEdit()" @click="openEdit()" :title="t('Bearbeiten (e)')" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F]"><span x-text="t('Bearbeiten')"></span></button>
                    <button x-show="writable()" @click="confirmDel ? deleteRow(detail) : (confirmDel = true, setTimeout(() => confirmDel = false, 3000))"
                            class="text-xs px-3 py-1.5 border rounded-lg transition"
                            :class="confirmDel ? 'border-[#A6362E] bg-[#A6362E] text-white' : 'border-[#A6362E]/40 text-[#A6362E] hover:bg-[#A6362E]/5'"
                            x-text="confirmDel ? t('Wirklich löschen?') : t('Löschen')"></button>
                </div>
            </div>
        </div>

        {{-- Onboarding tour prompt (first visit) --}}
        <div x-show="tourPrompt" class="fixed bottom-5 right-5 z-[60] w-72 bg-white border border-[#E4E9F0] rounded-xl shadow-2xl p-4" x-cloak>
            <div class="font-semibold text-sm text-[#0B0B0F]" x-text="t('Neu bei ALLOCORE?')"></div>
            <p class="text-xs text-[#5B6B7E] mt-1 leading-relaxed" x-text="t('Eine kurze Tour zeigt dir die wichtigsten Bereiche — Schritt für Schritt.')"></p>
            <div class="mt-3 flex items-center gap-2">
                <button @click="startTour()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-[#FACC15] rounded-lg hover:bg-[#1A1A1F]" x-text="t('Tour starten')"></button>
                <button @click="tourPrompt = false; tourDone()" class="text-xs px-3 py-1.5 text-[#9CA3AF] hover:text-[#5B6B7E]" x-text="t('Später')"></button>
            </div>
        </div>

        {{-- Onboarding tour overlay --}}
        <div x-show="tour" class="fixed inset-0 z-[70]" style="display:none">
            <div class="absolute inset-0" @click="endTour()"></div>
            <div x-show="tourRect" class="absolute rounded-xl ring-2 ring-[#FACC15] pointer-events-none transition-all duration-200"
                 :style="tourRect ? `top:${tourRect.top}px;left:${tourRect.left}px;width:${tourRect.width}px;height:${tourRect.height}px;box-shadow:0 0 0 9999px rgba(11,11,15,.55)` : ''"></div>
            <div x-show="!tourRect" class="absolute inset-0 bg-[#0B0B0F]/55"></div>
            <div class="absolute w-80 max-w-[90vw] bg-white rounded-xl shadow-2xl p-5" :style="tourTip()" role="dialog" :aria-label="t('Tour')">
                <div class="text-[10px] font-mono text-[#9CA3AF]" x-text="t('Schritt') + ' ' + (tourStep + 1) + ' / ' + tourSteps.length"></div>
                <h3 class="font-semibold text-[#0B0B0F] mt-1" x-text="tourSteps[tourStep] ? tourSteps[tourStep].t : ''"></h3>
                <p class="text-xs text-[#5B6B7E] mt-1.5 leading-relaxed" x-text="tourSteps[tourStep] ? tourSteps[tourStep].d : ''"></p>
                <div class="mt-4 flex items-center justify-between">
                    <button @click="endTour()" class="text-[11px] text-[#9CA3AF] hover:text-[#A6362E]" x-text="t('Überspringen')"></button>
                    <div class="flex gap-2">
                        <button x-show="tourStep > 0" @click="tourBack()" class="text-xs px-2.5 py-1.5 border border-[#D6DEE9] text-[#5B6B7E] rounded-lg hover:border-[#CA8A04]" x-text="t('Zurück')"></button>
                        <button @click="tourNext()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-[#FACC15] rounded-lg hover:bg-[#1A1A1F]" x-text="tourStep + 1 >= tourSteps.length ? t('Fertig') : t('Weiter')"></button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Shortcuts help (?) --}}
        <div x-show="kbdHelp" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none" role="dialog" aria-modal="true" :aria-label="t('Tastenkürzel')">
            <div class="absolute inset-0 bg-[#0B0B0F]/50" @click="kbdHelp = false"></div>
            <div class="relative w-full max-w-sm bg-white rounded-xl shadow-2xl p-6 max-h-[80vh] overflow-y-auto">
                <h2 class="font-semibold text-[#0B0B0F] mb-4"><span x-text="t('Tastenkürzel')"></span></h2>
                <dl class="space-y-2 text-sm">
                    <template x-for="k in [['Ctrl/⌘ + K', t('Befehlspalette öffnen (↑/↓ wählen, Enter öffnen)')], ['/', 'Suche fokussieren'], ['n', 'Neuen Eintrag anlegen'], ['e', 'Eintrag bearbeiten (Drawer)'], ['d', 'Eintrag duplizieren (Drawer)'], ['f', 'Sektion (ent)pinnen'], ['a', 'Alle Zeilen (ab)wählen'], ['p', 'Deep-Link kopieren (Drawer)'], ['o', 'Erste gefilterte Zeile öffnen'], ['l', 'Alle Zeilen laden'], ['r', 'Liste / Datensatz neu laden'], ['i', 'CSV-Import öffnen'], ['c', 'Spalten-Picker'], ['v', 'Ansichten-Picker'], ['x', t('Filter zurücksetzen')], ['u', 'Überfällig-Filter'], ['s', '≤7-Tage-Filter'], ['b', 'Heute-Filter'], ['m', 'Mir zugewiesen'], ['q', 'Ohne Verantwortlichen'], ['g', 'Gruppierung wechseln (Status, Zeitraum, FK-Spalten)'], ['.', 'Zum Dashboard'], ['1–9', 'n-te Zeile öffnen'], ['← / →', 'Vorheriger / nächster Eintrag (Drawer)'], ['Pos1 / Ende', 'Erster / letzter Eintrag (Drawer)'], ['w', 'Drawer breit/schmal'], ['k', 'Kompakte Zeilen'], ['j', 'JSON kopieren (Drawer)'], ['z', 'Rückgängig (Löschen/Status)'], ['y', 'Nächster Mandant'], ['Ctrl + Enter', 'Formular absenden'], ['B', 'Benachrichtigungen'], ['t', 'Dunkel/Hell umschalten'], ['Esc', t('Schließen')], ['? / h', 'Diese Übersicht']]" :key="k[0]">
                        <div class="flex justify-between items-center">
                            <dt class="text-[#5B6B7E]"><kbd class="px-1.5 py-0.5 bg-[#F0F3F7] border border-[#E4E9F0] rounded text-xs font-mono" x-text="k[0]"></kbd></dt>
                            <dd class="text-[#1A2433]" x-text="(lang==='en' ? (T_EN[k[1]]||k[1]) : k[1])"></dd>
                        </div>
                    </template>
                </dl>
            </div>
        </div>

        {{-- Command palette (Ctrl+K) --}}
        <div x-show="palette" class="fixed inset-0 z-50" style="display:none" role="dialog" aria-modal="true" :aria-label="t('Befehlspalette')">
            <div class="absolute inset-0 bg-[#0B0B0F]/50" @click="palette = false"></div>
            <div class="relative mx-auto mt-24 w-full max-w-md bg-white rounded-xl shadow-2xl overflow-hidden">
                <input x-ref="paletteInput" x-model="paletteQ" x-init="$watch('palette', v => v && $nextTick(() => $refs.paletteInput.focus())); $watch('paletteQ', () => palIdx = 0)"
                       :placeholder="t('Modul oder Aktion suchen…')" class="w-full px-5 py-4 text-sm border-0 border-b border-[#E4E9F0] focus:ring-0 focus:border-[#CA8A04]"
                       @keydown.arrow-down.prevent="palIdx = Math.min(palIdx + 1, paletteItems().length - 1)"
                       @keydown.arrow-up.prevent="palIdx = Math.max(palIdx - 1, 0)"
                       @keydown.enter.prevent="paletteGo()">
                <div class="max-h-72 overflow-y-auto py-1">
                    <template x-for="(it, pi) in paletteItems()" :key="it.key || it.action">
                        <a :href="'/app/' + it.key + (tenant ? '?tenant='+tenant : '')"
                           @click="it.action ? (function(){ $event.preventDefault(); paletteRun(it); })() : null"
                           :class="pi === palIdx ? 'bg-[#CA8A04]/10' : ''"
                           class="block px-5 py-2.5 text-sm text-[#1A2433] hover:bg-[#FAFBFC] transition">
                            <span class="inline-block w-4 text-center text-[11px] text-[#9CA3AF] mr-2" x-text="it.icon || icons[it.key] || '·'"></span><span x-text="it.label"></span>
                            <span x-show="it.key === section" class="ml-1.5 text-[9px] text-[#CA8A04] font-semibold">●</span>
                            <span class="ml-2 text-[10px] text-[#9CA3AF] tracking-wide" x-text="it.group"></span>
                        </a>
                    </template>
                    <div x-show="paletteItems().length === 0" class="px-5 py-6 text-center text-xs text-[#9CA3AF]"><span x-text="t('Kein Modul gefunden.')"></span></div>
                </div>
            </div>
        </div>

        {{-- Create modal --}}
        <div x-show="showCreate" class="fixed inset-0 z-40 flex items-center justify-center" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="closeCreate()"></div>
            <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl" role="dialog" aria-modal="true">
                <div class="px-6 py-4 border-b border-[#E4E9F0]">
                    <h2 class="font-semibold text-[#0B0B0F]" x-text="(editing ? t('Bearbeiten: ') : dupMode ? t('Duplizieren: ') : t('Neu: ')) + title()"></h2>
                </div>
                <form @submit.prevent="submitCreate" @input="formDirty = true" @keydown.ctrl.enter.prevent="submitCreate" class="p-6 space-y-4" id="createForm">
                    <template x-for="f in createFields()" :key="f.key">
                        <div>
                            <label class="block text-[13px] font-medium text-[#42536A] mb-1">
                                <span x-text="label(f.key)"></span><span x-show="f.req" class="text-[#A6362E]"> *</span>
                            </label>
                            <div x-show="f.type === 'fk'">
                                <input x-show="fkOptions(f.table).length > 10" x-model="fkQ[f.key]" :placeholder="t('Filtern…')"
                                       class="w-full mb-1 rounded-lg border-[#E4E9F0] text-xs focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                                <select x-model="form[f.key]"
                                        class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                                    <option value=""><span x-text="t('— wählen —')"></span></option>
                                    <template x-for="o in fkOptions(f.table).filter(o => !fkQ[f.key] || String(o[1]).toLowerCase().includes(String(fkQ[f.key]).toLowerCase()))" :key="o[0]">
                                        <option :value="o[0]" x-text="o[1]"></option>
                                    </template>
                                </select>
                            </div>
                            <div x-show="f.type === 'enum'">
                                <select x-model="form[f.key]"
                                        class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                                    <option value=""><span x-text="t('— wählen —')"></span></option>
                                    <template x-for="o in f.opts" :key="o">
                                        <option :value="o" x-text="typeLabel(o)"></option>
                                    </template>
                                </select>
                            </div>
                            <label x-show="f.type === 'checkbox'" class="inline-flex items-center gap-2 text-sm text-[#42536A]">
                                <input type="checkbox" x-model="form[f.key]" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                <span x-text="label(f.key)"></span>
                            </label>
                            <textarea x-show="f.type === 'textarea'" x-model="form[f.key]" rows="3"
                                      class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30"></textarea>
                            <div x-show="f.type !== 'fk' && f.type !== 'enum' && f.type !== 'checkbox' && f.type !== 'textarea'" class="relative">
                                <input x-model="form[f.key]" :type="f.type"
                                       class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                                <button x-show="f.type === 'date' || f.type === 'datetime-local'" type="button"
                                        @click="form[f.key] = f.type === 'date' ? new Date().toISOString().slice(0,10) : new Date(Date.now() - new Date().getTimezoneOffset()*60000).toISOString().slice(0,16)"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] font-semibold text-[#CA8A04] hover:underline"><span x-text="t('Heute')"></span></button>
                            </div>
                        </div>
                    </template>
                    <div x-show="section === 'users' && !editing">
                        <label class="block text-[13px] font-medium text-[#42536A] mb-1"><span x-text="t('Rollen')"></span></label>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 max-h-32 overflow-y-auto">
                            <template x-for="r in allRoles" :key="r.id">
                                <label class="flex items-center gap-1.5 text-[11px] text-[#42536A]">
                                    <input type="checkbox" :value="r.name" x-model="form.roles" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                    <span x-text="roleLabel(r.name)"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                    <div x-show="section === 'tokens' && !editing">
                        <label class="block text-[13px] font-medium text-[#42536A] mb-1"><span x-text="t('Rechte (Abilities)')"></span></label>
                        <div class="flex flex-wrap gap-x-3 gap-y-1 max-h-32 overflow-y-auto">
                            <template x-for="a in tokenAbilities" :key="a">
                                <label class="flex items-center gap-1.5 text-[11px] text-[#42536A]">
                                    <input type="checkbox" :value="a" x-model="form.abilities" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/30">
                                    <span x-text="a"></span>
                                </label>
                            </template>
                        </div>
                        <p class="text-[11px] text-[#9CA3AF] mt-1"><span x-text="t('leer = * (alle Rechte)')"></span></p>
                    </div>
                    <div x-show="['documents','data-objects'].includes(section) && !editing">
                        <label class="block text-[13px] font-medium text-[#42536A] mb-1"><span x-text="t('Datei')"></span></label>
                        <input type="file" x-ref="fileInput" class="w-full text-sm">
                    </div>
                    <div x-show="formError" class="text-xs text-[#A6362E]" x-text="formError"></div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="closeCreate()" class="text-sm px-4 py-2 text-[#5B6B7E]" x-text="t('Abbrechen')"></button>
                        <button type="button" x-show="!editing && !['documents','data-objects'].includes(section)" @click="submitCreate(true)" :disabled="createFields().some(f => f.req && !String(form[f.key] || '').trim())"
                                class="text-sm px-4 py-2 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10 disabled:opacity-40 disabled:cursor-not-allowed" :title="t('Speichern und nächsten Eintrag anlegen')" x-text="t('Speichern & neu')"></button>
                        <button type="submit" :disabled="createFields().some(f => f.req && !String(form[f.key] || '').trim())"
                                class="text-sm px-4 py-2 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] disabled:opacity-40 disabled:cursor-not-allowed" :title="t('Speichern (Strg+↵)')" x-text="t('Speichern')"></button>
                    </div>
                </form>
            </div>
        </div>

        {{-- CSV import modal --}}
        <div x-show="showImport" class="fixed inset-0 z-40 flex items-center justify-center" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="showImport = false"></div>
            <div class="relative w-full max-w-lg bg-white rounded-xl shadow-xl" role="dialog" aria-modal="true" :aria-label="'CSV Import: ' + title()">
                <div class="px-6 py-4 border-b border-[#E4E9F0]">
                    <h2 class="font-semibold text-[#0B0B0F]">CSV Import: <span x-text="title()"></span></h2>
                </div>
                <div class="p-6 space-y-3">
                    <p class="text-xs text-[#5B6B7E]"><span x-text="t('Erste Zeile = Spaltennamen (')"></span><span x-text="createFields().map(f => f.key).join(', ')"></span><span x-text="t('). Trennzeichen ; oder ,')"></span></p>
                    <textarea x-model="importText" rows="8" class="w-full rounded-lg border-[#D6DEE9] text-xs font-mono focus:border-[#CA8A04] focus:ring-[#CA8A04]/30" placeholder="title;status&#10;Beispiel;open"></textarea>
                    <div class="flex justify-end"><button type="button" @click="importText = importTemplate()" class="text-xs text-[#CA8A04] hover:underline"><span x-text="t('Vorlage mit Beispielzeile einfügen')"></span></button></div>
                    <div x-show="importResult" class="text-xs" :class="importErr ? 'text-[#A6362E]' : 'text-[#2E7D4F]'" x-text="importResult"></div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="showImport = false" class="text-sm px-4 py-2 text-[#5B6B7E]"><span x-text="t('Schließen')"></span></button>
                        <button type="button" @click="importCsv()" :disabled="importing" class="text-sm px-4 py-2 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] disabled:opacity-50" x-text="importing ? 'Importiere… ' + importProgress : 'Importieren'"></button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Toasts --}}
    <div class="fixed bottom-4 right-4 z-50 space-y-2" aria-live="polite" role="status">
        <template x-for="t in toasts" :key="t.id">
            <div :class="t.type === 'error' ? 'border-[#A6362E]' : 'border-[#FACC15]'" :role="t.type === 'error' ? 'alert' : null" class="bg-[#0B0B0F] text-white text-xs px-4 py-3 rounded-lg shadow-lg border-l-2 max-w-xs flex items-center gap-3">
                <span x-text="t.msg" class="flex-1"></span>
                <button x-show="t.action" @click="t.action.fn(); toasts = toasts.filter(x => x.id !== t.id)"
                        class="text-[#FACC15] font-semibold hover:underline shrink-0" x-text="t.action ? t.action.label : ''"></button>
                <button @click="toasts = toasts.filter(x => x.id !== t.id)" :aria-label="t('Schließen')"
                        class="text-white/40 hover:text-white shrink-0 leading-none">×</button>
            </div>
        </template>
    </div>
{{-- Passwort ändern --}}
<div x-show="pwOpen" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
    <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="pwOpen = false"></div>
    <div class="relative w-full max-w-sm bg-white rounded-xl shadow-xl" role="dialog" aria-modal="true" :aria-label="t('Passwort ändern')">
        <div class="px-6 py-4 border-b border-[#E4E9F0]"><h2 class="font-semibold text-[#0B0B0F]"><span x-text="t('Profil bearbeiten')"></span></h2></div>
        <div class="p-6 space-y-3" @keydown.enter="submitPassword()">
            <div x-show="pwErr" class="text-xs text-[#A6362E] bg-[#A6362E]/10 rounded-lg px-3 py-2" x-text="pwErr"></div>
            <input type="text" x-model="pwForm.name" :placeholder="t('Name')" class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
            <input type="email" x-model="pwForm.email" :placeholder="t('E-Mail')" class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
            <input type="password" x-model="pwForm.current" :placeholder="t('Aktuelles Passwort (nur für Passwort-Änderung)')" autocomplete="current-password" class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
            <input type="password" x-model="pwForm.next" :placeholder="t('Neues Passwort (min. 12 Zeichen, Groß-/Kleinbuchstabe, Zahl)')" autocomplete="new-password" class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
            <input type="password" x-model="pwForm.confirm" :placeholder="t('Neues Passwort wiederholen')" autocomplete="new-password" class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
            <p class="text-[11px] text-[#5B6B7E]" x-show="me && me.password_changed_at" x-text="t('Zuletzt geändert: ') + new Date(me.password_changed_at).toLocaleString((this.lang==='en'?'en-GB':'de-DE'))"></p>
            <p class="text-[11px] text-[#5B6B7E]" x-show="me && me.created_at" x-text="t('Mitglied seit: ') + new Date(me.created_at).toLocaleDateString((this.lang==='en'?'en-GB':'de-DE'))"></p>
            <p class="text-[11px] text-[#5B6B7E]" x-show="me && me.last_login_ip"><span x-text="t('Letzte Anmeldung von: ') + me.last_login_ip"></span></p>
            <p class="text-[11px] text-[#5B6B7E]" x-show="me && me.tokens_count !== undefined"><span x-text="(me.tokens_count || 0) + t(' aktive API-Token')"></span> · <a :href="'/app/tokens?tenant=' + tenant" class="text-[#CA8A04] hover:underline"><span x-text="t('verwalten →')"></span></a></p>
            <p class="text-[11px] text-[#A6362E]" x-show="me && me.email_verified === false"><span x-text="t('E-Mail noch nicht verifiziert — wird nach Änderung erneut ausstehend.')"></span></p>
            <p class="text-[11px] text-[#5B6B7E]"><span x-text="t('Nach der Änderung werden alle API-Token widerrufen — die Seite lädt neu.')"></span></p>
            <template x-if="me && me.tenants && me.tenants.length">
                <div class="pt-2 border-t border-[#E4E9F0]">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#8A97A6] mb-1.5"><span x-text="t('Mitgliedschaften')"></span></p>
                    <div class="space-y-1 max-h-32 overflow-y-auto">
                        <template x-for="t in me.tenants" :key="t.id">
                            <div class="flex items-center gap-2 text-xs">
                                <button @click="t.id !== tenant && (pwOpen = false, tenant = t.id, loadSection(), loadNavBadges(), toast('Mandant: ' + (t.name || t.id)))" :class="t.id === tenant ? 'font-medium text-[#0B0B0F] cursor-default' : 'font-medium text-[#CA8A04] hover:underline'" x-text="t.name || t.id"></button>
                                <span x-show="t.id === tenant" class="text-[9px] font-semibold uppercase tracking-wide text-[#8A97A6]"><span x-text="t('aktiv')"></span></span>
                                <span class="flex-1"></span>
                                <template x-for="r in (t.roles || [])" :key="r">
                                    <span class="text-[9px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded bg-[#FACC15]/20 text-[#854D0E]" x-text="roleLabel(r)"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
            <template x-if="me && me.muted_kinds && me.muted_kinds.length">
                <div class="pt-2 border-t border-[#E4E9F0]">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#8A97A6] mb-1.5"><span x-text="t('Stummgeschaltete Arten')"></span></p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="k in me.muted_kinds" :key="k">
                            <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-[#0B0B0F]/5 text-[#5B6B7E]">
                                <span x-text="nKind(k)"></span>
                                <button @click="toggleMute(k)" class="text-[#8A97A6] hover:text-[#A6362E]" :title="t('Stummschaltung für ') + k + t(' aufheben')">×</button>
                            </span>
                        </template>
                    </div>
                </div>
            </template>
            <template x-if="loginHistory.length">
                <div class="pt-2 border-t border-[#E4E9F0]">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#8A97A6] mb-1.5"><span x-text="t('Anmelde-Verlauf')"></span></p>
                    <div class="space-y-1">
                        <template x-for="e in loginHistory" :key="e.id">
                            <p class="text-[11px] text-[#5B6B7E]"><span x-text="new Date(e.created_at).toLocaleString((this.lang==='en'?'en-GB':'de-DE'))"></span> · <span x-text="e.event_properties?.payload?.ip || '—'"></span></p>
                        </template>
                    </div>
                </div>
            </template>
        </div>
        <div class="px-6 py-3 border-t border-[#E4E9F0] flex items-center gap-2">
            <button @click="api('/api/v1/notifications/test', {method: 'POST'}).then(r => r.ok && toast('Testbenachrichtigung gesendet'))" :title="t('Testet die Benachrichtigungsglocke')" class="px-3 py-1.5 text-xs rounded-lg border border-[#D6DEE9] text-[#5B6B7E] hover:border-[#CA8A04] hover:text-[#CA8A04]"><span x-text="t('Test-Benachrichtigung')"></span></button>
            <button @click="deleteAccount()" class="px-3 py-1.5 text-xs rounded-lg border border-[#A6362E]/40 text-[#A6362E] hover:bg-[#A6362E]/10"><span x-text="t('Konto löschen')"></span></button>
            <span class="flex-1"></span>
            <button @click="pwOpen = false" class="px-3 py-1.5 text-sm rounded-lg border border-[#D6DEE9] text-[#5B6B7E]"><span x-text="t('Abbrechen')"></span></button>
            <button @click="submitPassword()" :disabled="(!pwForm.current && !(pwForm.name && pwForm.email)) || (pwForm.next && pwForm.next !== pwForm.confirm)" class="px-4 py-1.5 text-sm rounded-lg bg-[#FACC15] text-black font-semibold disabled:opacity-50"><span x-text="t('Ändern')"></span></button>
        </div>
    </div>
</div>

{{-- Tenant create/rename modal --}}
<div x-show="tenantModal" class="fixed inset-0 z-50 flex items-center justify-center" style="display:none">
    <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="tenantModal = false"></div>
    <div class="relative w-full max-w-sm bg-white rounded-xl shadow-xl" role="dialog" aria-modal="true" :aria-label="t('Mandant')">
        <div class="px-6 py-4 border-b border-[#E4E9F0]"><h2 class="font-semibold text-[#0B0B0F]" x-text="tenantMode === 'rename' ? t('Mandant umbenennen') : t('Neuer Mandant')"></h2></div>
        <div class="p-6 space-y-3" @keydown.enter="submitTenant()">
            <input type="text" x-model="tenantName" :placeholder="t('Name des Mandanten')" class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
        </div>
        <div class="px-6 py-3 border-t border-[#E4E9F0] flex items-center gap-2 justify-end">
            <button @click="tenantModal = false" class="px-3 py-1.5 text-sm rounded-lg border border-[#D6DEE9] text-[#5B6B7E]" x-text="t('Abbrechen')"></button>
            <button @click="submitTenant()" :disabled="!tenantName.trim()" class="px-4 py-1.5 text-sm rounded-lg bg-[#FACC15] text-black font-semibold disabled:opacity-50" x-text="tenantMode === 'rename' ? t('Umbenennen') : t('Anlegen')"></button>
        </div>
    </div>
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
            {key:'audits',label:'Audits',ep:'/api/v1/audits'},
            {key:'audit-findings',label:'Feststellungen',ep:'/api/v1/audit-findings'},
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
            {key:'users',label:'Team',ep:'/api/v1/users'},
            {key:'notifications',label:'Benachrichtigungen',ep:'/api/v1/notifications'},
        ]},
        {label:'PLATTFORM', items:[
            {key:'events',label:'Events',ep:'/api/v1/events'},
            {key:'tooldata',label:'Tool-Daten',ep:'/api/v1/events'},
            {key:'data-objects',label:'Data Lake',ep:'/api/v1/data-objects'},
            {key:'ai-analyses',label:'KI-Analysen',ep:'/api/v1/ai-analyses'},
            {key:'graph-entities',label:'Graphen · Entitäten',ep:'/api/v1/graph-entities'},
            {key:'graph-edges',label:'Graphen · Kanten',ep:'/api/v1/graph-edges'},
            {key:'integrations',label:'Webhook-Quellen',ep:'/api/v1/integrations'},
            {key:'connectors',label:'Konnektoren',ep:'/api/v1/connectors'},
            {key:'tokens',label:'API-Token',ep:'/api/v1/tokens'},
        ]},
    ];
    const ICONS = {dashboard:'◈',companies:'▣',persons:'◉',documents:'▤',tasks:'☑',instructions:'ⓘ',inspections:'✓',deadlines:'◷','risk-assessments':'⚠','operating-instructions':'✎','expert-profiles':'◎',questions:'?',tenders:'☰',strategies:'⌘',projects:'◇',measures:'→',portfolios:'▲',investments:'€',participations:'◆',machines:'⚙','production-orders':'▶','leave-requests':'◔','financial-reports':'₣',events:'≋','data-objects':'▦','ai-analyses':'✦','graph-entities':'●','graph-edges':'↔',executive:'∑',users:'☺',audits:'§','audit-findings':'∴',notifications:'✉',tokens:'⚿',integrations:'⇄',connectors:'⤓',tooldata:'◧'};
    const FKMAP = {person_id:'persons',company_id:'companies',machine_id:'machines',project_id:'projects',strategy_id:'strategies',portfolio_id:'portfolios',tender_id:'tenders',question_id:'questions',document_id:'documents',audit_id:'audits',expert_profile_id:'expert_profiles',responsible_id:'users',assignee_id:'users',owner_id:'users',asked_by:'users',approved_by:'users',answered_by:'users',created_by:'users',uploaded_by:'users',generated_by:'users',current_version_id:'documents',assigned_to:'persons',from_entity_id:'graph_entities',to_entity_id:'graph_entities',subject_id:'graph_entities'};
    const NOTIF_KIND = {unterweisung:'Unterweisung',pruefung:'Prüfung',frist:'Frist',feststellung:'Feststellung',audit:'Audit',massnahme:'Maßnahme',aufgabe:'Aufgabe',gefaehrdungsbeurteilung:'Gefährdungsbeurteilung',projekt:'Projekt',auftrag:'Produktionsauftrag',ausschreibung:'Ausschreibung',antwort:'Antwort',frage:'Frage',urlaub:'Urlaubsantrag',rollen:'Rollen',unterweisung_wiederholung:'Unterweisung (Wiederholung)',hinweis:'Kritischer Hinweis',passwort_geaendert:'Passwort geändert',anmeldung:'Anmeldung',anmeldeversuche:'Fehlgeschlagene Anmeldung',ki_coach:'KI-Coach'};
    const STATUS_DE = {open:'Offen',pending:'Ausstehend',in_progress:'Läuft',active:'Aktiv',done:'Fertig',completed:'Abgeschlossen',approved:'Genehmigt',archived:'Archiviert',draft:'Entwurf',maintenance:'Wartung',retired:'Ausgemustert',awarded:'Vergeben',info:'Info',warning:'Warnung',critical:'Kritisch',high:'Hoch',medium:'Mittel',low:'Niedrig',scheduled:'Geplant',cancelled:'Abgesagt',rejected:'Abgelehnt',answered:'Beantwortet',closed:'Geschlossen',submitted:'Eingereicht',shortlisted:'Vorauswahl',queued:'Warteschlange',running:'Läuft',mitigated:'Gemindert',accepted:'Akzeptiert',planned:'Geplant',on_hold:'Pausiert',inactive:'Inaktiv',todo:'Offen',overdue:'Überfällig',sent:'Gesendet',paid:'Bezahlt',unpaid:'Unbezahlt',expired:'Abgelaufen',suspended:'Gesperrt',review:'In Prüfung',assigned:'Zugewiesen',requested:'Angefragt',confirmed:'Bestätigt',declined:'Abgelehnt',exited:'Ausgestiegen',candidate:'Kandidat',resolved:'Gelöst',internal:'Intern',external:'Extern',vacation:'Urlaub',sick:'Krank',other:'Sonstiges'};
    const HIDE = new Set(['id','tenant_id','created_at','updated_at','deleted_at','pivot','data','roles','permissions','email_verified_at','meta_data','event_class','_etype']);
    const KPI = [
        {key:'companies',label:'Unternehmen',to:'companies'},{key:'persons',label:'Personen',to:'persons'},
        {key:'documents',label:'Dokumente',to:'documents'},{key:'tasks_open',label:'Offene Aufgaben',to:'tasks'},
        {key:'instructions',label:'Unterweisungen',to:'instructions'},{key:'compliance_rate',label:'Compliance %',to:'instructions'},
        {key:'deadlines_open',label:'Offene Fristen',to:'deadlines'},{key:'risk_high',label:'Hohe Risiken',to:'risk-assessments'},
        {key:'tenders_open',label:'Offene Ausschreibungen',to:'tenders'},{key:'fin_revenue',label:'Umsatz (Monat)',to:'financial-reports',money:true},{key:'fin_ebitda',label:'EBITDA (Monat)',to:'financial-reports',money:true},{key:'fin_liquidity',label:'Liquidität (Monat)',to:'financial-reports',money:true},{key:'expert_profiles',label:'Experten',to:'expert-profiles'},
        {key:'questions',label:'Fragen',to:'questions'},{key:'inspections',label:'Prüfungen',to:'inspections'},
        {key:'team_members',label:'Team',to:'users'},
        {key:'audits_planned',label:'Geplante Audits',to:'audits'},{key:'audits_in_progress',label:'Laufende Audits',to:'audits'},
        {key:'audit_findings_open',label:'Offene Feststellungen',to:'audit-findings'},{key:'audit_findings_overdue',label:'Überfällige Feststellungen',to:'audit-findings'},
        {key:'production_orders_open',label:'Laufende Aufträge',to:'production-orders'},{key:'projects_open',label:'Offene Projekte',to:'projects'},
        {key:'machines_active',label:'Maschinen aktiv',to:'machines'},{key:'leave_requests_pending',label:'Urlaubsanträge offen',to:'leave-requests'},{key:'questions_open',label:'Offene Fragen',to:'questions'},{key:'ai_analyses',label:'KI-Analysen',to:'ai-analyses'},{key:'strategies_active',label:'Aktive Strategien',to:'strategies'},{key:'graph_entities',label:'Wissensgraph',to:'graph-entities'},{key:'tender_applications',label:'Bewerbungen',to:'tenders'},{key:'instructions_pending',label:'Unterweisungen offen',to:'instructions'},{key:'answers',label:'Antworten',to:'questions'},
        {key:'investments_active',label:'Investitionen aktiv',to:'investments'},{key:'participations_active',label:'Beteiligungen aktiv',to:'participations'},
        {key:'ext_revenue',label:'Umsatz (Tools)',to:'events',money:true},{key:'ext_revenue_paid',label:'Bezahlt (Tools)',to:'events',money:true},
        {key:'ext_costs',label:'Kosten (Tools)',to:'events',money:true},{key:'ext_cash_in',label:'Cash-In (Tools)',to:'events',money:true},
        {key:'ext_cash_out',label:'Cash-Out (Tools)',to:'events',money:true},{key:'ext_costs_marketing',label:'Marketingkosten (Tools)',to:'events',money:true},
        {key:'ext_leads',label:'Leads (Tools)',to:'events'},{key:'ext_mql',label:'MQL (Tools)',to:'events'},
        {key:'ext_new_customers',label:'Neue Kunden (Tools)',to:'events'},{key:'ext_orders',label:'Aufträge (Tools)',to:'events'},
        {key:'ext_orders_done',label:'Fertige Aufträge (Tools)',to:'events'},{key:'ext_orders_on_time',label:'Pünktliche Aufträge (Tools)',to:'events'},
        {key:'ext_complaints',label:'Reklamationen (Tools)',to:'events'},{key:'ext_billable_hours',label:'Fakturierbare Std. (Tools)',to:'events'},
        {key:'ext_lead_time_days',label:'Durchlaufzeit Σ (Tools)',to:'events'},{key:'ext_pipeline_value',label:'Pipeline (Tools)',to:'events',money:true},
        {key:'ext_web_visitors',label:'Websitebesucher (Tools)',to:'events'},
    ];
    const GROUP_EN = {START:'START',COMPLIANCE:'COMPLIANCE',FAVORITEN:'FAVORITES',ZULETZT:'RECENT',STAMMDATEN:'MASTER DATA',NETZWERK:'NETWORK',ENTWICKLUNG:'DEVELOPMENT',KAPITAL:'CAPITAL',PRODUKTION:'PRODUCTION',ORGANISATION:'ORGANIZATION',PLATTFORM:'PLATFORM'};
    const ITEM_EN = {dashboard:'Dashboard',executive:'Executive',companies:'Companies',persons:'Persons',documents:'Documents',tasks:'Tasks',instructions:'Instructions',inspections:'Inspections',deadlines:'Deadlines','risk-assessments':'Risk Assessments','operating-instructions':'Operating Instructions',audits:'Audits','audit-findings':'Findings','expert-profiles':'Experts',questions:'Questions',tenders:'Tenders',strategies:'Strategies',projects:'Projects',measures:'Measures',portfolios:'Portfolios',investments:'Investments',participations:'Participations',machines:'Machines','production-orders':'Orders','leave-requests':'Leave Requests','financial-reports':'Finance',users:'Team',notifications:'Notifications',events:'Events','data-objects':'Data Lake','ai-analyses':'AI Analyses','graph-entities':'Graph · Entities','graph-edges':'Graph · Edges','answers':'Answers',integrations:'Webhook Sources',connectors:'Connectors',tokens:'API Tokens',tooldata:'Tool Data'};
    const STATUS_EN = {open:'Open',pending:'Pending',in_progress:'Running',active:'Active',done:'Done',completed:'Completed',approved:'Approved',archived:'Archived',draft:'Draft',maintenance:'Maintenance',retired:'Retired',awarded:'Awarded',info:'Info',warning:'Warning',critical:'Critical',high:'High',medium:'Medium',low:'Low',scheduled:'Scheduled',cancelled:'Cancelled',rejected:'Rejected',answered:'Answered',closed:'Closed',submitted:'Submitted',shortlisted:'Shortlisted',queued:'Queued',running:'Running',mitigated:'Mitigated',accepted:'Accepted',planned:'Planned',on_hold:'On hold',inactive:'Inactive',todo:'Open',overdue:'Overdue',sent:'Sent',paid:'Paid',unpaid:'Unpaid',expired:'Expired',suspended:'Suspended',review:'In review',assigned:'Assigned',requested:'Requested',confirmed:'Confirmed',declined:'Declined',exited:'Exited',candidate:'Candidate',resolved:'Resolved',internal:'Internal',external:'External',vacation:'Vacation',sick:'Sick',other:'Other'};
    const NOTIF_EN = {unterweisung:'Instruction',pruefung:'Inspection',frist:'Deadline',feststellung:'Finding',audit:'Audit',massnahme:'Measure',aufgabe:'Task',gefaehrdungsbeurteilung:'Risk Assessment',projekt:'Project',auftrag:'Production Order',ausschreibung:'Tender',antwort:'Answer',frage:'Question',urlaub:'Leave Request',rollen:'Roles',unterweisung_wiederholung:'Instruction (Renewal)',hinweis:'Critical Notice',passwort_geaendert:'Password Changed',anmeldung:'Login',anmeldeversuche:'Failed Login',ki_coach:'AI Coach'};
    const KPI_EN = {companies:'Companies',persons:'Persons',documents:'Documents',tasks_open:'Open Tasks',instructions:'Instructions',compliance_rate:'Compliance %',deadlines_open:'Open Deadlines',risk_high:'High Risks',tenders_open:'Open Tenders',fin_revenue:'Revenue (Month)',fin_ebitda:'EBITDA (Month)',fin_liquidity:'Liquidity (Month)',expert_profiles:'Experts',questions:'Questions',inspections:'Inspections',team_members:'Team',audits_planned:'Planned Audits',audits_in_progress:'Running Audits',audit_findings_open:'Open Findings',audit_findings_overdue:'Overdue Findings',production_orders_open:'Running Orders',projects_open:'Open Projects',machines_active:'Machines Active',leave_requests_pending:'Leave Requests Open',questions_open:'Open Questions',ai_analyses:'AI Analyses',strategies_active:'Active Strategies',graph_entities:'Knowledge Graph',tender_applications:'Applications',instructions_pending:'Instructions Open',answers:'Answers',investments_active:'Investments Active',participations_active:'Participations Active',ext_revenue:'Revenue (Tools)',ext_revenue_paid:'Paid (Tools)',ext_costs:'Costs (Tools)',ext_cash_in:'Cash-In (Tools)',ext_cash_out:'Cash-Out (Tools)',ext_costs_marketing:'Marketing Costs (Tools)',ext_leads:'Leads (Tools)',ext_mql:'MQL (Tools)',ext_new_customers:'New Customers (Tools)',ext_orders:'Orders (Tools)',ext_orders_done:'Orders Done (Tools)',ext_orders_on_time:'On-Time Orders (Tools)',ext_complaints:'Complaints (Tools)',ext_billable_hours:'Billable Hrs (Tools)',ext_lead_time_days:'Lead Time Σ (Tools)',ext_pipeline_value:'Pipeline (Tools)',ext_web_visitors:'Web Visitors (Tools)'};
const T_EN2 = {
 'AKZEPTIERT':'ACCEPTED','ANTWORTEN':'ANSWERS','AUSLÖSER':'TRIGGERED BY','Abbrechen':'Cancel','Aktionen':'Actions',
 'Aktivität (':'Activity (','Akzeptieren':'Accept','Alle einblenden':'Show all','Alle entfernen':'Delete all',
 'Alle gelesen':'Mark all read','Alle widerrufen':'Revoke all','Alle →':'All →','Alles im grünen Bereich.':'All good.',
 'Anmelde-Verlauf':'Login History','Ansichten':'Views','Antworten':'Reply','Art gelesen':'Mark kind read',
 'Auswahl aufheben':'Clear selection','BENACHRICHTIGUNGEN':'NOTIFICATIONS','BERECHTIGUNGEN':'PERMISSIONS',
 'BEWERBUNGEN':'APPLICATIONS','Bald fällig':'Due soon','Bearbeiten':'Edit','Bewerbung anlegen':'New application',
 'CSV Import:':'CSV Import:','Code gelesen':'Mark code read','Datei':'File','Datensatz öffnen':'Open record',
 'Download':'Download','Drucken':'Print','Duplizieren':'Duplicate',
 'E-Mail noch nicht verifiziert — wird nach Änderung erneut ausstehend.':'E-mail not verified yet — will be pending again after a change.',
 'Entfernen':'Remove','Erneut versuchen':'Retry','Erste Zeile = Spaltennamen (':'First row = column names (',
 'Erstellt:':'Created:','FAVORITEN':'FAVORITES','FESTSTELLUNGEN':'FINDINGS','Filter zurücksetzen':'Reset filters',
 'Gelesen':'Read','Gelesene entfernen':'Delete read','Geändert:':'Changed:','Heute':'Today','Heute fällig':'Due today',
 'Ihnen ist nichts offen zugewiesen.':'Nothing open is assigned to you.','Invertieren':'Invert','Jetzt abrufen':'Fetch now',
 'Kein Modul gefunden.':'No module found.','Keine Feststellungen.':'No findings.','Keine Gruppierung':'No grouping',
 'Keine Kanten zu dieser Entität.':'No edges for this entity.','Keine Rollen für diesen Mandanten.':'No roles for this tenant.',
 'Keine Treffer.':'No hits.','Keine weiteren Kanten.':'No more edges.','Konto löschen':'Delete account','Kopieren':'Copy',
 'Lade Report…':'Loading report…','Letzte Ereignisse':'Recent Events','Lädt…':'Loading…','Löschen':'Delete',
 'MANDANT':'TENANT','Metrik-Trends':'Metric Trends','Mir zugewiesen':'Assigned to me','Mitgliedschaften':'Memberships',
 'Nach der Änderung werden alle API-Token widerrufen — die Seite lädt neu.':'After the change all API tokens will be revoked — the page reloads.',
 'Neue Version':'New version','Neuer API-Token — nur einmal sichtbar. Jetzt kopieren:':'New API token — visible only once. Copy now:',
 'Noch keine Ansichten.':'No saved views yet.','Noch keine Antworten.':'No answers yet.','Noch keine Bewerbungen.':'No applications yet.',
 'Noch keine Einträge für diesen Mandanten.':'No entries for this tenant yet.','Profil bearbeiten':'Edit profile','ROLLEN':'ROLES',
 'Rechte (Abilities)':'Permissions (abilities)','Rechte speichern':'Save permissions','Rollen':'Roles','Rollen speichern':'Save roles',
 'Schließen':'Close','Spalten':'Columns','Stummgeschaltete Arten':'Muted kinds','Tastenkürzel':'Shortcuts',
 'Test-Benachrichtigung':'Test notification','Ungelesen':'Unread','VERKNÜPFUNGEN':'LINKS','VERLAUF':'HISTORY',
 'VERSIONEN':'VERSIONS','Von mir':'Mine','Vorlage mit Beispielzeile einfügen':'Insert template with example row',
 'Zeitraum':'Period','Zum Datensatz':'To the record','Zum Inhalt springen':'Skip to content','Ändern':'Change',
 'ÜBERFÄLLIG':'OVERDUE','Überfällig gesamt':'Overdue total',
 'Abmelden':'Sign out','Aktualisieren':'Refresh','Alle API-Token widerrufen':'Revoke all API tokens',
 'Alle Benachrichtigungen zu diesem Datensatz':'All notifications for this record','Alle Ereignisse zu diesem Datensatz':'All events for this record',
 'Alle Felder als lesbarer Text':'All fields as readable text','Alle Gruppen aufklappen':'Expand all groups',
 'Alle Gruppen einklappen':'Collapse all groups','Alle laden (l)':'Load all (l)','Als TSV in die Zwischenablage':'Copy as TSV to clipboard',
 'Ansicht löschen':'Delete view','Ausblenden':'Hide','Ausgewählte Zeilen als JSON-Datei':'Selected rows as JSON file',
 'Ausgewählte als gelesen markieren':'Mark selected as read','Ausgewählte als ungelesen markieren':'Mark selected as unread',
 'Auswahl umkehren (sichtbare Zeilen)':'Invert selection (visible rows)','Bearbeiten (e)':'Edit (e)',
 'Benachrichtigungen als NDJSON exportieren (aktive Filter werden übernommen)':'Export notifications as NDJSON (active filters applied)',
 'CSV exportieren':'Export CSV','CSV importieren (i)':'Import CSV (i)','Duplizieren (d)':'Duplicate (d)',
 'Eigene Rolle anlegen':'Create custom role','Erledigt markieren':'Mark as done','Filter löschen':'Clear filter',
 'Filter zurücksetzen (x)':'Reset filters (x)','Gespeicherte Ansichten':'Saved views','Gruppieren nach':'Group by',
 'Herunterladen':'Download','Heute fällige Einträge anzeigen':'Show entries due today','Link kopieren (p)':'Copy link (p)',
 'Menü':'Menu','Mitglied aus diesem Mandanten entfernen':'Remove member from this tenant',
 'Mitgliedschaft in diesem Mandanten beenden':'End membership in this tenant','Nachbarn zeigen':'Show neighbors',
 'Nächster (→)':'Next (→)','Rechte der Rolle bearbeiten':'Edit role permissions','Rolle löschen':'Delete role',
 'Schließen (Esc)':'Close (Esc)','Serverseitiger Audit-Export (NDJSON, alle Einträge)':'Server-side audit export (NDJSON, all entries)',
 'Spalten (c)':'Columns (c)','Speichern (Strg+↵)':'Save (Ctrl+↵)','Speichern und nächsten Eintrag anlegen':'Save and create next entry',
 'Suche löschen':'Clear search','Testet die Benachrichtigungsglocke':'Tests the notification bell','Vorheriger (←)':'Previous (←)',
 'Wert kopieren':'Copy value','Zuletzt-Liste für diese Sektion leeren':'Clear recent list for this section',
 'Überfällig (u)':'Overdue (u)','≤7 Tage (s)':'≤7 days (s)','≤7 Tage fällige Einträge anzeigen':'Show entries due within ≤7 days',
 'Aktualisieren (r)':'Refresh (r)','Ungelesene anzeigen':'Show unread','Überfällige Einträge anzeigen':'Show overdue entries',
 'Aktuelles Passwort (nur für Passwort-Änderung)':'Current password (only for password change)',
 'Angebot / Beschreibung…':'Proposal / description…','Antwort schreiben…':'Write a reply…','Filtern…':'Filter…',
 'Globale Suche — Firma, Aufgabe, Dokument…':'Global search — company, task, document…','Modul oder Aktion suchen…':'Search module or action…',
 'Neues Passwort (min. 12 Zeichen, Groß-/Kleinbuchstabe, Zahl)':'New password (min. 12 chars, upper/lowercase, digit)',
 'Neues Passwort wiederholen':'Repeat new password','Preis (€)':'Price (€)',
 'Alle sichtbaren Zeilen auswählen':'Select all visible rows','Befehlspalette':'Command palette',
 'Benachrichtigung entfernen':'Remove notification','Darstellung umschalten':'Toggle appearance','Fehler schließen':'Dismiss error',
 'Hauptnavigation':'Main navigation','Liste aktualisieren':'Refresh list','Navigation umschalten':'Toggle navigation',
 'API-Token angelegt — einmalig sichtbar.':'API token created — visible only once.','Aktion fehlgeschlagen (HTTP':'Action failed (HTTP',
 'Aktualisieren fehlgeschlagen (HTTP':'Refresh failed (HTTP','Analyse fehlgeschlagen (HTTP':'Analysis failed (HTTP',
 'Alle API-Token widerrufen? Der aktuell verwendete bleibt aktiv.':'Revoke all API tokens? The one currently in use stays active.',
 'Alle Benachrichtigungen entfernen?':'Delete all notifications?','Alle als gelesen markiert':'All marked as read',
 'Anfrage fehlgeschlagen — Rate-Limit erreicht, bitte':'Request failed — rate limit reached, please',
 'Anlegen fehlgeschlagen (HTTP':'Creation failed (HTTP','Antwort akzeptiert.':'Answer accepted.',
 'Antwort fehlgeschlagen (HTTP':'Reply failed (HTTP','Antwort gelöscht.':'Reply deleted.','Antwort gespeichert.':'Reply saved.',
 'Antwort löschen?':'Delete reply?','Art als gelesen markiert':'Kind marked as read','Aufgabe erledigt.':'Task completed.',
 'Benachrichtigung entfernt':'Notification removed','Benachrichtigungen exportiert':'Notifications exported',
 'Benutzer angelegt — Initiales Passwort:':'User created — initial password:','Bewerbung eingereicht.':'Application submitted.',
 'Bewerbung fehlgeschlagen (HTTP':'Application failed (HTTP','Bewerbung gelöscht.':'Application deleted.',
 'Bewerbung löschen?':'Delete application?','Code als gelesen markiert':'Code marked as read','Datensatz aktualisiert.':'Record updated.',
 'Demo-Seed fehlgeschlagen.':'Demo seed failed.','Download fehlgeschlagen':'Download failed',
 'Eigenes Konto wirklich löschen? Nur möglich, wenn du in keinem Mandanten mehr Mitglied bist. Alle API-Tokens werden widerrufen.':'Really delete your own account? Only possible when you are not a member of any tenant. All API tokens will be revoked.',
 'Eintrag angelegt.':'Entry created.','Ereignisse exportiert':'Events exported','Export fehlgeschlagen':'Export failed',
 'Fehler (HTTP':'Error (HTTP','Fehler beim Speichern.':'Failed to save.','Gelöscht.':'Deleted.','Hinweis ausgeblendet.':'Hint hidden.',
 'Konto gelöscht.':'Account deleted.','Kopieren fehlgeschlagen.':'Copy failed.','Kopiert.':'Copied.',
 'Mandant angelegt:':'Tenant created:','Mandant umbenannt:':'Tenant renamed:','Mandant verlassen.':'Tenant left.',
 'Report fehlgeschlagen (HTTP':'Report failed (HTTP','Rolle angelegt.':'Role created.',
 'Sitzung abgelaufen — bitte neu anmelden.':'Session expired — please sign in again.','Testbenachrichtigung gesendet':'Test notification sent',
 'Token kopiert':'Token copied','URL kopiert':'URL copied','Umbenennen fehlgeschlagen (HTTP':'Rename failed (HTTP',
 'Ungespeicherte Änderungen verwerfen?':'Discard unsaved changes?','Upload fehlgeschlagen (HTTP':'Upload failed (HTTP',
 'Version hochgeladen.':'Version uploaded.','Webhook-Quelle angelegt — URL kopieren und im Quellsystem hinterlegen.':'Webhook source created — copy the URL into your source system.',
 'Wiederhergestellt.':'Restored.','Wirklich löschen?':'Really delete?',
 'entfernt':'removed','gelesene Benachrichtigungen entfernt':'read notifications removed','Benachrichtigungen entfernt':'notifications removed',
 'gelöscht.':'deleted.','Zeilen kopiert.':'rows copied.','Auswahl zurückgesetzt.':'selection cleared.','weitere':'more',
 'Abschließen':'Complete','Als gelesen markieren':'Mark as read','Als gelesen markiert.':'Marked as read.',
 'Als ungelesen markieren':'Mark as unread','Als ungelesen markiert.':'Marked as unread.','Bestätigt':'Confirmed',
 'Bitte eine Datei wählen.':'Please choose a file.','Demo-Daten geladen.':'Demo data loaded.','Demo-Seed fehlgeschlagen (HTTP':'Demo seed failed (HTTP',
 'Entfernen fehlgeschlagen (HTTP':'Removal failed (HTTP','Entität':'Entity','Fenster für »bald fällig«-Badges (Tage)':'Window for "due soon" badges (days)',
 'Filter: Überfällig':'Filter: Overdue','Fällig':'Due','Gefährdung':'Hazard','Gefährdungsbeurteilung':'Risk assessment',
 'Gefährdungsbeurteilungen':'Risk assessments','Gelöst':'Resolved','Geschäftsführer':'Managing director','Geändert von':'Changed by',
 'Graphen · Entitäten':'Graph · Entities','Gültig ab':'Valid from','In Prüfung':'Under review','Kapazität/Tag':'Capacity/day',
 'Keine Änderung.':'No change.','Kompakte Zeilenhöhe':'Compact row height','Laufende Aufträge':'Running orders',
 'Löschen fehlgeschlagen (HTTP':'Deletion failed (HTTP','Mitglied entfernt.':'Member removed.','Neuer Name für':'New name for',
 'Noch nie ausgeführt':'Never run','Normale Zeilenhöhe':'Normal row height','Passwort geändert':'Password changed',
 'Passwort geändert — Seite wird neu geladen.':'Password changed — page will reload.','Passwort-Felder unvollständig oder ungleich.':'Password fields incomplete or mismatch.',
 'Profil aktualisiert.':'Profile updated.','Prüfung':'Inspection','Prüfungen':'Inspections','Qualität':'Quality',
 'Rechte gespeichert.':'Permissions saved.','Rechte geändert':'Permissions changed','Rolle gelöscht.':'Role deleted.',
 'Rollen gespeichert.':'Roles saved.','Rollen geändert':'Roles changed','Speichern fehlgeschlagen (HTTP':'Save failed (HTTP',
 'Stummschaltung für':'Mute for','Stummschaltung für Art aufheben':'Unmute kind','Teilweise fehlgeschlagen.':'Partially failed.',
 'Verlassen fehlgeschlagen (HTTP':'Leave failed (HTTP','Wieder öffnen':'Reopen','Wiederherstellen fehlgeschlagen.':'Restore failed.',
 'Zeile auswählen:':'Select row:','Zu Favoriten hinzufügen':'Add to favorites','Zu Favoriten hinzugefügt':'Added to favorites',
 'Zuletzt geändert:':'Last changed:','Aus Favoriten entfernt':'Removed from favorites','aktualisiert':'updated',
 'hinzugefügt':'added','hochgeladen':'uploaded','leer = zufällig generiert':'empty = randomly generated',
 'Älter':'Older','Öffnen':'Open','Übergeordnet':'Parent','Überfällige Feststellungen':'Overdue findings',
 'Aufträge':'Orders','Aufträge offen':'Open orders','Urlaubsanträge offen':'Open leave requests','Liquidität (Monat)':'Liquidity (month)',
 '“ gespeichert.':'” saved.','“ gelöscht.':'” deleted.','Ansicht „':'View "','Rolle „':'Role "','Mandant „':'Tenant "',
 'aus dem Mandanten entfernen?':'remove from this tenant?','Diese Woche':'This week','Ohne Status':'No status','Nicht zugewiesen':'Unassigned',
 'Rolle löschen? Zugewiesene Nutzer verlieren diese Rolle.':'Delete role? Assigned users lose this role.',
 'Mandant wirklich verlassen? Du verlierst alle Rollen und Zugriff.':'Really leave this tenant? You lose all roles and access.',
 'Einträge wirklich löschen?':'Really delete the entries?','Mitglied':'Member','Vormerken':'Shortlist','Vergeben':'Award','Ablehnen':'Reject',
 'Befehlspalette öffnen (↑/↓ wählen, Enter öffnen)':'Open command palette (↑/↓ to select, Enter to open)','Suche fokussieren':'Focus search',
 'Neuen Eintrag anlegen':'Create new entry','Eintrag bearbeiten (Drawer)':'Edit entry (drawer)','Eintrag duplizieren (Drawer)':'Duplicate entry (drawer)',
 'Sektion (ent)pinnen':'(Un)pin section','Alle Zeilen (ab)wählen':'(De)select all rows','Deep-Link kopieren (Drawer)':'Copy deep link (drawer)',
 'Erste gefilterte Zeile öffnen':'Open first filtered row','Alle Zeilen laden':'Load all rows','Liste / Datensatz neu laden':'Reload list / record',
 'CSV-Import öffnen':'Open CSV import','Spalten-Picker':'Column picker','Ansichten-Picker':'Views picker','Überfällig-Filter':'Overdue filter',
 '≤7-Tage-Filter':'≤7-day filter','Heute-Filter':'Today filter','Gruppierung wechseln (Status, Zeitraum, FK-Spalten)':'Cycle grouping (status, period, FK columns)',
 'Zum Dashboard':'To dashboard','n-te Zeile öffnen':'Open nth row','Vorheriger / nächster Eintrag (Drawer)':'Previous / next entry (drawer)',
 'Erster / letzter Eintrag (Drawer)':'First / last entry (drawer)','Drawer breit/schmal':'Drawer wide/narrow','Kompakte Zeilen':'Compact rows',
 'JSON kopieren (Drawer)':'Copy JSON (drawer)','Rückgängig (Löschen/Status)':'Undo (delete/status)','Nächster Mandant':'Next tenant',
 'Formular absenden':'Submit form','Dunkel/Hell umschalten':'Toggle dark/light','Diese Übersicht':'This overview',
 'Speichern & neu':'Save & new','Sekunden':'seconds','Minute':'minute','Minuten':'minutes','Stunde':'hour','Stunden':'hours','Tag':'day','Tagen':'days',
 'Neue Benachrichtigung':'New notification','Keine Ansichten gespeichert':'No saved views',
};
    const T_EN = {'Neuer Mandant':'New Tenant','Umbenennen':'Rename','Verlassen':'Leave','Speichern':'Save','Abbrechen':'Cancel','Löschen':'Delete','Bearbeiten':'Edit','Duplizieren':'Duplicate','Alle gelesen':'Mark all read','Gelesene entfernen':'Delete read','Alle entfernen':'Delete all','Mehr laden':'Load more','Alle laden':'Load all','Drucken':'Print','Spalten':'Columns','Filter zurücksetzen':'Reset filters','Exportieren':'Export','Importieren':'Import','Suchen':'Search','Passwort ändern':'Change Password','Öffnen':'Open','Senden':'Send','Antworten':'Reply','Hochladen':'Upload','Ereignisse':'Events','Benachrichtigungen':'Notifications','Überfällig':'Overdue','Heute':'Today','Von mir':'Mine','Ungelesen':'Unread','Stumm':'Muted','Mitglieder':'Members','Aktionen':'Actions','alle auf':'expand all','alle zu':'collapse all','Module filtern…':'Filter modules…','Keine Einträge vorhanden.':'No entries.','Keine Einträge für diese Filter.':'No entries for these filters.','Eintrag':'entry','Einträge':'entries','überfällig':'overdue','heute':'today','Unternehmenssteuerung':'Business Management','Keine Berechtigung':'No permission','Fehler beim Laden':'Failed to load','Rückgängig':'Undo','zugewiesen':'assigned','Demo-Daten laden':'Load demo data','Lade Demo-Daten…':'Loading demo data…','Ausgeblendete: ':'Hidden: ','Fällig ':'Due ','Art gelesen':'Mark kind read','Art entfernen':'Delete kind','Art stummschalten':'Mute kind','Art ungelesen':'Mark kind unread','Code gelesen':'Mark code read','Code entfernen':'Delete code','leeren ×':'clear ×','Nächste Fristen':'Upcoming Deadlines','Offene Aufgaben':'Open Tasks','Letzte Ereignisse':'Recent Events','Aktivität':'Activity','alle →':'all →','Ansichten':'Views','Ansicht speichern':'Save view','Neu':'New','In Bearbeitung':'In progress','Anmelden':'Sign in','Abmelden':'Sign out','Gruppiert nach: ':'Grouped by: ','Gruppierung aus':'Grouping off','Nach Status gruppieren':'Group by status','Mir zugewiesen':'Assigned to me','Ohne Verantwortlichen':'Unassigned','≤7 Tage':'≤7 days','Schwere ':'Severity ','Zuletzt geändert':'Last changed','Mitglied seit':'Member since','Mitgliedschaften':'Memberships','Anmelde-Verlauf':'Login History','BENACHRICHTIGUNGEN':'NOTIFICATIONS','VERKNÜPFUNGEN':'LINKS','VERLAUF':'HISTORY','FESTSTELLUNGEN':'FINDINGS','BEWERBUNGEN':'APPLICATIONS','ANTWORTEN':'ANSWERS','VERSIONEN':'VERSIONS','Mitglied(er)':'member(s)','Keine Beschreibung':'No description'};
    Object.assign(T_EN, T_EN2);
    Object.assign(T_EN, {'Aufgabe':'Task','Unternehmen':'Company','Person':'Person','Dokument':'Document','Dokumentversion':'Document version','Unterweisung':'Instruction','Prüfung':'Inspection','Frist':'Deadline','Gefährdungsbeurteilung':'Risk assessment','Betriebsanweisung':'Operating instruction','Experte':'Expert','Frage':'Question','Ausschreibung':'Tender','Finanzbericht':'Financial report','Urlaubsantrag':'Leave request','Maschine':'Machine','Produktionsauftrag':'Production order','Beteiligung':'Participation','Data-Objekt':'Data object','Portfolio':'Portfolio','Investition':'Investment','Kante':'Edge','Strategie':'Strategy','Projekt':'Project','KI-Analyse':'AI analysis','Executive-Report':'Executive report','Audit':'Audit','Feststellung':'Finding','Benutzer':'User','Webhook':'Webhook','Konnektor':'Connector','Webhook-Quelle':'Webhook source'});
    Object.assign(T_EN, {'— wählen —':'— select —','— Experte wählen —':'— select expert —','Aktuelle Ansicht speichern':'Save current view','ausgewählt':'selected','öffnen →':'open →','löschen':'delete','Geändert:':'Changed:'});
    Object.assign(T_EN, {'Neu bei ALLOCORE?':'New to ALLOCORE?','Eine kurze Tour zeigt dir die wichtigsten Bereiche — Schritt für Schritt.':'A short tour walks you through the key areas — step by step.','Tour starten':'Start tour','Später':'Later','Schritt':'Step','Überspringen':'Skip','Zurück':'Back','Weiter':'Next','Fertig!':'Done!','Fertig':'Done','Tour':'Tour','Rundgang starten':'Start tour','Navigation':'Navigation','Alle Module in einer Seitenleiste — Dashboard, Aufgaben, Dokumente, Compliance, Team und mehr. Ein Klick öffnet die Sektion.':'All modules in one sidebar — dashboard, tasks, documents, compliance, team and more. One click opens the section.','Mandant wählen':'Choose tenant','Hier wechselst du zwischen deinen Unternehmen/Mandanten — alle Daten gehören zum gewählten Mandanten.':'Switch between your companies/tenants here — all data belongs to the selected tenant.','Module filtern':'Filter modules','Tippen, um Module schnell zu finden — z. B. „Aufgaben" — Enter öffnet den ersten Treffer.':'Type to quickly find modules — e.g. "tasks" — Enter opens the first match.','Globale Suche':'Global search','Suche über alles: Firmen, Aufgaben, Dokumente — Enter öffnet den ersten Treffer direkt.':'Search across everything: companies, tasks, documents — Enter opens the first hit directly.','KPI-Karten':'KPI cards','Deine wichtigsten Kennzahlen auf einen Blick. Ein Klick auf eine Karte öffnet die zugehörige Sektion.':'Your key metrics at a glance. Clicking a card opens the related section.','KI-Hinweise':'AI insights','Automatisch erkannte Risiken und offene Punkte — ein Klick springt zum betroffenen Eintrag.':'Automatically detected risks and open items — one click jumps to the affected record.','Erinnerungen zu Fristen und Zuweisungen — das Badge zeigt, was ansteht.':'Reminders for deadlines and assignments — the badge shows what is pending.','Suchen & Filtern':'Search & filter','Jede Liste durchsuchbar und filterbar — Taste „/" fokussiert die Suche, „x" setzt Filter zurück.':'Every list is searchable and filterable — press "/" to focus search, "x" resets filters.','Neuer Eintrag':'New entry','Hier legst du neue Datensätze an — oder mit Taste „n". Eine Zeile anklicken öffnet Details, Bearbeiten und Status-Aktionen.':'Create new records here — or press "n". Clicking a row opens details, editing and status actions.','Darstellung':'Appearance','Zwischen heller und dunkler Darstellung wechseln — auch mit Taste „t".':'Switch between light and dark mode — also via the "t" key.','Das war der Rundgang. Tipp: Ctrl+K öffnet die Befehlspalette, „?" zeigt alle Tastenkürzel.':'That was the tour. Tip: Ctrl+K opens the command palette, "?" shows all shortcuts.'});

    Object.assign(T_EN, {
 'Stand':'As of','gesamt':'total','Tag':'Day','Top-Tag':'Top day','Meiste Ereignisse an einem Tag — Tag filtern':'Most events in one day — filter by day',
 'Zuletzt':'Recent','Eintrag':'Entry','gestern':'yesterday','heute':'today','morgen':'tomorrow','Neu':'New',
 'Fällig gesamt':'Due total','Einträge':'entries','Zeilen':'rows','Bewerbung':'Application','Antwort':'Reply',
 'gespeichert':'saved','gelöscht':'deleted','gefunden':'found','anlegen':'create','verfügbar':'available',
 'Mehr laden':'Load more','Alle laden':'Load all','Zurück':'Back','Weiter':'Next','Exportieren':'Export',
 'Importieren':'Import','Abbrechen':'Cancel','Senden':'Send','Antworten':'Reply','Fällig':'Due',
 'Kopieren':'Copy','JSON exportieren':'Export JSON','Liste neu laden':'Reload list','Liste drucken':'Print list',
 'Alle zu':'Collapse all','Alle auf':'Expand all','Zuletzt ':'Recent ','Keine':'No','heute ':'today ','überfällig ':'overdue ',
});

    Object.assign(T_EN, {
        'Wirklich löschen?': 'Really delete?',
        'Duplizieren: ': 'Duplicate: ',
        'Neu: ': 'New: ',
        'Bearbeiten: ': 'Edit: ',
        'AKZEPTIERT': 'ACCEPTED',
        'ANTWORTEN': 'ANSWERS',
        'API-Token angelegt — einmalig sichtbar.': 'API token created — visible only once.',
        'AUSLÖSER': 'ACTOR',
        'Abmelden': 'Log out',
        'Abschließen': 'Complete',
        'Aktion fehlgeschlagen (HTTP ': 'Action failed (HTTP ',
        'Aktionen': 'Actions',
        'Aktivität (': 'Activity (',
        'Aktualisieren': 'Refresh',
        'Aktualisieren (r)': 'Refresh (r)',
        'Aktualisieren fehlgeschlagen (HTTP ': 'Refresh failed (HTTP ',
        'Aktuelles Passwort (nur für Passwort-Änderung)': 'Current password (only for password change)',
        'Akzeptieren': 'Accept',
        'Alle API-Token widerrufen': 'Revoke all API tokens',
        'Alle API-Token widerrufen? Der aktuell verwendete bleibt aktiv.': 'Revoke all API tokens? The one currently in use stays active.',
        'Alle Benachrichtigungen entfernen?': 'Remove all notifications?',
        'Alle Benachrichtigungen zu diesem Datensatz': 'All notifications for this record',
        'Alle Ereignisse zu diesem Datensatz': 'All events for this record',
        'Alle Felder als lesbarer Text': 'All fields as readable text',
        'Alle Gruppen aufklappen': 'Expand all groups',
        'Alle einblenden': 'Show all',
        'Alle entfernen': 'Remove all',
        'Alle gelesen': 'Mark all read',
        'Alle laden (l)': 'Load all (l)',
        'Alle sichtbaren Zeilen auswählen': 'Select all visible rows',
        'Alle widerrufen': 'Revoke all',
        'Alle →': 'All →',
        'Alles im grünen Bereich.': 'All in the green.',
        'Als TSV in die Zwischenablage': 'Copy as TSV',
        'Als gelesen markiert.': 'Marked as read.',
        'Als ungelesen markiert.': 'Marked as unread.',
        'Analyse fehlgeschlagen (HTTP ': 'Analysis failed (HTTP ',
        'Anfrage fehlgeschlagen — Rate-Limit erreicht, bitte ': 'Request failed — rate limit reached, please ',
        'Angebot / Beschreibung…': 'Offer / description…',
        'Anlegen fehlgeschlagen (HTTP ': 'Create failed (HTTP ',
        'Anmelde-Verlauf': 'Login history',
        'Ansicht löschen': 'Delete view',
        'Ansicht „': 'View "',
        'Ansichten': 'Views',
        'Antwort akzeptiert.': 'Answer accepted.',
        'Antwort fehlgeschlagen (HTTP ': 'Answer failed (HTTP ',
        'Antwort gelöscht.': 'Answer deleted.',
        'Antwort gespeichert.': 'Answer saved.',
        'Antwort löschen?': 'Delete answer?',
        'Antwort schreiben…': 'Write answer…',
        'Art ': 'Kind ',
        'Art gelesen': 'Kind read',
        'Aufgabe erledigt.': 'Task done.',
        'Aufträge offen': 'Open orders',
        'Aus Favoriten entfernt': 'Removed from favorites',
        'Ausblenden': 'Hide',
        'Ausgewählte Zeilen als JSON-Datei': 'Selected rows as JSON file',
        'Ausgewählte als gelesen markieren': 'Mark selected as read',
        'Ausgewählte als ungelesen markieren': 'Mark selected as unread',
        'Auswahl aufheben': 'Clear selection',
        'Auswahl umkehren (sichtbare Zeilen)': 'Invert selection (visible rows)',
        'BENACHRICHTIGUNGEN': 'NOTIFICATIONS',
        'BERECHTIGUNGEN': 'PERMISSIONS',
        'BEWERBUNGEN': 'APPLICATIONS',
        'Bald fällig': 'Due soon',
        'Bearbeiten': 'Edit',
        'Bearbeiten (e)': 'Edit (e)',
        'Befehlspalette': 'Command palette',
        'Benachrichtigung entfernen': 'Remove notification',
        'Benachrichtigung entfernt': 'Notification removed',
        'Benachrichtigungen': 'Notifications',
        'Benachrichtigungen als NDJSON exportieren (aktive Filter werden übernommen)': 'Export notifications as NDJSON (active filters applied)',
        'Benachrichtigungen exportiert': 'Notifications exported',
        'Benutzer angelegt — Initiales Passwort: ': 'User created — initial password: ',
        'Bewerbung anlegen': 'Create application',
        'Bewerbung eingereicht.': 'Application submitted.',
        'Bewerbung fehlgeschlagen (HTTP ': 'Application failed (HTTP ',
        'Bewerbung gelöscht.': 'Application deleted.',
        'Bewerbung löschen?': 'Delete application?',
        'Bitte eine Datei wählen.': 'Please choose a file.',
        'CSV exportieren': 'Export CSV',
        'CSV importieren (i)': 'Import CSV (i)',
        'Code gelesen': 'Code read',
        'Darstellung umschalten': 'Toggle theme',
        'Datei': 'File',
        'Datensatz aktualisiert.': 'Record updated.',
        'Datensatz öffnen': 'Open record',
        'Demo-Daten geladen.': 'Demo data loaded.',
        'Demo-Seed fehlgeschlagen (HTTP ': 'Demo seed failed (HTTP ',
        'Demo-Seed fehlgeschlagen.': 'Demo seed failed.',
        'Diese Woche': 'This week',
        'Download': 'Download',
        'Download fehlgeschlagen': 'Download failed',
        'Drucken': 'Print',
        'Dunkel/Hell umschalten': 'Toggle dark/light',
        'Duplizieren': 'Duplicate',
        'Duplizieren (d)': 'Duplicate (d)',
        'E-Mail': 'E-mail',
        'E-Mail noch nicht verifiziert — wird nach Änderung erneut ausstehend.': 'E-mail not verified yet — will be pending again after a change.',
        'Eigene Rolle anlegen': 'Create custom role',
        'Eigenes Konto wirklich löschen? Nur möglich, wenn du in keinem Mandanten mehr Mitglied bist. Alle API-Tokens werden widerrufen.': 'Really delete your own account? Only possible when you are not a member of any tenant. All API tokens will be revoked.',
        'Eintrag angelegt.': 'Entry created.',
        'Entfernen': 'Remove',
        'Entfernen fehlgeschlagen (HTTP ': 'Remove failed (HTTP ',
        'Ereignisse exportiert': 'Events exported',
        'Erledigt markieren': 'Mark done',
        'Erneut versuchen': 'Retry',
        'Erste Zeile = Spaltennamen (': 'First row = column names (',
        'Export fehlgeschlagen': 'Export failed',
        'FAVORITEN': 'FAVORITES',
        'Fehler (HTTP ': 'Error (HTTP ',
        'Fehler beim Speichern.': 'Error saving.',
        'Fehler schließen': 'Dismiss error',
        'Filter löschen': 'Clear filter',
        'Filter zurücksetzen': 'Reset filters',
        'Filter zurücksetzen (x)': 'Reset filters (x)',
        'Filter: Überfällig': 'Filter: Overdue',
        'Filter: Überfällig ': 'Filter: Overdue ',
        'Filtern…': 'Filter…',
        'Gefährdung': 'Hazard',
        'Gelesen': 'Read',
        'Gelesene entfernen': 'Remove read',
        'Gelöscht.': 'Deleted.',
        'Gelöst': 'Resolved',
        'Geschäftsführer': 'Managing director',
        'Gespeicherte Ansichten': 'Saved views',
        'Geändert von': 'Changed by',
        'Globale Suche — Firma, Aufgabe, Dokument…': 'Global search — company, task, document…',
        'Gruppieren nach': 'Group by',
        'Gültig ab': 'Valid from',
        'Hauptnavigation': 'Main navigation',
        'Herunterladen': 'Download',
        'Heute': 'Today',
        'Heute fällig': 'Due today',
        'Heute fällige Einträge anzeigen': 'Show entries due today',
        'Hinweis ausgeblendet.': 'Hint hidden.',
        'Ihnen ist nichts offen zugewiesen.': 'Nothing open is assigned to you.',
        'Invertieren': 'Invert',
        'Jetzt abrufen': 'Fetch now',
        'Kapazität/Tag': 'Capacity/day',
        'Kein Modul gefunden.': 'No module found.',
        'Keine Einträge für diese Filter.': 'No entries for these filters.',
        'Keine Einträge vorhanden.': 'No entries.',
        'Keine Feststellungen.': 'No findings.',
        'Keine Gruppierung': 'No grouping',
        'Keine Kanten zu dieser Entität.': 'No edges for this entity.',
        'Keine Rollen für diesen Mandanten.': 'No roles for this tenant.',
        'Keine Treffer.': 'No matches.',
        'Keine weiteren Kanten.': 'No more edges.',
        'Keine Änderung.': 'No change.',
        'Konto gelöscht.': 'Account deleted.',
        'Konto löschen': 'Delete account',
        'Kopieren fehlgeschlagen.': 'Copy failed.',
        'Kopiert.': 'Copied.',
        'Lade Report…': 'Loading report…',
        'Link kopieren (p)': 'Copy link (p)',
        'Liste aktualisieren': 'Refresh list',
        'Lädt…': 'Loading…',
        'Löschen': 'Delete',
        'Löschen fehlgeschlagen (HTTP ': 'Delete failed (HTTP ',
        'MANDANT': 'TENANT',
        'Mandant': 'Tenant',
        'Mandant angelegt: ': 'Tenant created: ',
        'Mandant umbenannt: ': 'Tenant renamed: ',
        'Mandant verlassen.': 'Tenant left.',
        'Mandant „': 'Tenant "',
        'Menü': 'Menu',
        'Mir zugewiesen': 'Assigned to me',
        'Mitglied': 'Member',
        'Mitglied aus diesem Mandanten entfernen': 'Remove member from this tenant',
        'Mitglied entfernt.': 'Member removed.',
        'Mitglied(er)': 'Member(s)',
        'Mitgliedschaft in diesem Mandanten beenden': 'End membership in this tenant',
        'Mitgliedschaften': 'Memberships',
        'Modul oder Aktion suchen…': 'Search module or action…',
        'Module filtern…': 'Filter modules…',
        'NEU': 'NEW',
        'Nach der Änderung werden alle API-Token widerrufen — die Seite lädt neu.': 'After the change all API tokens will be revoked — the page reloads.',
        'Nachbarn zeigen': 'Show neighbors',
        'Name': 'Name',
        'Navigation umschalten': 'Toggle navigation',
        'Neue Version': 'New version',
        'Neuer API-Token — nur einmal sichtbar. Jetzt kopieren:': 'New API token — visible only once. Copy now:',
        'Neuer Mandant': 'New tenant',
        'Neuer Name für ': 'New name for ',
        'Neues Passwort (min. 12 Zeichen, Groß-/Kleinbuchstabe, Zahl)': 'New password (min. 12 chars, upper/lowercase, digit)',
        'Neues Passwort wiederholen': 'Repeat new password',
        'Nicht zugewiesen': 'Not assigned',
        'Noch keine Ansichten.': 'No views yet.',
        'Noch keine Antworten.': 'No answers yet.',
        'Noch keine Bewerbungen.': 'No applications yet.',
        'Noch keine Einträge für diesen Mandanten.': 'No entries for this tenant yet.',
        'Nächster (→)': 'Next (→)',
        'Ohne Status': 'No status',
        'Passwort geändert': 'Password changed',
        'Passwort geändert — Seite wird neu geladen.': 'Password changed — page will reload.',
        'Passwort ändern': 'Change password',
        'Passwort-Felder unvollständig oder ungleich.': 'Password fields incomplete or do not match.',
        'Preis (€)': 'Price (€)',
        'Profil aktualisiert.': 'Profile updated.',
        'Profil bearbeiten': 'Edit profile',
        'Qualität': 'Quality',
        'ROLLEN': 'ROLES',
        'Rechte (Abilities)': 'Abilities',
        'Rechte der Rolle bearbeiten': 'Edit role permissions',
        'Rechte gespeichert.': 'Permissions saved.',
        'Rechte geändert': 'Permissions changed',
        'Rechte speichern': 'Save permissions',
        'Report fehlgeschlagen (HTTP ': 'Report failed (HTTP ',
        'Rolle angelegt.': 'Role created.',
        'Rolle gelöscht.': 'Role deleted.',
        'Rolle löschen': 'Delete role',
        'Rolle „': 'Role "',
        'Rollen': 'Roles',
        'Rollen gespeichert.': 'Roles saved.',
        'Rollen geändert': 'Roles changed',
        'Rollen speichern': 'Save roles',
        'Schließen': 'Close',
        'Schließen (Esc)': 'Close (Esc)',
        'Serverseitiger Audit-Export (NDJSON, alle Einträge)': 'Server-side audit export (NDJSON, all entries)',
        'Sitzung abgelaufen — bitte neu anmelden.': 'Session expired — please log in again.',
        'Spalten': 'Columns',
        'Spalten (c)': 'Columns (c)',
        'Speichern': 'Save',
        'Speichern & neu': 'Save & new',
        'Speichern (Strg+↵)': 'Save (Ctrl+↵)',
        'Speichern fehlgeschlagen (HTTP ': 'Save failed (HTTP ',
        'Speichern und nächsten Eintrag anlegen': 'Save and create next entry',
        'Stummgeschaltete Arten': 'Muted kinds',
        'Suche löschen': 'Clear search',
        'Tastenkürzel': 'Shortcuts',
        'Teilweise fehlgeschlagen.': 'Partially failed.',
        'Test-Benachrichtigung': 'Test notification',
        'Testet die Benachrichtigungsglocke': 'Tests the notification bell',
        'Umbenennen': 'Rename',
        'Umbenennen fehlgeschlagen (HTTP ': 'Rename failed (HTTP ',
        'Ungelesen': 'Unread',
        'Ungespeicherte Änderungen verwerfen?': 'Discard unsaved changes?',
        'Unternehmenssteuerung': 'Corporate management',
        'Upload fehlgeschlagen (HTTP ': 'Upload failed (HTTP ',
        'VERKNÜPFUNGEN': 'LINKS',
        'VERSIONEN': 'VERSIONS',
        'Verlassen': 'Leave',
        'Verlassen fehlgeschlagen (HTTP ': 'Leave failed (HTTP ',
        'Version hochgeladen.': 'Version uploaded.',
        'Von mir': 'From me',
        'Vorheriger (←)': 'Previous (←)',
        'Vorlage mit Beispielzeile einfügen': 'Insert template with example row',
        'Webhook-Quelle angelegt — URL kopieren und im Quellsystem hinterlegen.': 'Webhook source created — copy the URL into your source system.',
        'Wert kopieren': 'Copy value',
        'Wieder öffnen': 'Reopen',
        'Wiederhergestellt.': 'Restored.',
        'Wiederherstellen fehlgeschlagen.': 'Restore failed.',
        'Wirklich löschen?': 'Really delete?',
        'Zeitraum': 'Period',
        'Zu Favoriten hinzugefügt': 'Added to favorites',
        'Zuletzt-Liste für diese Sektion leeren': 'Clear recent list for this section',
        'Zum Datensatz': 'To record',
        'Zum Inhalt springen': 'Skip to content',
        'aktualisiert': 'updated',
        'alle auf': 'expand all',
        'alle zu': 'collapse all',
        'entfernt': 'removed',
        'hinzugefügt': 'added',
        'hochgeladen': 'uploaded',
        'leer = zufällig generiert': 'empty = randomly generated',
        'neue_rolle': 'new_role',
        'weitere': 'more',
        'Älter': 'Older',
        'Ändern': 'Change',
        'ÜBERFÄLLIG': 'OVERDUE',
        'Überfällig': 'Overdue',
        'Überfällig (u)': 'Overdue (u)',
        'Überfällig gesamt': 'Total overdue',
        'Übergeordnet': 'Parent',
        'überfällig': 'overdue',
        '“ gelöscht.': '" deleted.',
        '“ gespeichert.': '" saved.',
        '≤7 Tage (s)': '≤7 days (s)',
        '≤7 Tage fällige Einträge anzeigen': 'Show entries due ≤7 days'
    });

    Object.assign(T_EN, {' Einträge wirklich löschen?':' really delete entries?',' Ereignisse — Stunde filtern':' events — filter by hour',' Ereignisse — Tag filtern':' events — filter by day',' Ereignisse — Wochentag filtern':' events — filter by weekday',' Token widerrufen':' revoke tokens',' aktive API-Token':' active API tokens',' aufheben':' unmute',' aus dem Mandanten entfernen?':' remove from this tenant?',' gelöscht.':' deleted.',' wechseln':' switch',' · Stumm ':' · Muted ',' — keine Berechtigung (executive.view)?':' — missing permission (executive.view)?',' ≤ 7 Tage':' ≤ 7 days','" löschen? Zugewiesene Nutzer verlieren diese Rolle.':'" delete? Assigned users lose this role.','60-Tage-Verlauf: ':'60-day history: ','API-Token':'API token','Abruf gestartet.':'Fetch started.','Absagen':'Cancel','Alle':'All','Allgemein':'General','Als Kandidat':'As candidate','Anlegen':'Create','Auf Deutsch wechseln':'Switch to German','Aufträge (Tools)':'Orders (Tools)','Ausgestiegen':'Exited','Auslöser: ':'Triggered by: ','Ausmustern':'Retire','Berater':'Advisor','Breit':'Wide','Durchlaufzeit Σ (Tools)':'Lead time Σ (Tools)','Entfernt.':'Removed.','Fehler ':'Error ','Fehler beim Widerrufen':'Revoke failed','Fertige Aufträge (Tools)':'Orders done (Tools)','Filter: Mir zugewiesen ':'Filter: Assigned to me ','Filter: Ohne Verantwortlichen ':'Filter: Unassigned ','Filter: ≤7 Tage ':'Filter: ≤7 days ','Gemindert':'Mitigated','Heute · ':'Today · ','Hoch':'High','Keine Berechtigung (':'No permission (','Konnektor: ':'Connector: ','Kritisch':'Critical','Kunde':'Customer','Letzte Anmeldung von: ':'Last login from: ','Letzter Status: ':'Last status: ','Mandant umbenennen':'Rename tenant','Mandant: ':'Tenant: ','Marketingkosten (Tools)':'Marketing costs (Tools)','Maßnahmen':'Measures','Mindestens Kopfzeile + eine Datenzeile nötig.':'Need at least header + one data row.','Mitarbeiter':'Employee','Mitglied seit: ':'Member since: ','Mittel':'Medium','Modul ':'Module ','Name der Ansicht:':'View name:','Name des Mandanten':'Tenant name','Nein':'No','Netzwerkfehler.':'Network error.','Neu anlegen: ':'Create new: ','Niedrig':'Low','Onboarding':'Onboarding','Operativ':'Operational','Pünktliche Aufträge (Tools)':'On-time orders (Tools)','Rolle ':'Role ','Schulung':'Training','Sicherheit':'Security','Sonstiges':'Other','Spalte ':'Column ','Starten':'Start','Stumm · ':'Muted · ','Stummschaltung für ':'Mute for ','Stunde: ':'Hour: ','Suchen in ':'Search in ','Text':'Text','Tool-Daten':'Tool data','Umwelt':'Environment','Unbekannt':'Unknown','Unbekannte Spalten: ':'Unknown columns: ','Urlaub':'Leave','Urlaub offen':'Leave open','Warnung':'Warning','Webhook-Quellen':'Webhook sources','Websitebesucher (Tools)':'Website visitors (Tools)','Wissensgraph':'Knowledge graph','Wochentag: ':'Weekday: ','Workshop':'Workshop','Zeile auswählen: ':'Select row: ','Zuletzt geändert: ':'Last changed: ','von ':'by ','— kein Mandant —':'— no tenant —','≤ 7 Tage · ':'≤ 7 days · '});
    Object.assign(T_EN, {'alle gelesen':'mark all read','Metrik-Trends':'Metric trends','+ Report erstellen':'+ Create report','Reports':'Reports','+ Ersten Eintrag erstellen':'+ Create first entry','leeren':'clear','bearbeiten':'edit','+ Rolle':'+ Role','+ Kante anlegen':'+ Add edge','+ Feststellung anlegen':'+ Add finding','WEBHOOK-URL':'WEBHOOK URL','Erstellt:':'Created:','leer = * (alle Rechte)':'empty = * (all permissions)','). Trennzeichen ; oder ,':'). Delimiter ; or ,','verwalten →':'manage →','aktiv':'active'});
    return {
        section: initial, groups: GROUPS, kpiCards: KPI, icons: ICONS,
        NOTIF_KIND,
        T_EN,
        lang: localStorage.getItem('af_lang') || 'de',
        NOTIF_KIND,
        tenant: '', rows: null, columns: [], metrics: null, insights: [], events: [], trends: [], evSummary: null, evWindow: 7, spark: {}, exec: null, execReports: [], reportOpen: {}, reportData: {}, lookups: {}, navOpen: false, collapsed: {}, me: null, upcoming: [], openTasks: [],
        tenantList: {{ \Illuminate\Support\Js::from($tenants->map(fn($t) => ['id' => $t->id, 'name' => $t->name])) }},
        loading: false, error: '', detail: null, drawerWide: localStorage.getItem('af_drawer_wide') === '1', showCreate: false, compact: localStorage.getItem('af_density') === '1',
        form: {}, formError: '', formDirty: false, query: '', editing: null, dupMode: false, showImport: false, importText: '', importResult: '', importErr: false, importing: false, importProgress: '', newToken: null, tokenAbilities: [], toasts: [],
        sortKey: '', sortAsc: true, limit: 100, statusFilter: '', severityFilter: '', roleFilter: '', kindFilter: '', codeFilter: '', unreadOnly: false, mutedOnly: false, overdueOnly: false, dueSoonOnly: false, dueTodayOnly: false, myOnly: false, unassignedOnly: false, evGroup: '', evDay: '', evActor: '', evActorName: '', evWeekday: '', evHour: '', evActors: [], evActorsKey: '', evTypeList: [], tdType: '', linkCopied: false, confirmKindDel: false, confirmCodeDel: false, jsonCopied: false, textCopied: false, lastLoad: null, rowLoading: false, dark: document.documentElement.classList.contains('dark'), navQ: '',
        pins: JSON.parse(localStorage.getItem('af_pins') || '[]'), kbdHelp: false, hiddenCols: {}, colPicker: false, viewPicker: false, selected: {}, recent: [], navBadges: {}, navBadgesToday: {}, navBadgesWeek: {}, navTotal: null, navWindow: parseInt(localStorage.getItem('af_navwindow') || '7'),
        docVersions: [], entityEdges: [], allEdges: [], expandedEdge: null, auditFindings: [], confirmDel: false, rowEvents: [], evShown: 6, rowNotifs: [], allRoles: [], userRoles: [], userPerms: [], roleEdit: null, allPerms: [], rolePerms: [], newRole: '',
        tour: false, tourStep: 0, tourRect: null, tourSteps: [], tourPrompt: false,
        answers: [], answerText: '', apps: [], appForm: {expert_profile_id: '', proposal: '', price: ''}, palette: false, paletteQ: '', palIdx: 0, notif: false, globHits: [], globTimer: null, seeding: false, insightSev: '', fkQ: {}, insDismissed: JSON.parse(localStorage.getItem('af_insdismissed') || '[]'), dbNotifs: [], notifKinds: [], notifCodes: [], notifStats: null, tenantInfo: null, pwOpen: false, tenantModal: false, tenantMode: 'create', tenantName: '', pwErr: '', pwForm: {name:'',email:'',current:'',next:'',confirm:''}, loginHistory: [],
        meId: @js($user->id ?? null), offline: !navigator.onLine, groupBy: '', collapsedGroups: {}, dashMyOnly: false, rowsTotal: null, dashQ: '', dashHits: [], dashTimer: null,
        init() {
            document.documentElement.lang = this.lang;
            window.addEventListener('online', () => { this.offline = false; this.loadSection(true); this.loadNavBadges(); });
            window.addEventListener('offline', () => { this.offline = true; });
            this.loadNotifications();
            if (this.tenant) this.loadMe();
            const p = new URLSearchParams(location.search);
            const t = p.get('tenant');
            try { this.recent = JSON.parse(localStorage.getItem('af_recent') || '[]').filter(k => k !== this.section).slice(0, 5); } catch (e) { this.recent = []; }
            try { this.collapsed = JSON.parse(localStorage.getItem('af_navgroups') || '{}') || {}; } catch (e) { this.collapsed = {}; }
            if (this.section !== 'dashboard' && this.section !== 'executive') {
                const next = [this.section, ...this.recent.filter(k => k !== this.section)].slice(0, 5);
                try { localStorage.setItem('af_recent', JSON.stringify(next)); } catch (e) {}
            }
            try { if (! localStorage.getItem('af_tour_done')) this.tourPrompt = true; } catch (e) {}
            if (t) this.tenant = t;
            else if (localStorage.getItem('allocore.tenant')) this.tenant = localStorage.getItem('allocore.tenant');
            if (this.tenant) { this.loadSection(); this.loadNavBadges(); }
            if (p.get('new')) this.$nextTick(() => { if (this.tenant && this.canCreate()) this.openCreate(p.get('from') ? {from_entity_id: p.get('from')} : (p.get('audit') ? {audit_id: p.get('audit')} : {})); });
            if (p.get('q')) this.query = p.get('q');
            if (p.get('status')) this.statusFilter = p.get('status');
            if (p.get('severity')) this.severityFilter = p.get('severity');
            if (this.section === 'dashboard' && p.get('severity')) this.insightSev = p.get('severity');
            if (p.get('role')) this.roleFilter = p.get('role');
            if (p.get('overdue')) this.overdueOnly = true;
            if (p.get('dueSoon')) this.dueSoonOnly = true;
            if (p.get('today')) this.dueTodayOnly = true;
            if (p.get('my')) this.myOnly = true;
            if (p.get('unassigned')) this.unassignedOnly = true;
            if (p.get('unread')) this.unreadOnly = true;
            if (p.get('muted')) this.mutedOnly = true;
            if (p.get('kind')) this.kindFilter = p.get('kind');
            if (p.get('code')) this.codeFilter = p.get('code');
            if (p.get('group')) this.groupBy = p.get('group');
            if (p.get('eg')) this.evGroup = p.get('eg');
            if (p.get('day')) this.evDay = p.get('day');
            if (p.get('ea')) this.evActor = p.get('ea');
            if (p.get('an')) this.evActorName = p.get('an');
            if (p.get('wd')) this.evWeekday = p.get('wd');
            if (p.get('hr')) this.evHour = p.get('hr');
            this._urlSort = p.get('sort') || null;
            this.$watch('query', () => this.syncUrl());
            this.$watch('query', q => { if (this.section !== 'events') return; clearTimeout(this.evQTimer); this.evQTimer = setTimeout(() => this.loadSection(true), 300); });
            this.$watch('query', q => { if (this.section !== 'notifications') return; clearTimeout(this.ntQTimer); this.ntQTimer = setTimeout(() => this.loadSection(true), 300); });
            this.$watch('statusFilter', () => this.syncUrl());
            this.$watch('severityFilter', () => this.syncUrl());
            this.$watch('roleFilter', () => this.syncUrl());
            this.$watch('overdueOnly', () => this.syncUrl());
            this.$watch('dueSoonOnly', () => this.syncUrl());
            this.$watch('dueTodayOnly', () => this.syncUrl());
            this.$watch('myOnly', () => this.syncUrl());
            this.$watch('unassignedOnly', () => this.syncUrl());
            this.$watch('unreadOnly', () => this.syncUrl());
            this.$watch('mutedOnly', () => this.syncUrl());
            this.$watch('kindFilter', () => this.syncUrl());
            this.$watch('codeFilter', () => this.syncUrl());
            this.$watch('evGroup', () => { this.syncUrl(); if (this.section === 'events') this.loadSection(true); });
            this.$watch('evActor', () => { this.syncUrl(); if (this.section === 'events') this.loadSection(true); });
            this.$watch('evWeekday', () => { this.syncUrl(); if (this.section === 'events') this.loadSection(true); });
            this.$watch('evHour', () => { this.syncUrl(); if (this.section === 'events') this.loadSection(true); });
            this.$watch('evActorName', () => { this.syncUrl(); if (this.section === 'events') this.loadSection(true); });
            this.$watch('evDay', () => { this.syncUrl(); if (this.section === 'events') this.loadSection(true); });
            this.$watch('paletteQ', q => {
                clearTimeout(this.globTimer); this.globHits = [];
                const t = (q || '').trim();
                if (t.length < 3) return;
                this.globTimer = setTimeout(() => {
                    this.api('/api/v1/search?q=' + encodeURIComponent(t)).then(r => r.ok ? r.json() : [])
                        .then(d => { if (this.paletteQ.trim() === t) this.globHits = d || []; }).catch(() => {});
                }, 300);
            });
            this.$watch('palette', v => { if (!v) this.globHits = []; });
            this.$watch('dashQ', q => {
                clearTimeout(this.dashTimer); this.dashHits = [];
                const t = (q || '').trim();
                if (t.length < 3) return;
                this.dashTimer = setTimeout(() => {
                    this.api('/api/v1/search?q=' + encodeURIComponent(t)).then(r => r.ok ? r.json() : [])
                        .then(d => { if (this.dashQ.trim() === t) this.dashHits = d || []; }).catch(() => {});
                }, 300);
            });
            this.$watch('groupBy', v => { try { localStorage.setItem('af_group_' + this.section, v); localStorage.removeItem('af_gc_' + this.section); } catch (e) {} this.collapsedGroups = {}; this.syncUrl(); });
            this.$watch('tenant', v => {
                if (v) { localStorage.setItem('allocore.tenant', v); this.loadMe(); this.fetchTenantInfo(); } else { localStorage.removeItem('allocore.tenant'); this.me = null; this.tenantInfo = null; }
                this.setDocTitle();
            });
            this.$watch('rows', () => this.setDocTitle());
            this.$watch('detail', d => {
                if (d && d.id) {
                    if (this.section === 'notifications' && !d.read) this.markNotifRead(d);
                    this.pushRecentRow(d);
                    if (!this._prevFocus) this._prevFocus = document.activeElement;
                    this.$nextTick(() => { this.$refs.drawer && this.$refs.drawer.focus(); });
                } else if (this._prevFocus) {
                    try { this._prevFocus.focus(); } catch (e) {}
                    this._prevFocus = null;
                }
            });
            this.$watch('section', () => this.setDocTitle());
            setInterval(() => { if (this.tenant && !this.detail && !this.showCreate && !this.palette) this.loadSection(true); }, 30000);
            this.$watch('detail', v => {
                const url = new URL(location.href);
                if (v && v.id) url.searchParams.set('open', v.id); else url.searchParams.delete('open');
                history.replaceState(null, '', url);
                this.answers = []; this.answerText = ''; this.apps = []; this.appForm = {expert_profile_id: '', proposal: '', price: ''}; this.docVersions = []; this.entityEdges = []; this.expandedEdge = null; this.auditFindings = []; this.userRoles = []; this.userPerms = []; this.roleEdit = null; this.rolePerms = [];
                this.confirmDel = false; this.rowEvents = []; this.evShown = 6; this.rowNotifs = [];
                if (v && v.id && !['events','metrics','ai-analyses','executive','dashboard'].includes(this.section)) this.loadRowEvents(v.id);
                if (v && v.id && !['events','metrics','ai-analyses','executive','dashboard','notifications'].includes(this.section)) this.loadRowNotifs(v.id);
                if (v && this.section === 'questions') this.loadAnswers(v.id);
                if (v && this.section === 'tenders') this.loadApps(v.id);
                if (v && this.section === 'users') this.loadUserRoles(v.id);
                if (v && this.section === 'documents') this.loadDocVersions(v.id);
                if (v && this.section === 'graph-entities') this.loadEntityEdges(v.id);
                if (v && this.section === 'audits') this.loadAuditFindings(v.id);
            });
            ['detail', 'showCreate', 'palette', 'kbdHelp', 'navOpen'].forEach(p =>
                this.$watch(p, () => {
                    const open = !!(this.detail || this.showCreate || this.palette || this.kbdHelp || this.navOpen);
                    document.body.style.overflow = open ? 'hidden' : '';
                })
            );
        },
        syncUrl() {
            const url = new URL(location.href);
            if (this.query) url.searchParams.set('q', this.query); else url.searchParams.delete('q');
            if (this.statusFilter) url.searchParams.set('status', this.statusFilter); else url.searchParams.delete('status');
            if (this.severityFilter) url.searchParams.set('severity', this.severityFilter); else url.searchParams.delete('severity');
            if (this.roleFilter) url.searchParams.set('role', this.roleFilter); else url.searchParams.delete('role');
            if (this.overdueOnly) url.searchParams.set('overdue', '1'); else url.searchParams.delete('overdue');
            if (this.dueSoonOnly) url.searchParams.set('dueSoon', '1'); else url.searchParams.delete('dueSoon');
            if (this.dueTodayOnly) url.searchParams.set('today', '1'); else url.searchParams.delete('today');
            if (this.myOnly) url.searchParams.set('my', '1'); else url.searchParams.delete('my');
            if (this.unassignedOnly) url.searchParams.set('unassigned', '1'); else url.searchParams.delete('unassigned');
            if (this.unreadOnly) url.searchParams.set('unread', '1'); else url.searchParams.delete('unread');
            if (this.mutedOnly) url.searchParams.set('muted', '1'); else url.searchParams.delete('muted');
            if (this.kindFilter) url.searchParams.set('kind', this.kindFilter); else url.searchParams.delete('kind');
            if (this.codeFilter) url.searchParams.set('code', this.codeFilter); else url.searchParams.delete('code');
            if (this.groupBy) url.searchParams.set('group', this.groupBy); else url.searchParams.delete('group');
            if (this.evGroup) url.searchParams.set('eg', this.evGroup); else url.searchParams.delete('eg');
            if (this.evDay) url.searchParams.set('day', this.evDay); else url.searchParams.delete('day');
            if (this.evActor) url.searchParams.set('ea', this.evActor); else url.searchParams.delete('ea');
            if (this.evActorName) url.searchParams.set('an', this.evActorName); else url.searchParams.delete('an');
            if (this.evWeekday) url.searchParams.set('wd', this.evWeekday); else url.searchParams.delete('wd');
            if (this.evHour !== '') url.searchParams.set('hr', this.evHour); else url.searchParams.delete('hr');
            history.replaceState(null, '', url);
        },
        item() {
            return GROUPS.flatMap(g => g.items).find(i => i.key === this.section) || {label:this.section};
        },
        groupOf(key) {
            const g = GROUPS.find(g => g.items.some(i => i.key === key));
            return g ? this.gLabel(g.label) : '';
        },
        t(s) { return this.lang === 'en' ? (T_EN[s] || s) : s; },
        iLabel(i) { return this.lang === 'en' ? (ITEM_EN[i.key] || i.label) : i.label; },
        gLabel(l) { return this.lang === 'en' ? (GROUP_EN[l] || l) : l; },
        kLabel(m) { return this.lang === 'en' ? (KPI_EN[m.key] || m.label) : m.label; },
        nKind(k) { return this.lang === 'en' ? (NOTIF_EN[k] || k) : (this.NOTIF_KIND[k] || k); },
        setLang(l) { this.lang = l; try { localStorage.setItem('af_lang', l); } catch (e) {} document.documentElement.lang = l; },
        title() { return this.iLabel(this.item()); },
        togglePin(key) {
            const was = this.pins.includes(key);
            this.pins = was ? this.pins.filter(k => k !== key) : [...this.pins, key];
            try { localStorage.setItem('af_pins', JSON.stringify(this.pins)); } catch (e) {}
            this.toast(was ? this.t('Aus Favoriten entfernt') : this.t('Zu Favoriten hinzugefügt'));
        },
        sectionLabel(k) { const i = this.groups.flatMap(g => g.items).find(x => x.key === k); return i ? this.iLabel(i) : k; },
        recentRows() { this._rrTick = this._rrTick || 0; try { return JSON.parse(localStorage.getItem('af_recentrows') || '[]'); } catch (e) { return []; } },
        pushRecentRow(d) {
            const name = d.name || d.title || d.headline || d.subject || d.order_no || d.file_name || d.email || String(d.id).slice(0, 8);
            const list = this.recentRows().filter(r => !(r.key === this.section && String(r.id) === String(d.id)));
            list.unshift({key: this.section, id: d.id, name: name});
            try { localStorage.setItem('af_recentrows', JSON.stringify(list.slice(0, 8))); } catch (e) {}
            this._rrTick = (this._rrTick || 0) + 1;
        },
        removeRecentRow(rr) {
            const list = this.recentRows().filter(r => !(r.key === rr.key && String(r.id) === String(rr.id)));
            try { localStorage.setItem('af_recentrows', JSON.stringify(list)); } catch (e) {}
            this._rrTick = (this._rrTick || 0) + 1;
        },
        clearRecentSection() {
            const list = this.recentRows().filter(r => r.key !== this.section);
            try { localStorage.setItem('af_recentrows', JSON.stringify(list)); } catch (e) {}
            this._rrTick = (this._rrTick || 0) + 1;
        },
        allCollapsed() { return this.groups.every(g => this.collapsed[g.label]); },
        toggleAllGroups() { const v = !this.allCollapsed(); this.groups.forEach(g => { this.collapsed[g.label] = v; }); this.saveCollapsed(); },
        toggleGroup(k) { this.collapsed[k] = !this.collapsed[k]; this.saveCollapsed(); },
        toggleGroupHeader(label) { this.collapsedGroups[label] = !this.collapsedGroups[label]; try { localStorage.setItem('af_gc_' + this.section, JSON.stringify(this.collapsedGroups)); } catch (e) {} },
        saveCollapsed() { try { localStorage.setItem('af_navgroups', JSON.stringify(this.collapsed)); } catch (e) {} },
        setDocTitle() {
            const od = (this.rows || []).filter(r => this.overdue(r)).length;
            document.title = (od ? '(' + od + ') ' : '') + this.title() + ' · ' + this.tenantName() + ' — ALLOCORE';
        },
        overdueSections() {
            return Object.entries(this.navBadges || {}).filter(([k, n]) => n > 0)
                .map(([k, n]) => ({key: k, label: this.sectionLabel(k), count: n}))
                .sort((a, b) => b.count - a.count);
        },
        todaySections() {
            return Object.entries(this.navBadgesToday || {}).filter(([k, n]) => n > 0)
                .map(([k, n]) => ({key: k, label: this.sectionLabel(k), count: n}))
                .sort((a, b) => b.count - a.count);
        },
        weekSections() {
            return Object.entries(this.navBadgesWeek || {}).filter(([k, n]) => n > 0)
                .map(([k, n]) => ({key: k, label: this.sectionLabel(k), count: n}))
                .sort((a, b) => b.count - a.count);
        },
        overdueTotal() { return this.navTotal?.overdue ?? Object.values(this.navBadges || {}).reduce((s, n) => s + n, 0); },
        todayTotal() { return this.navTotal?.today ?? Object.values(this.navBadgesToday || {}).reduce((s, n) => s + n, 0); },
        unreadNotifs() { return this.dbNotifs.filter(n => !n.read).length; },
        paletteItems() {
            const q = this.paletteQ.trim().toLowerCase();
            const all = this.visGroups().flatMap(g => g.items.map(i => ({key: i.key, label: this.iLabel(i), group: this.gLabel(g.label)})));
            const acts = [];
            if (this.canCreate()) acts.push({key: null, action: 'create', label: '+ ' + this.t('Neu') + ': ' + this.title(), group: this.t('Aktionen')});
            if (this.rows && this.section !== 'dashboard') acts.push({key: null, action: 'export', label: this.t('CSV exportieren'), group: 'Aktion'}, {key: null, action: 'exportJson', label: (this.lang==='en'?'Export JSON':'JSON exportieren'), group: this.t('Aktionen')}, {key: null, action: 'reload', label: (this.lang==='en'?'Reload list':'Liste neu laden'), group: this.t('Aktionen')}, {key: null, action: 'print', label: (this.lang==='en'?'Print list':'Liste drucken'), group: this.t('Aktionen')});
            if (this.rows && this.canImport()) acts.push({key: null, action: 'import', label: this.t('CSV importieren (i)').replace(' (i)',''), group: this.t('Aktionen')});
            acts.push({key: null, action: 'dark', label: this.t('Dunkel/Hell umschalten'), group: this.t('Aktionen')});
            acts.push({key: null, action: 'newtenant', label: '+ ' + this.t('Neuer Mandant'), group: this.t('Aktionen')});
            if (this.tenantList.length > 1) this.sortedTenants().filter(t => t.id !== this.tenant).forEach(t => acts.push({key: null, action: 'tenant', tenant: t.id, label: this.t('Mandant') + ': ' + t.name, group: this.t('Aktionen')}));
            if (this.rows) Object.keys(this.views()).forEach(vn => acts.push({key: null, action: 'view', view: vn, label: this.t('Ansichten') + ': ' + vn, group: this.t('Aktionen')}));
            if (this.rows && this.rows.length) {
                if (this.rows.some(r => this.dueKey(r))) acts.push(
                    {key: null, action: 'filter', filter: 'overdueOnly', label: this.t('Filter: Überfällig ') + (this.overdueOnly ? '(an)' : '(aus)'), group: 'Aktion'},
                    {key: null, action: 'filter', filter: 'dueSoonOnly', label: (this.lang==='en'?'Filter: ≤7 days ':this.t('Filter: ≤7 Tage ')) + (this.dueSoonOnly ? '(an)' : '(aus)'), group: 'Aktion'},
                    {key: null, action: 'filter', filter: 'dueTodayOnly', label: this.t('Filter: Überfällig').replace(this.t('Überfällig'),'' + this.t('Heute') + '') + ' ' + (this.dueTodayOnly ? '(an)' : '(aus)'), group: 'Aktion'});
                if (this.rows.some(r => 'assignee_id' in r || 'responsible_id' in r || 'owner_id' in r)) {
                    acts.push({key: null, action: 'filter', filter: 'myOnly', label: (this.lang==='en'?'Filter: Assigned to me ':this.t('Filter: Mir zugewiesen ')) + (this.myOnly ? '(an)' : '(aus)'), group: 'Aktion'});
                }
                if (this.rows.some(r => 'assignee_id' in r || 'responsible_id' in r || 'owner_id' in r || 'assigned_to' in r) && this.rows.some(r => !(r.assignee_id || r.responsible_id || r.owner_id || r.assigned_to))) {
                    acts.push({key: null, action: 'filter', filter: 'unassignedOnly', label: (this.lang==='en'?'Filter: Unassigned ':this.t('Filter: Ohne Verantwortlichen ')) + (this.unassignedOnly ? '(an)' : '(aus)'), group: 'Aktion'});
                }
                if (this.overdueOnly || this.dueSoonOnly || this.dueTodayOnly || this.myOnly || this.unassignedOnly || this.statusFilter || this.query) acts.push({key: null, action: 'filter', filter: '_reset', label: this.t('Filter zurücksetzen'), group: 'Aktion'});
            }
            this.recentRows().forEach(r => acts.push({key: null, action: 'openrow', row: r, label: '↻ ' + (r.name || r.id) + ' (' + this.sectionLabel(r.key) + ')', group: this.t('Zuletzt')}));
            if (q && this.rows && this.rows.length) this.rows.filter(r => JSON.stringify(r).toLowerCase().includes(q)).slice(0, 5).forEach(r => acts.push({key: null, action: 'openrowcur', id: r.id, label: '→ ' + (r.name || r.title || r.headline || r.id) + ' (' + this.title() + ')', group: this.t('Eintrag')}));
            const mods = q ? all.filter(i => i.label.toLowerCase().includes(q) || i.key.includes(q)) : all;
            mods.sort((a, b) => ((this.pins || []).includes(b.key) ? 1 : 0) - ((this.pins || []).includes(a.key) ? 1 : 0));
            const globals = this.globHits.map(h => ({key: null, action: 'gsearch', section: h.section, id: h.id, icon: this.icons[h.section], label: '⇉ ' + (h.label || h.id) + ' (' + this.sectionLabel(h.section) + ')', group: 'Global'}));
            return [...acts.filter(a => !q || a.label.toLowerCase().includes(q)), ...globals, ...mods];
        },
        paletteGo() {
            this.paletteRun(this.paletteItems()[this.palIdx] || this.paletteItems()[0]);
        },
        paletteRun(it) {
            if (!it) return;
            this.palette = false;
            if (it.action === 'create') { this.openCreate(); return; }
            if (it.action === 'export') { this.exportCsv(); return; }
            if (it.action === 'exportJson') { this.exportJson(); return; }
            if (it.action === 'reload') { this.loadSection(true); return; }
            if (it.action === 'import') { if (this.canImport()) this.showImport = true; return; }
            if (it.action === 'print') { window.print(); return; }
            if (it.action === 'dark') { this.toggleDark(); return; }
            if (it.action === 'newtenant') { this.createTenant(); return; }
            if (it.action === 'tenant') { this.tenant = it.tenant; this.loadSection(); this.loadNavBadges(); return; }
            if (it.action === 'view') { this.applyView(it.view); return; }
            if (it.action === 'openrow') { location.href = '/app/' + it.row.key + '?tenant=' + this.tenant + '&open=' + encodeURIComponent(it.row.id); return; }
            if (it.action === 'gsearch') { location.href = '/app/' + it.section + '?tenant=' + this.tenant + '&open=' + encodeURIComponent(it.id); return; }
            if (it.action === 'openrowcur') { const r = (this.rows || []).find(x => String(x.id) === String(it.id)); if (r) this.detail = r; return; }
            if (it.action === 'filter') {
                if (it.filter === '_reset') { this.query = ''; this.statusFilter = ''; this.severityFilter = ''; this.roleFilter = ''; this.kindFilter = ''; this.codeFilter = ''; this.unreadOnly = false; this.mutedOnly = false; this.evGroup = ''; this.evDay = ''; evActor = ''; evActorName = ''; evDay = ''; this.overdueOnly = this.dueSoonOnly = this.dueTodayOnly = this.myOnly = this.unassignedOnly = false; }
                else this[it.filter] = !this[it.filter];
                return;
            }
            location.href = '/app/' + it.key + (this.tenant ? '?tenant=' + this.tenant : '');
        },
        insightSection(code) { return ({tasks_overdue:'tasks',tasks_due_soon:'tasks',tasks_unassigned:'tasks',tasks_done_no_stamp:'tasks',deadlines_due_soon:'deadlines',deadlines_unassigned:'deadlines',inspections_due_soon:'inspections',inspections_unassigned:'inspections',fin_negative_liquidity:'financial-reports',fin_reports_missing:'financial-reports',machines_in_maintenance:'machines',orders_overdue:'production-orders',orders_due_soon:'production-orders',orders_unassigned:'production-orders',orders_no_machine:'production-orders',orders_machine_maintenance:'production-orders',orders_done_incomplete:'production-orders',orders_running_no_start:'production-orders',orders_done_no_finish:'production-orders',machines_idle:'machines',instructions_no_person:'instructions',projects_no_owner:'projects',investments_stale_value:'investments',investments_no_value:'investments',documents_no_category:'documents',expert_profiles_no_rate:'expert-profiles',persons_no_contact:'persons',leave_overlap:'leave-requests',leave_decided_no_stamp:'leave-requests',applications_no_price:'tenders',applications_no_proposal:'tenders',applications_stale:'tenders',audits_no_findings:'audits',audits_no_result:'audits',instructions_no_completed_at:'instructions',risk_assessments_no_person:'risk-assessments',risks_no_measures:'risk-assessments',ai_analyses_failed:'ai-analyses',tenders_no_budget:'tenders',machines_zero_capacity:'machines',inspections_no_person:'inspections',instructions_renewal_due:'instructions',data_objects_no_category:'data-objects',data_objects_empty:'data-objects',graph_edges_no_relation:'graph-edges',measures_no_project:'measures',projects_stalled:'projects',projects_done_incomplete:'projects',projects_no_strategy:'projects',questions_unassigned:'questions',questions_no_answers:'questions',questions_no_accepted:'questions',deadlines_no_subject:'deadlines',deadlines_completed_no_stamp:'deadlines',risk_reviews_overdue:'risk-assessments',risk_reviews_due_soon:'risk-assessments',measures_overdue:'measures',measures_due_soon:'measures',measures_unassigned:'measures',projects_overdue:'projects',projects_ending_soon:'projects',projects_unassigned:'projects',projects_no_measures:'projects',portfolios_no_investments:'portfolios',instructions_due_soon:'instructions',instructions_overdue:'instructions',instructions_unassigned:'instructions',compliance_rate_low:'instructions',high_risks_open:'risk-assessments',deadlines_overdue:'deadlines',tenders_open:'tenders',questions_open:'questions',questions_stale:'questions',graph_orphans:'graph-entities',tenders_deadline_soon:'tenders',tenders_overdue:'tenders',tenders_no_applications:'tenders',tenders_awarded_no_winner:'tenders',expert_profiles_incomplete:'expert-profiles',documents_no_version:'documents',persons_without_company:'persons',companies_no_persons:'companies',investments_drawdown:'investments',strategies_overdue:'strategies',strategies_no_projects:'strategies',strategies_ending_soon:'strategies',participations_capital_need:'participations',participations_exited_with_stake:'participations',inspections_overdue:'inspections',inspections_no_result:'inspections',leave_requests_pending:'leave-requests',leave_pending_stale:'leave-requests',leave_active_today:'leave-requests',audit_findings_overdue:'audit-findings',audit_findings_critical:'audit-findings',audit_findings_due_soon:'audit-findings',audit_findings_unassigned:'audit-findings',audits_starting_soon:'audits',audits_overdue:'audits',audits_unassigned:'audits',audits_no_auditor:'audits',op_instructions_draft:'operating-instructions',op_instructions_review:'operating-instructions',op_instructions_no_document:'operating-instructions',instructions_no_document:'instructions',members_never_logged_in:'users'})[code] || null; },
        insightFilter(code) { return ({tasks_overdue:'overdue=1',tasks_due_soon:'dueSoon=1',tasks_unassigned:'unassigned=1',tasks_done_no_stamp:'status=done',deadlines_due_soon:'dueSoon=1',deadlines_unassigned:'unassigned=1',inspections_due_soon:'dueSoon=1',inspections_unassigned:'unassigned=1',fin_negative_liquidity:'',fin_reports_missing:'',machines_in_maintenance:'status=maintenance',orders_overdue:'overdue=1',orders_due_soon:'dueSoon=1',orders_unassigned:'unassigned=1',orders_no_machine:'',orders_machine_maintenance:'',orders_done_incomplete:'status=done',orders_running_no_start:'status=running',orders_done_no_finish:'status=done',machines_idle:'status=active',instructions_no_person:'status=pending',projects_no_owner:'status=active',investments_stale_value:'',investments_no_value:'',documents_no_category:'',expert_profiles_no_rate:'status=active',persons_no_contact:'',leave_overlap:'status=approved',leave_decided_no_stamp:'status=approved',applications_no_price:'',applications_no_proposal:'',applications_stale:'status=submitted',audits_no_findings:'status=done',audits_no_result:'status=done',instructions_no_completed_at:'status=completed',risk_assessments_no_person:'status=open',risks_no_measures:'status=open',ai_analyses_failed:'status=failed',tenders_no_budget:'status=open',machines_zero_capacity:'status=active',inspections_no_person:'status=scheduled',instructions_renewal_due:'status=completed',data_objects_no_category:'',data_objects_empty:'',graph_edges_no_relation:'',measures_no_project:'',projects_stalled:'status=active',projects_done_incomplete:'status=done',projects_no_strategy:'status=active',questions_unassigned:'status=open',questions_no_answers:'',questions_no_accepted:'status=answered',deadlines_no_subject:'status=open',deadlines_completed_no_stamp:'status=completed',risk_reviews_overdue:'overdue=1',risk_reviews_due_soon:'dueSoon=1',compliance_rate_low:'',graph_orphans:'',leave_pending_stale:'status=pending',measures_overdue:'overdue=1',measures_due_soon:'dueSoon=1',measures_unassigned:'unassigned=1',projects_overdue:'overdue=1',projects_ending_soon:'dueSoon=1',projects_unassigned:'unassigned=1',projects_no_measures:'status=active',portfolios_no_investments:'',instructions_due_soon:'dueSoon=1',instructions_overdue:'overdue=1',instructions_unassigned:'unassigned=1',deadlines_overdue:'overdue=1',high_risks_open:'status=open',tenders_open:'status=open',questions_open:'status=open',questions_stale:'status=open',tenders_deadline_soon:'dueSoon=1',tenders_overdue:'overdue=1',tenders_no_applications:'status=open',tenders_awarded_no_winner:'status=awarded',expert_profiles_incomplete:'status=active',documents_no_version:'',persons_without_company:'',companies_no_persons:'',investments_drawdown:'',strategies_overdue:'overdue=1',strategies_no_projects:'status=active',strategies_ending_soon:'dueSoon=1',participations_capital_need:'status=active',participations_exited_with_stake:'status=exited',inspections_overdue:'overdue=1',inspections_no_result:'status=completed',leave_requests_pending:'status=pending',leave_active_today:'status=approved',audit_findings_overdue:'overdue=1',audit_findings_critical:'severity=critical',audit_findings_due_soon:'dueSoon=1',audit_findings_unassigned:'unassigned=1',audits_starting_soon:'status=planned',audits_overdue:'overdue=1',audits_unassigned:'unassigned=1',audits_no_auditor:'status=planned',op_instructions_draft:'status=draft',op_instructions_review:'status=active',op_instructions_no_document:'',instructions_no_document:'',members_never_logged_in:''})[code] || ''; },
        tenantName() { const t = this.tenantList.find(x => x.id === this.tenant); return t ? t.name : this.t('— kein Mandant —'); },
        sortedTenants() { return [...this.tenantList].sort((a, b) => String(a.name).localeCompare(String(b.name), 'de')); },
        execLabel(k) { const M = {companies:this.t('Unternehmen'),persons:'Personen',tasks_open:'Offene Aufgaben',deadlines_open:'Offene Fristen',high_risks:'Hohe Risiken',data_objects:'Data Lake',audits_open:'Audits offen',findings_open:'Festst. offen',leave_pending:this.t('Urlaub offen'),orders_open:this.t('Aufträge offen'),documents:'Dokumente',questions_open:'Offene Fragen',investments_active:'Investitionen',participations_active:'Beteiligungen'}; return M[k] || k; },
        createExecReport() {
            const title = prompt('Report-Titel', 'Executive Report ' + new Date().toLocaleDateString((this.lang==='en'?'en-GB':'de-DE')));
            if (!title) return;
            this.api('/api/v1/exec-reports', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({title})})
                .then(r => { if (r.ok) this.loadSection(); else this.toast(this.t('Report fehlgeschlagen (HTTP ')+r.status+')'); });
        },
        toggleReport(r) {
            const id = r.id;
            this.reportOpen = {...this.reportOpen, [id]: !this.reportOpen[id]};
            if (this.reportOpen[id] && !this.reportData[id]) {
                this.api('/api/v1/exec-reports/' + id).then(res => res.ok ? res.json() : null)
                    .then(d => { if (d) this.reportData = {...this.reportData, [id]: d}; });
            }
        },
        subtitle() {
            const base = (this.section === 'dashboard' ? this.t('Unternehmenssteuerung') : (this.lang === 'en' ? 'Module ' : this.t('Modul ')) + (this.iLabel(this.item())||this.section)) + ' · ' + this.tenantName();
            if (this.section === 'dashboard') return base + ' · ' + new Date().toLocaleDateString((this.lang==='en'?'en-GB':'de-DE'), {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'});
            if (this.section === 'executive') return base;
            if (this.rows && this.rows.length) {
                const od = this.rows.filter(r => this.overdue(r)).length;
                const td = this.rows.filter(r => this.dueToday(r)).length;
                return base + ' · ' + this.rows.length + ' ' + this.eintrag(this.rows.length) + (od ? ' · ' + od + ' ' + this.t('überfällig') : '') + (td ? ' · ' + td + ' ' + this.t('heute') : '');
            }
            return base;
        },
        metric(k) { const v = this.metrics && this.metrics[k]; return v ? parseFloat(v.value) : '–'; },
        kpiValue(m) { const v = this.metric(m.key); if (v === '–' || !m.money) return v; return v.toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {maximumFractionDigits: 0}) + ' €'; },
        trend(k) { const t = this.trends.find(x => x.metric === k); return t || {delta: null, direction: 'unknown'}; },
        sparkPath(k) {
            const v = this.spark[k] || [];
            if (v.length < 2) return '';
            const min = Math.min(...v), max = Math.max(...v), span = max - min || 1;
            return v.map((p, i) => (i ? 'L' : 'M') + (i * 96 / (v.length - 1)).toFixed(1) + ',' + (22 - (p - min) / span * 20).toFixed(1)).join(' ');
        },
        seedDemo() {
            this.seeding = true;
            this.api('/api/v1/demo-seed', {method: 'POST'})
                .then(r => { this.seeding = false; this.toast(r.ok ? this.t('Demo-Daten geladen.') : this.t('Demo-Seed fehlgeschlagen (HTTP ') + r.status + ')'); if (r.ok) this.loadSection(); })
                .catch(() => { this.seeding = false; this.toast(this.t('Demo-Seed fehlgeschlagen.')); });
        },
        completeDashTask(t) {
            const prev = t.status, id = t.id;
            this.api('/api/v1/tasks/' + id, {method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({status: 'done'})})
                .then(r => {
                    if (!r.ok) { this.toast(this.t('Fehler (HTTP ') + r.status + ')'); return; }
                    this.openTasks = this.openTasks.filter(x => String(x.id) !== String(id));
                    this.toast(this.t('Aufgabe erledigt.'), {label: this.t('Rückgängig'), fn: () => this.api('/api/v1/tasks/' + id, {method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({status: prev || 'open'})}).then(() => this.loadSection(true))});
                })
                .catch(() => this.toast(this.t('Fehler beim Speichern.')));
        },
        toast(msg, action) {
            const type = /fehlgeschlagen|abgelaufen|fehler/i.test(msg) ? 'error' : 'info';
            const t = {id: Date.now() + Math.random(), msg, action, type};
            this.toasts.push(t);
            if (action) { this._undo = {tid: t.id, fn: action.fn, label: action.label}; setTimeout(() => { if (this._undo && this._undo.tid === t.id) this._undo = null; }, 4500); }
            setTimeout(() => { this.toasts = this.toasts.filter(x => x.id !== t.id); }, 4500);
        },
        loadLoginHistory() {
            if (!this.me || !this.me.id) return;
            this.api('/api/v1/events?per_page=5&type=user.logged_in&subject_id=' + encodeURIComponent(this.me.id)).then(r => r.ok ? r.json() : {data: []})
                .then(d => { this.loginHistory = (d && d.data) || (Array.isArray(d) ? d : []); })
                .catch(() => {});
        },
        submitPassword() {
            this.pwErr = '';
            const profileChanged = this.me && (this.pwForm.name !== this.me.name || this.pwForm.email !== this.me.email);
            const pwChanged = !!this.pwForm.next;
            if (pwChanged && (!this.pwForm.current || this.pwForm.next !== this.pwForm.confirm)) { this.pwErr = this.t('Passwort-Felder unvollständig oder ungleich.'); return; }
            const done = (msg) => { this.toast(msg); this.pwOpen = false; if (pwChanged) setTimeout(() => location.reload(), 1200); };
            const fail = async (r) => { const d = await r.json().catch(() => null); this.pwErr = (d && d.message) ? d.message : this.t('Fehler ') + r.status; };
            const savePw = () => this.api('/api/v1/me/password', {method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({current_password: this.pwForm.current, password: this.pwForm.next, password_confirmation: this.pwForm.confirm})})
                .then(r => { if (r.ok) done(this.t('Passwort geändert — Seite wird neu geladen.')); else fail(r); })
                .catch(() => { this.pwErr = this.t('Netzwerkfehler.'); });
            if (profileChanged) {
                this.api('/api/v1/me', {method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({name: this.pwForm.name, email: this.pwForm.email})})
                    .then(r => { if (!r.ok) return fail(r); if (pwChanged) savePw(); else { this.me.name = this.pwForm.name; this.me.email = this.pwForm.email; done(this.t('Profil aktualisiert.')); } })
                    .catch(() => { this.pwErr = this.t('Netzwerkfehler.'); });
            } else if (pwChanged) savePw();
            else this.pwErr = this.t('Keine Änderung.');
        },
        deleteAccount() {
            if (!confirm(this.t(this.t('Eigenes Konto wirklich löschen? Nur möglich, wenn du in keinem Mandanten mehr Mitglied bist. Alle API-Tokens werden widerrufen.')))) return;
            this.api('/api/v1/me', {method: 'DELETE'})
                .then(async r => {
                    if (r.ok) { this.toast(this.t('Konto gelöscht.')); setTimeout(() => { location.href = '/login'; }, 1200); return; }
                    const d = await r.json().catch(() => null);
                    this.pwErr = (d && d.message) ? d.message : this.t('Fehler ') + r.status;
                })
                .catch(() => { this.pwErr = this.t('Netzwerkfehler.'); });
        },
        fetchTenantInfo() {
            if (!this.tenant) { this.tenantInfo = null; return; }
            this.api('/api/v1/tenant').then(r => r.ok ? r.json() : null).then(d => this.tenantInfo = d).catch(() => this.tenantInfo = null);
        },
        api(path, opts={}) {
            opts.headers = Object.assign({
                'Authorization': 'Bearer {{ $apiToken }}',
                'X-Tenant': this.tenant, 'Accept': 'application/json',
            }, opts.headers||{});
            return fetch(path, opts).then(r => {
                if ((r.status === 401 || r.status === 419) && !this._authRedirect) {
                    this._authRedirect = true;
                    this.toast(this.t('Sitzung abgelaufen — bitte neu anmelden.'));
                    setTimeout(() => { location.href = '/login'; }, 1400);
                }
                if (r.status === 400 && !this._tenantToast) {
                    this._tenantToast = true;
                    r.clone().json().then(d => {
                        if (d && /X-Tenant/.test(d.message || '')) {
                            this.toast(d.message + ' Auswahl zurückgesetzt.');
                            localStorage.removeItem('allocore.tenant');
                            const bad = this.tenant;
                            this.tenantList = this.tenantList.filter(t => t.id !== bad);
                            this.tenant = this.tenantList.length ? this.sortedTenants()[0].id : null;
                            if (this.tenant) { this.loadSection(); this.loadNavBadges(); }
                        }
                    }).catch(() => {});
                    setTimeout(() => { this._tenantToast = false; }, 3000);
                }
                if (r.status === 429 && !this._rlToast) {
                    this._rlToast = true;
                    const s = r.headers.get('Retry-After') || 60;
                    this.toast(this.t('Anfrage fehlgeschlagen — Rate-Limit erreicht, bitte ') + s + ' s warten.');
                    setTimeout(() => { this._rlToast = false; }, Math.min(s, 15) * 1000);
                }
                return r;
            });
        },
        loadNavBadges() {
            this.loadNotifications();
            const apply = pairs => {
                const total = pairs.find(([k]) => k === '_total');
                if (total) this.navTotal = {overdue: total[1][0], today: total[1][1], week: total[1][2] || 0};
                this.navBadges = Object.fromEntries(pairs.filter(([k]) => k !== '_total').map(([k, v]) => [k, v[0]]));
                this.navBadgesToday = Object.fromEntries(pairs.filter(([k]) => k !== '_total').map(([k, v]) => [k, v[1]]));
                this.navBadgesWeek = Object.fromEntries(pairs.filter(([k]) => k !== '_total').map(([k, v]) => [k, v[2] || 0]));
            };
            this.api('/api/v1/nav-counts?days=' + this.navWindow).then(r => {
                if (!r.ok) throw new Error('no agg');
                return r.json();
            }).then(agg => apply(Object.entries(agg))).catch(() => {
                const items = this.groups.flatMap(g => g.items).filter(i => i.ep);
                Promise.all(items.map(i =>
                    this.api(i.ep + '?per_page=200').then(r => r.ok ? r.json() : []).then(d => {
                        const rows = Array.isArray(d) ? d : (d.data || []);
                        return [i.key, [(rows||[]).filter(r => this.overdue(r)).length, (rows||[]).filter(r => this.dueToday(r)).length, (rows||[]).filter(r => this.dueSoon(r) && !this.dueToday(r)).length]];
                    }).catch(() => [i.key, [0, 0]])
                )).then(apply);
            });
        },
        loadMe() {
            if (!this.tenant) { this.me = null; return; }
            this.api('/api/v1/me').then(r => r.ok ? r.json() : null).then(d => { this.me = d; }).catch(() => {});
        },
        loadNotifications() {
            if (!this.tenant) { this.dbNotifs = []; return; }
            this.api('/api/v1/notifications?limit=10').then(r => r.ok ? r.json() : []).then(d => {
                const now = Date.now();
                this.dbNotifs = (d || []).map(n => ({
                    ...n, entity_id: n.entity_id || n.data?.entity_id || '',
                    rel: (() => { const m = Math.round((now - new Date(n.created_at).getTime()) / 60000); return m < 60 ? 'vor ' + m + ' Min' : m < 1440 ? 'vor ' + Math.round(m / 60) + ' Std' : 'vor ' + Math.round(m / 1440) + ' T'; })()
                }));
                this.navBadges['notifications'] = this.unreadNotifs();
            }).catch(() => {});
            this.api('/api/v1/notifications/kinds').then(r => r.ok ? r.json() : []).then(d => { this.notifKinds = d || []; }).catch(() => {});
            this.api('/api/v1/notifications/codes').then(r => r.ok ? r.json() : []).then(d => { this.notifCodes = d || []; }).catch(() => {});
            this.api('/api/v1/notifications/stats').then(r => r.ok ? r.json() : null).then(d => { this.notifStats = d || null; }).catch(() => {});
            this.api('/api/v1/notifications/unread-count').then(r => r.ok ? r.json() : null).then(d => {
                if (d && d.count !== undefined) this.navBadges['notifications'] = d.count;
            }).catch(() => {});
        },
        markNotifRead(n) {
            this.api('/api/v1/notifications/' + n.id + '/read', {method: 'POST'}).then(r => { if (r.ok) { n.read = true; this.navBadges['notifications'] = this.unreadNotifs(); } }).catch(() => {});
        },
        toggleNotifRead(n) {
            if (!n) return;
            const url = '/api/v1/notifications/' + n.id + (n.read ? '/unread' : '/read');
            this.api(url, {method: 'POST'}).then(r => { if (r.ok) { n.read = !n.read; this.navBadges['notifications'] = this.unreadNotifs(); this.loadSection(true); } }).catch(() => {});
        },
        dismissNotif(n) {
            this.api('/api/v1/notifications/' + n.id, {method: 'DELETE'}).then(r => { if (r.ok) { this.dbNotifs = this.dbNotifs.filter(x => x.id !== n.id); this.navBadges['notifications'] = this.unreadNotifs(); this.rows = (this.rows || []).filter(x => x.id !== n.id); if (this.detail && this.detail.id === n.id) this.detail = null; this.toast(this.t('Benachrichtigung entfernt')); } }).catch(() => {});
        },
        toggleMute(kind) {
            const cur = (this.me && this.me.muted_kinds) || [];
            const op = cur.includes(kind) ? {unmute: kind} : {mute: kind};
            this.api('/api/v1/me/notification-prefs', {method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(op)}).then(r => r.ok ? r.json() : null).then(d => {
                if (!d) return;
                this.me = {...this.me, muted_kinds: d.muted_kinds};
                const muted = !cur.includes(kind);
                (this.rows || []).forEach(n => { if (n.kind === kind) n.muted = muted; });
                (this.dbNotifs || []).forEach(n => { if (n.kind === kind) n.muted = muted; });
                if (this.detail && this.detail.kind === kind) this.detail = {...this.detail, muted};
                this.toast(muted ? 'Art "' + kind + '" stummgeschaltet' : 'Stummschaltung aufgehoben');
            }).catch(() => {});
        },
        deleteKind(kind) {
            this.confirmKindDel = false;
            this.api('/api/v1/notifications?kind=' + encodeURIComponent(kind), {method: 'DELETE'}).then(r => r.ok ? r.json() : null).then(d => {
                if (!d) return;
                const ids = new Set((this.rows || []).filter(n => n.kind === kind).map(n => n.id));
                this.dbNotifs = this.dbNotifs.filter(n => !ids.has(n.id));
                this.rows = (this.rows || []).filter(n => n.kind !== kind);
                this.detail = null;
                this.toast(d.deleted + ' Benachrichtigungen entfernt');
            }).catch(() => {});
        },
        deleteCode(code) {
            this.confirmCodeDel = false;
            this.api('/api/v1/notifications?code=' + encodeURIComponent(code), {method: 'DELETE'}).then(r => r.ok ? r.json() : null).then(d => {
                if (!d) return;
                const ids = new Set((this.rows || []).filter(n => n.code === code).map(n => n.id));
                this.dbNotifs = this.dbNotifs.filter(n => !ids.has(n.id));
                this.rows = (this.rows || []).filter(n => n.code !== code);
                this.detail = null;
                this.toast(d.deleted + ' Benachrichtigungen entfernt');
            }).catch(() => {});
        },
        deleteReadNotifs() {
            this.api('/api/v1/notifications/delete-read', {method: 'POST'}).then(r => r.ok ? r.json() : null).then(d => {
                if (!d) return;
                this.dbNotifs = this.dbNotifs.filter(n => !n.read);
                this.rows = (this.rows || []).filter(n => !n.read);
                if (this.detail && this.detail.read) this.detail = null;
                this.toast(d.deleted + ' gelesene Benachrichtigungen entfernt');
            }).catch(() => {});
        },
        markAllNotifsRead() {
            this.api('/api/v1/notifications/read-all', {method: 'POST'}).then(r => { if (r.ok) { this.dbNotifs.forEach(n => n.read = true); (this.rows || []).forEach(n => n.read = true); this.navBadges['notifications'] = this.unreadNotifs(); } }).catch(() => {});
        },
        async revokeAllTokens() {
            if (!confirm(this.t(this.t('Alle API-Token widerrufen? Der aktuell verwendete bleibt aktiv.')))) return;
            const r = await this.api('/api/v1/tokens', {method: 'DELETE'});
            const d = await r.json().catch(() => ({}));
            if (r.ok) { this.toast((d.deleted ?? 0) + this.t(' Token widerrufen')); this.loadSection(); }
            else this.toast(d.message || this.t('Fehler beim Widerrufen'), 'error');
        },

        loadSection(soft) {
            if (this.section === 'events' && this.evActorsKey !== this.tenant) {
                this.evActorsKey = this.tenant;
                this.api('/api/v1/events/actors').then(r => r.ok ? r.json() : {data: []}).then(d => this.evActors = d.data || []);
                this.api('/api/v1/events/types').then(r => r.ok ? r.json() : {data: []}).then(d => this.evTypeList = d.data || []);
            }
            if (!this.tenant) { this.rows = null; return; }
            if (this._loadedTenant !== this.tenant) { this.lookups = {}; this._loadedTenant = this.tenant; }
            this.loading = true; this.error = '';
            if (!soft) { this.limit = 100; this.statusFilter = ''; this.severityFilter = ''; this.roleFilter = ''; this.kindFilter = ''; this.codeFilter = ''; this.unreadOnly = false; this.mutedOnly = false; this.evGroup = ''; this.evDay = ''; evActor = ''; evActorName = ''; evDay = ''; this.tdType = ''; this.unassignedOnly = false; this.overdueOnly = false; this.dueSoonOnly = false; this.dueTodayOnly = false; this.myOnly = false; this.hiddenCols = this.loadColPrefs(); this.colPicker = false; this.selected = {}; this.groupBy = localStorage.getItem('af_group_' + this.section) || ''; try { this.collapsedGroups = JSON.parse(localStorage.getItem('af_gc_' + this.section) || '{}') || {}; } catch (e) { this.collapsedGroups = {}; } }
            try { const sp = JSON.parse(localStorage.getItem('af_sort_' + this.section) || 'null'); this.sortKey = sp ? sp.k : ''; this.sortAsc = sp ? sp.a : true; } catch (e) { this.sortKey = ''; this.sortAsc = true; }
            if (this._urlSort) { const m = this._urlSort.match(/^(.+?)(?::(asc|desc))?$/); this.sortKey = m[1]; this.sortAsc = m[2] !== 'desc'; this._urlSort = null; }
            const url = new URL(location.href); url.searchParams.set('tenant', this.tenant);
            history.replaceState(null,'',url);
            if (this.section === 'dashboard') {
                this.api('/api/v1/metrics').then(r => r.ok ? r.json() : (this.error='HTTP '+r.status, null))
                    .then(d => { this.metrics = d; this.loading = false; this.lastLoad = new Date(); });
                this.spark = {};
                KPI.forEach(m => {
                    this.api('/api/v1/metrics/' + m.key + '?recent=14').then(r => r.ok ? r.json() : [])
                        .then(d => { const v = (Array.isArray(d) ? d : (d.data || [])).map(x => x.value); if (v.length > 1) this.spark[m.key] = v; });
                });
                this.api('/api/v1/insights').then(r => r.ok ? r.json() : [])
                    .then(d => this.insights = d.filter(i => i.code !== 'all_clear')
                        .sort((a, b) => ({critical: 0, warning: 1, info: 2}[a.severity] ?? 3) - ({critical: 0, warning: 1, info: 2}[b.severity] ?? 3)));
                this.api('/api/v1/events').then(r => r.ok ? r.json() : [])
                    .then(d => this.events = (Array.isArray(d) ? d : (d.data || [])).slice(0, 15));
                this.api('/api/v1/events/summary?days=' + this.evWindow).then(r => r.ok ? r.json() : null)
                    .then(d => this.evSummary = d);
                this.api('/api/v1/analytics/trends').then(r => r.ok ? r.json() : [])
                    .then(d => this.trends = Array.isArray(d) ? d : (d.data || []));
                Promise.all([
                    this.api('/api/v1/deadlines').then(r => r.ok ? r.json() : []).then(d => (Array.isArray(d) ? d : (d.data || [])).filter(x => x.status !== 'completed' && x.due_at).map(x => ({sec: 'deadlines', id: x.id, title: x.title, due: x.due_at}))),
                    this.api('/api/v1/tasks').then(r => r.ok ? r.json() : []).then(d => (Array.isArray(d) ? d : (d.data || [])).filter(x => ['open','in_progress'].includes(String(x.status)) && x.due_at).map(x => ({sec: 'tasks', id: x.id, title: x.title, due: x.due_at}))),
                    this.api('/api/v1/inspections').then(r => r.ok ? r.json() : []).then(d => (Array.isArray(d) ? d : (d.data || [])).filter(x => x.status === 'scheduled' && x.scheduled_at).map(x => ({sec: 'inspections', id: x.id, title: x.title, due: x.scheduled_at}))),
                    this.api('/api/v1/audits').then(r => r.ok ? r.json() : []).then(d => (Array.isArray(d) ? d : (d.data || [])).filter(x => ['planned','in_progress'].includes(String(x.status)) && x.starts_on).map(x => ({sec: 'audits', id: x.id, title: x.title, due: x.starts_on}))),
                    this.api('/api/v1/audit-findings').then(r => r.ok ? r.json() : []).then(d => (Array.isArray(d) ? d : (d.data || [])).filter(x => ['open','in_progress'].includes(String(x.status)) && x.due_at).map(x => ({sec: 'audit-findings', id: x.id, title: x.title, due: x.due_at}))),
                ]).then(list => {
                    this.upcoming = list.flat().sort((a, b) => new Date(a.due) - new Date(b.due)).slice(0, 6);
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
                    if (!r.ok) { this.error = 'HTTP '+r.status+this.t(' — keine Berechtigung (executive.view)?'); this.exec = null; this.loading=false; return null; }
                    return r.json();
                }).then(d => { if (d) this.exec = d; this.loading = false; this.lastLoad = new Date(); });
                this.api('/api/v1/exec-reports').then(r => r.ok ? r.json() : [])
                    .then(d => this.execReports = Array.isArray(d) ? d : (d.data || []));
                return;
            }
            this.loadLookups();
            this.api(this.item().ep + '?per_page=200' + (this.section === 'tooldata' ? '&group=webhook' : '') + (this.section === 'events' && this.evGroup ? '&group=' + this.evGroup : '') + (this.section === 'events' && this.evDay ? '&day=' + this.evDay : '') + (this.section === 'events' && this.evActor ? '&actor=' + this.evActor : '' + (this.evActorName ? '&actor_name=' + encodeURIComponent(this.evActorName) : '')) + (this.section === 'events' && this.evWeekday ? '&weekday=' + this.evWeekday : '') + (this.section === 'events' && this.evHour !== '' ? '&hour=' + this.evHour : '') + (this.section === 'events' && this.query ? '&q=' + encodeURIComponent(this.query) : '') + (this.section === 'notifications' && this.query ? '&q=' + encodeURIComponent(this.query) : '')).then(r => {
                if (!r.ok) { const dom = this.permDom(this.section); this.error = r.status === 403 ? this.t('Keine Berechtigung (')+(dom ? dom+'.view' : 'Zugriff')+')' : 'HTTP '+r.status+' — Fehler beim Laden'; this.rows=[]; this.loading=false; return null; }
                return r.json();
            }).then(d => {
                if (d === null) return;
                let rows = Array.isArray(d) ? d : (d.data || []);
                if (this.section === 'events') rows.forEach(r => r.summary = this.eventSummary(r));
                if (this.section === 'tooldata') rows = rows.map(e => this.toolRow(e));
                this.rows = rows;
                this.rowsTotal = (d && typeof d.total === 'number') ? d.total : (rows||[]).length;
                this.navBadges = {...this.navBadges, [this.section]: (rows||[]).filter(r => this.overdue(r)).length};
                this.navBadgesToday = {...this.navBadgesToday, [this.section]: (rows||[]).filter(r => this.dueToday(r)).length};
                this.navBadgesWeek = {...this.navBadgesWeek, [this.section]: (rows||[]).filter(r => this.dueSoon(r) && !this.dueToday(r)).length};
                this.navTotal = {
                    overdue: Object.entries(this.navBadges).filter(([k]) => k !== 'notifications').reduce((s, [, n]) => s + n, 0),
                    today: Object.values(this.navBadgesToday).reduce((s, n) => s + n, 0),
                    week: Object.values(this.navBadgesWeek).reduce((s, n) => s + n, 0),
                };
                if ((rows||[]).length) {
                    const keys = Object.keys(rows[0]).filter(k => !HIDE.has(k) && typeof rows[0][k] !== 'object');
                    this.columns = keys.slice(0, 7);
                } else this.columns = [];
                if (this.section === 'tooldata') this.columns = this.tdColumns();
                const oid = new URLSearchParams(location.search).get('open');
                if (oid) { const r = rows.find(x => String(x.id) === oid); if (r) this.detail = r; }
                this.loading = false; this.lastLoad = new Date();
                if (this.rowsTotal > (rows||[]).length && (rows||[]).length === 200) {
                    const sec = this.section;
                    const loadRest = (page) => {
                        if (this.section !== sec) return;
                        this.api(this.item().ep + '?per_page=200&page=' + page + (this.section === 'tooldata' ? '&group=webhook' : '') + (this.section === 'events' && this.evGroup ? '&group=' + this.evGroup : '') + (this.section === 'events' && this.evDay ? '&day=' + this.evDay : '') + (this.section === 'events' && this.evActor ? '&actor=' + this.evActor : '' + (this.evActorName ? '&actor_name=' + encodeURIComponent(this.evActorName) : '')) + (this.section === 'events' && this.evWeekday ? '&weekday=' + this.evWeekday : '') + (this.section === 'events' && this.evHour !== '' ? '&hour=' + this.evHour : '') + (this.section === 'events' && this.query ? '&q=' + encodeURIComponent(this.query) : '') + (this.section === 'notifications' && this.query ? '&q=' + encodeURIComponent(this.query) : '')).then(r => r.ok ? r.json() : null).then(d2 => {
                            if (!d2 || this.section !== sec) return;
                            let more = Array.isArray(d2) ? d2 : (d2.data || []);
                            if (!more.length) return;
                            if (this.section === 'tooldata') more = more.map(e => this.toolRow(e));
                            this.rows = this.rows.concat(more);
                            if (!this.detail) {
                                const o2 = new URLSearchParams(location.search).get('open');
                                const hit = o2 && this.rows.find(x => String(x.id) === o2);
                                if (hit) this.detail = hit;
                            }
                            if (this.rows.length < this.rowsTotal) loadRest(page + 1);
                        });
                    };
                    loadRest(2);
                }
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
                const g = this.groupBy === '__period' ? this.periodKey(r.updated_at) : String(r[this.groupBy] ?? '');
                if (!buckets.has(g)) buckets.set(g, []);
                buckets.get(g).push(r);
            });
            const out = []; let i = 0;
            for (const [label, rs] of buckets) {
                const disp = this.groupBy === '__period' ? label : ((this.groupBy === 'status' || this.groupBy === 'severity' || this.groupBy === 'risk_level') ? (label ? this.statusLabel(label) : this.t('Ohne Status')) : (/_id$/.test(this.groupBy) ? (label ? (this.resolveId(this.groupBy, label) || label) : this.t('Nicht zugewiesen')) : (label || '—')));
                out.push({t: 'h', label, disp, count: rs.length, overdue: rs.filter(r => this.overdue(r)).length});
                if (!this.collapsedGroups[label]) rs.forEach(r => out.push(mk(r, i++)));
                else i += rs.length;
            }
            return out;
        },
        sorted(rows) {
            const k = this.sortKey || 'updated_at', dir = (this.sortKey ? this.sortAsc : false) ? 1 : -1;
            if (!(rows||[]).length || rows[0][k] === undefined) return rows;
            return [...rows].sort((a,b) => {
                const x = a[k], y = b[k];
                if (x === y) return 0;
                if (x === null || x === undefined) return 1;
                if (y === null || y === undefined) return -1;
                return (typeof x === 'number' && typeof y === 'number' ? x - y : String(x).localeCompare(String(y), 'de')) * dir;
            });
        },
        sectionActions() {
            if (!this.canManage()) return [];
            const A = {
                'leave-requests': [['Genehmigen','approved'],['Ablehnen','rejected']],
                'tasks': [[this.t('Starten'),'in_progress'],['Erledigen','done'],[this.t('Absagen'),'cancelled'],[this.t('Wieder öffnen'),'open']],
                'instructions': [['Erledigt','completed']],
                'inspections': [[this.t('Abschließen'),'completed'],[this.t('Absagen'),'cancelled']],
                'deadlines': [['Erledigt','completed']],
                'tenders': [[this.t('Schließen'),'closed']],
                'questions': [[this.t('Schließen'),'closed']],
                'projects': [['Aktivieren','active'],[this.t('Abschließen'),'done'],[this.t('Absagen'),'cancelled']],
                'measures': [[this.t('Starten'),'in_progress'],['Erledigt','done'],[this.t('Absagen'),'cancelled']],
                'production-orders': [[this.t('Starten'),'running'],['Fertig','done'],[this.t('Absagen'),'cancelled']],
                'risk-assessments': [[this.t('Akzeptieren'),'accepted'],[this.t('Gemindert'),'mitigated']],
                'strategies': [['Aktivieren','active'],['Archivieren','archived']],
                'machines': [['In Wartung','maintenance'],['Aktivieren','active'],[this.t('Ausmustern'),'retired']],
                'operating-instructions': [['Aktivieren','active'],['Archivieren','archived']],
                'participations': [[this.t('Als Kandidat'),'candidate'],['Aktivieren','active'],[this.t('Ausgestiegen'),'exited']],
                'expert-profiles': [['Aktivieren','active'],['Deaktivieren','inactive']],
                'audits': [[this.t('Starten'),'in_progress'],[this.t('Abschließen'),'done'],[this.t('Absagen'),'cancelled']],
                'audit-findings': [['In Bearbeitung','in_progress'],[this.t('Gelöst'),'resolved'],['Akzeptiert','accepted'],[this.t('Wieder öffnen'),'open']],
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
            const prev = row.status;
            const payload = {status: s};
            const stamp = {completed: 'completed_at', approved: 'approved_at', cancelled: 'cancelled_at'}[s];
            if (stamp && stamp in row && !row[stamp]) payload[stamp] = new Date().toISOString().slice(0, 19).replace('T', ' ');
            if (s === 'approved' && 'approved_by' in row && !row.approved_by) payload.approved_by = this.meId;
            this.api(this.item().ep + '/' + row.id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)})
                .then(r => {
                    if (!r.ok) { this.toast(this.t('Aktion fehlgeschlagen (HTTP ')+r.status+')'); return; }
                    this.loadSection(true);
                    this.toast(this.statusLabel(s) + '.', {label: this.t('Rückgängig'), fn: () => {
                        this.api(this.item().ep + '/' + row.id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status: prev})}).then(() => this.loadSection(true));
                    }});
                });
        },
        applyStatus(s) {
            const prev = this.detail.status, id = this.detail.id;
            const payload = {status: s};
            const stamp = {completed: 'completed_at', approved: 'approved_at', cancelled: 'cancelled_at'}[s];
            if (stamp && stamp in this.detail && !this.detail[stamp]) payload[stamp] = new Date().toISOString().slice(0, 19).replace('T', ' ');
            if (s === 'approved' && 'approved_by' in this.detail && !this.detail.approved_by) payload.approved_by = this.meId;
            this.api(this.item().ep + '/' + id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)})
                .then(r => {
                    if (!r.ok) { this.toast(this.t('Aktion fehlgeschlagen (HTTP ')+r.status+')'); return; }
                    this.detail = null; this.loadSection();
                    this.toast(this.statusLabel(s) + '.', {label: this.t('Rückgängig'), fn: () => {
                        this.api(this.item().ep + '/' + id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status: prev})}).then(() => this.loadSection());
                    }});
                });
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
                .then(r => { if (r.ok) { this.appForm = {expert_profile_id: '', proposal: '', price: ''}; this.loadApps(this.detail.id); this.loadSection(); this.toast(this.t('Bewerbung eingereicht.')); } else this.toast(this.t('Bewerbung fehlgeschlagen (HTTP ')+r.status+')'); });
        },
        setAppStatus(id, s) {
            this.api('/api/v1/tender-applications/' + id, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:s})})
                .then(r => { if (r.ok) { this.loadApps(this.detail.id); this.loadSection(); this.toast(this.statusLabel(s) + '.'); } else this.toast(this.t('Aktion fehlgeschlagen (HTTP ')+r.status+')'); });
        },
        deleteApp(id) {
            if (!confirm(this.t(this.t('Bewerbung löschen?')))) return;
            const row = (this.apps || []).find(a => String(a.id) === String(id));
            this.api('/api/v1/tender-applications/' + id, {method:'DELETE'})
                .then(() => { this.loadApps(this.detail.id); this.toast(this.t('Bewerbung gelöscht.'), row ? {label: this.t('Rückgängig'), fn: () => {
                    const {id: _i, created_at: _c, updated_at: _u, tenant_id: _t, tender_id, ...rest} = row;
                    return this.api('/api/v1/tenders/' + tender_id + '/applications', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(rest)}).then(() => this.loadApps(this.detail.id));
                }} : undefined); });
        },
        loadRowEvents(id) {
            this.api('/api/v1/events?per_page=100&subject_id=' + encodeURIComponent(id)).then(r => r.ok ? r.json() : {data: []})
                .then(d => { const es = (d.data || d || []); this.rowEvents = es.filter(e => e.event_properties && e.event_properties.subject && String(e.event_properties.subject.id) === String(id)).slice(0, 25); })
                .catch(() => this.rowEvents = []);
        },
        loadRowNotifs(id) {
            this.api('/api/v1/notifications?entity_id=' + encodeURIComponent(id)).then(r => r.ok ? r.json() : [])
                .then(d => { this.rowNotifs = Array.isArray(d) ? d : (d.data || []); })
                .catch(() => this.rowNotifs = []);
        },
        loadUserRoles(id) {
            this.api('/api/v1/roles').then(r => r.ok ? r.json() : []).then(d => { this.allRoles = Array.isArray(d) ? d : (d.data || []); });
            this.api('/api/v1/users/' + id + '/roles').then(r => r.ok ? r.json() : {roles: []}).then(d => { this.userRoles = d.roles || []; this.userPerms = d.permissions || []; });
        },
        saveUserRoles() {
            if (!this.detail) return;
            this.api('/api/v1/users/' + this.detail.id + '/roles', {method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify({roles: this.userRoles})})
                .then(r => { this.toast(r.ok ? this.t('Rollen gespeichert.') : this.t('Speichern fehlgeschlagen (HTTP ') + r.status + ')'); if (r.ok) this.loadUserRoles(this.detail.id); });
        },
        openRoleEdit(r) {
            this.roleEdit = this.roleEdit === r.id ? null : r.id;
            if (this.roleEdit === r.id) {
                this.rolePerms = (r.permissions || []).map(p => p.name);
                this.api('/api/v1/permissions').then(res => res.ok ? res.json() : []).then(d => { this.allPerms = Array.isArray(d) ? d : []; });
            }
        },
        createRole() {
            const name = this.newRole.trim();
            if (!name) return;
            this.api('/api/v1/roles', {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({name, permissions: this.rolePerms})})
                .then(async res => {
                    if (!res.ok) { const d = await res.json().catch(() => ({})); this.toast(d.message || this.t('Anlegen fehlgeschlagen (HTTP ') + res.status + ')'); return; }
                    this.newRole = ''; this.rolePerms = []; this.toast(this.t('Rolle angelegt.')); this.loadUserRoles(this.detail.id);
                });
        },
        deleteRole(r) {
            if (!confirm(this.t('Rolle „') + r.name + this.t('" löschen? Zugewiesene Nutzer verlieren diese Rolle.'))) return;
            this.api('/api/v1/roles/' + r.id, {method: 'DELETE'}).then(res => {
                this.toast(res.ok ? this.t('Rolle gelöscht.') : this.t('Löschen fehlgeschlagen (HTTP ') + res.status + ')');
                if (res.ok) { this.loadUserRoles(this.detail.id); this.loadMe(); }
            });
        },
        saveRolePerms(r) {
            this.api('/api/v1/roles/' + r.id, {method: 'PUT', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({permissions: this.rolePerms})})
                .then(res => {
                    this.toast(res.ok ? this.t('Rechte gespeichert.') : this.t('Speichern fehlgeschlagen (HTTP ') + res.status + ')');
                    if (res.ok) { this.roleEdit = null; this.loadUserRoles(this.detail.id); this.loadMe(); }
                });
        },
        removeMember() {
            if (!this.detail || !confirm((this.detail.name || this.t('Mitglied')) + this.t(' aus dem Mandanten entfernen?'))) return;
            this.api('/api/v1/users/' + this.detail.id, {method: 'DELETE'}).then(r => {
                this.toast(r.ok || r.status === 204 ? this.t('Mitglied entfernt.') : this.t('Entfernen fehlgeschlagen (HTTP ') + r.status + ')');
                if (r.ok || r.status === 204) { this.detail = null; this.loadSection(); }
            });
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
                .then(r => { if (r.ok) { this.answerText = ''; this.loadAnswers(this.detail.id); this.loadSection(); this.toast(this.t('Antwort gespeichert.')); } else this.toast(this.t('Antwort fehlgeschlagen (HTTP ')+r.status+')'); });
        },
        acceptAnswer(id) {
            this.api('/api/v1/answers/' + id + '/accept', {method:'POST'})
                .then(r => { if (r.ok) { this.loadAnswers(this.detail.id); this.loadSection(); this.toast(this.t('Antwort akzeptiert.')); } else this.toast(this.t('Aktion fehlgeschlagen (HTTP ')+r.status+')'); });
        },
        deleteAnswer(id) {
            if (!confirm(this.t(this.t('Antwort löschen?')))) return;
            const row = (this.answers || []).find(a => String(a.id) === String(id));
            this.api('/api/v1/answers/' + id, {method:'DELETE'})
                .then(() => { this.loadAnswers(this.detail.id); this.toast(this.t('Antwort gelöscht.'), row ? {label: this.t('Rückgängig'), fn: () =>
                    this.api('/api/v1/questions/' + this.detail.id + '/answers', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({body: row.body})}).then(() => this.loadAnswers(this.detail.id))
                } : undefined); });
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
        loadAuditFindings(id) {
            this.api('/api/v1/audit-findings?audit_id=' + id + '&per_page=200').then(r => r.ok ? r.json() : []).then(d => {
                const sevRank = {critical: 0, high: 1, medium: 2, low: 3};
                this.auditFindings = (Array.isArray(d) ? d : (d.data || [])).slice().sort((a, b) => (sevRank[a.severity] ?? 9) - (sevRank[b.severity] ?? 9));
            }).catch(() => this.auditFindings = []);
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
                .then(r => { if (r.ok) { this.$refs.versionFile.value = ''; this.loadDocVersions(this.detail.id); this.loadSection(); this.toast(this.t('Version hochgeladen.')); } else this.toast(this.t('Upload fehlgeschlagen (HTTP ')+r.status+')'); });
        },
        dlPath(row) {
            if (this.section === 'data-objects') return '/api/v1/data-objects/' + row.id + '/download';
            return '/api/v1/documents/' + row.id + '/download';
        },
        async downloadDoc(row) {
            const r = await this.api(this.dlPath(row));
            if (!r.ok) { this.toast(this.t('Download fehlgeschlagen')); return; }
            const blob = await r.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            const dispo = r.headers.get('Content-Disposition') || '';
            const m = dispo.match(/filename="?([^";]+)/);
            a.download = m ? m[1] : (row.title || 'document');
            a.click();
            URL.revokeObjectURL(a.href);
        },
        createTenant() {
            this.tenantMode = 'create'; this.tenantName = ''; this.tenantModal = true;
        },
        renameTenant() {
            const cur = this.tenantList.find(t => t.id === this.tenant);
            this.tenantMode = 'rename'; this.tenantName = cur ? cur.name : ''; this.tenantModal = true;
        },
        async submitTenant() {
            const name = this.tenantName.trim();
            if (!name) return;
            if (this.tenantMode === 'rename') {
                const cur = this.tenantList.find(t => t.id === this.tenant);
                if (name === (cur ? cur.name : '')) { this.tenantModal = false; return; }
                const r = await this.api('/api/v1/tenant', {method:'PUT', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({name})});
                if (!r.ok) { this.toast(this.t('Umbenennen fehlgeschlagen (HTTP ')+r.status+')'); return; }
                if (cur) cur.name = name;
                this.toast(this.t('Mandant umbenannt: ') + name);
            } else {
                const r = await fetch('/api/v1/tenants', {method:'POST', headers:{
                    'Authorization': 'Bearer {{ $apiToken }}', 'Accept':'application/json',
                    'Content-Type':'application/json'},
                    body: JSON.stringify({name})});
                if (!r.ok) { this.toast(this.t('Anlegen fehlgeschlagen (HTTP ')+r.status+')'); return; }
                const t = await r.json();
                this.tenantList.push({id: t.id, name: t.name});
                this.tenant = t.id;
                this.toast(this.t('Mandant angelegt: ') + t.name);
                this.loadSection();
                this.loadNavBadges();
            }
            this.tenantModal = false;
        },
        async leaveTenant() {
            const cur = this.tenantList.find(t => t.id === this.tenant);
            if (!confirm(this.t('Mandant „') + (cur ? cur.name : '') + '" wirklich verlassen? Du verlierst alle Rollen und Zugriff.')) return;
            const r = await this.api('/api/v1/me/membership', {method:'DELETE'});
            if (!r.ok) { const d = await r.json().catch(() => null); this.toast((d && d.message) ? d.message : this.t('Verlassen fehlgeschlagen (HTTP ')+r.status+')', 'error'); return; }
            this.toast(this.t('Mandant verlassen.'));
            localStorage.removeItem('af_last_tenant');
            this.tenantList = this.tenantList.filter(t => t.id !== this.tenant);
            this.tenant = this.tenantList.length ? this.tenantList[0].id : '';
            location.reload();
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
            if (this.dueTodayOnly) rs = rs.filter(r => this.dueToday(r));
            if (this.myOnly) rs = rs.filter(r => String(r.assignee_id || r.responsible_id || r.owner_id || '') === String(this.meId));
            if (this.unassignedOnly) rs = rs.filter(r => !(r.assignee_id || r.responsible_id || r.owner_id || r.assigned_to));
            if (this.statusFilter) rs = rs.filter(r => String(r.status || '') === this.statusFilter);
            if (this.severityFilter) rs = rs.filter(r => String(r.severity || '') === this.severityFilter);
            if (this.roleFilter) rs = rs.filter(r => (r.role_names || []).includes(this.roleFilter));
            if (this.evGroup) rs = rs.filter(r => this.eventGroup(r.event_type) === this.evGroup);
            if (this.evDay) rs = rs.filter(r => (r.created_at || '').slice(0, 10) === this.evDay);
            if (this.section === 'tooldata' && this.tdType) rs = rs.filter(r => r._etype === this.tdType);
            if (this.kindFilter) rs = rs.filter(r => String(r.kind || '') === this.kindFilter);
            if (this.codeFilter) rs = rs.filter(r => String(r.code || '') === this.codeFilter);
            if (this.unreadOnly) rs = rs.filter(r => !r.read);
            if (this.mutedOnly) rs = rs.filter(r => r.muted);
            const q = this.query.trim().toLowerCase();
            if (!q) return rs;
            return rs.filter(r => Object.entries(r).some(([k, v]) => {
                if (String(v).toLowerCase().includes(q)) return true;
                if (v && typeof v === 'object' && JSON.stringify(v).toLowerCase().includes(q)) return true;
                const rn = this.resolveId(k, v);
                return rn && String(rn).toLowerCase().includes(q);
            }));
        },
        statusOpts() {
            if (!this.rows) return [];
            return [...new Set(this.rows.map(r => r.status).filter(Boolean))].sort();
        },
        roleOpts() {
            if (this.section !== 'users' || !this.rows) return [];
            return [...new Set(this.rows.flatMap(r => r.role_names || []))].sort();
        },
        severityOpts() {
            if (!this.rows || !this.rows.some(r => r.severity)) return [];
            return [...new Set(this.rows.map(r => r.severity).filter(Boolean))].sort();
        },
        roleLabel(n) { const M = {holding:'Holding',administrator:'Administrator',geschaeftsfuehrer:this.t('Geschäftsführer'),mitarbeiter:this.t('Mitarbeiter'),berater:this.t('Berater'),auditor:'Auditor',kunde:this.t('Kunde')}; return M[String(n).toLowerCase()] || n; },
        statusLabel(s) { return (this.lang === 'en' ? STATUS_EN : STATUS_DE)[String(s).toLowerCase()] || s; },
        typeLabel(t) { const M = {vacation:this.t('Urlaub'),sick:'Krank',other:this.t('Sonstiges'),question:this.t('Frage'),feedback:'Feedback',maintenance:'Wartung',safety:this.t('Sicherheit'),general:this.t('Allgemein'),external:'Extern',internal:'Intern',onboarding:this.t('Onboarding'),video:'Video',document:this.t('Dokument'),workshop:this.t('Workshop'),audit:this.t('Audit'),inspection:this.t('Prüfung'),training:this.t('Schulung'),financial:'Finanzen',quality:this.t('Qualität'),environment:this.t('Umwelt'),risk:'Risiko',strategic:'Strategisch',operational:this.t('Operativ'),low:this.t('Niedrig'),medium:this.t('Mittel'),high:this.t('Hoch'),critical:this.t('Kritisch'),warning:this.t('Warnung'),info:'Info',analysis:'Analyse'}; return M[String(t).toLowerCase()] || t; },
        insightKey(i) { return (this.tenant || '') + '|' + (i.code || '') + '|' + (i.message || ''); },
        visibleInsights() { return (this.insights || []).filter(i => !this.insDismissed.includes(this.insightKey(i))); },
        dismissInsight(i) {
            const key = this.insightKey(i);
            this.insDismissed.push(key);
            localStorage.setItem('af_insdismissed', JSON.stringify(this.insDismissed));
            this.toast(this.t('Hinweis ausgeblendet.'), {label: this.t('Rückgängig'), fn: () => {
                this.insDismissed = this.insDismissed.filter(k => k !== key);
                localStorage.setItem('af_insdismissed', JSON.stringify(this.insDismissed));
            }});
        },
        refSection(k) {
            const t = FKMAP[k];
            if (!t) return null;
            const key = ({expert_profiles: 'expert-profiles', graph_entities: 'graph-entities'})[t] || t;
            return GROUPS.flatMap(g => g.items).some(i => i.key === key) ? key : null;
        },
        fmtD(row, k) {
            const rn = this.resolveId(k, row[k]);
            if (rn) return rn;
            if (k === 'status' || k === 'risk_level') return this.statusLabel(row[k]);
            if (k === 'type') return this.typeLabel(row[k]);
            const v = row[k];
            if (typeof v === 'number' && k !== 'id' && !k.endsWith('_id')) {
                if (/price|amount|value|budget|revenue|ebitda|cashflow|liquidity|capital|cost|salary|hourly|invested|valuation/i.test(k)) return v.toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {maximumFractionDigits: 2}) + ' €';
                if (/pct|percent|progress|rate$|quote/i.test(k)) return v.toLocaleString((this.lang==='en'?'en-GB':'de-DE')) + ' %';
                return v.toLocaleString((this.lang==='en'?'en-GB':'de-DE'));
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
            if (/price|amount|value|budget|revenue|ebitda|cashflow|liquidity|capital|cost|salary|hourly|invested|valuation/i.test(c)) return 'Σ ' + sum.toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {maximumFractionDigits: 2}) + ' €';
            if (/pct|percent|progress|rate$|quote/i.test(c)) return 'Ø ' + (sum / this.filtered().length).toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {maximumFractionDigits: 1}) + ' %';
            return 'Σ ' + sum.toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {maximumFractionDigits: 2});
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
                return 'Σ ' + this.label(c) + ' ' + sum.toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {maximumFractionDigits: 2}) + (/price|amount|value|budget|revenue|ebitda|cashflow|liquidity|capital|cost|salary|hourly|invested|valuation/i.test(c) ? ' €' : '');
            }).join(' · ');
        },
        statusColor(v) {
            const map = {open:'#CA8A04',pending:'#CA8A04',in_progress:'#CA8A04',running:'#CA8A04',queued:'#5B6B7E',scheduled:'#5B6B7E',planned:'#5B6B7E',on_hold:'#CA8A04',warning:'#CA8A04',medium:'#CA8A04',critical:'#A6362E',high:'#A6362E',cancelled:'#A6362E',rejected:'#A6362E',overdue:'#A6362E',done:'#2E7D5B',completed:'#2E7D5B',approved:'#2E7D5B',accepted:'#2E7D5B',mitigated:'#2E7D5B',active:'#2E7D5B',awarded:'#2E7D5B',answered:'#2E7D5B',closed:'#2E7D5B',info:'#5B6B7E',low:'#2E7D5B',maintenance:'#CA8A04',todo:'#CA8A04',candidate:'#CA8A04',requested:'#CA8A04',review:'#CA8A04',retired:'#5B6B7E',exited:'#5B6B7E',inactive:'#5B6B7E',archived:'#5B6B7E',draft:'#5B6B7E',expired:'#A6362E',unpaid:'#A6362E',suspended:'#A6362E',declined:'#A6362E',paid:'#2E7D5B',sent:'#2E7D5B',assigned:'#2E7D5B',confirmed:'#2E7D5B'};
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
            const keys = Object.keys(this.detail || {}).filter(k => (k === 'id' || !HIDE.has(k)) && !(typeof this.detail[k] === 'object' && this.detail[k] !== null && this.detail[k + '_id'] !== undefined));
            const rank = k => {
                if (k === 'id') return 0;
                if (/^(title|name|subject|question|company|label|description)$/.test(k)) return 1;
                if (k === 'status' || k === 'severity' || k === 'type' || k === 'risk_level') return 2;
                if (k === 'created_at' || k === 'updated_at' || k === 'deleted_at') return 4;
                return 3;
            };
            return keys.sort((a, b) => rank(a) - rank(b));
        },
        fmt(v) {
            if (v === null || v === undefined) return '—';
            if (typeof v === 'boolean') return v ? 'Ja' : this.t('Nein');
            if (Array.isArray(v)) return v.length ? v.join(', ') : '—';
            if (typeof v === 'object') return Object.entries(v).map(([k2, v2]) => k2 + ': ' + (v2 === null || v2 === undefined ? '—' : (typeof v2 === 'object' ? JSON.stringify(v2) : String(v2)))).join(' · ');
            if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(v)) {
                const d = new Date(v);
                if (!isNaN(d)) return d.toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {dateStyle: 'medium', timeStyle: 'short'});
            }
            return String(v);
        },
        ago(ts) {
            if (!ts) return '';
            const s = (Date.now() - new Date(ts)) / 1000;
            const en = this.lang === 'en';
            if (s < 90) return en ? 'just now' : 'gerade eben';
            if (s < 3600) return (en ? '' : 'vor ') + Math.round(s / 60) + (en ? ' min ago' : ' Min.');
            if (s < 86400) return (en ? '' : 'vor ') + Math.round(s / 3600) + (en ? ' h ago' : ' Std.');
            return (en ? '' : 'vor ') + Math.round(s / 86400) + (en ? ' d ago' : ' T');
        },
        notifLink(n) {
            if (n && n.kind === 'hinweis') return '/app/dashboard?tenant=' + this.tenant + '&severity=critical';
            if (!n || !n.entity_id) return null;
            const M = {unterweisung:'instructions',pruefung:'inspections',frist:'deadlines',feststellung:'audit-findings',audit:'audits',massnahme:'measures',aufgabe:'tasks',gefaehrdungsbeurteilung:'risk-assessments',projekt:'projects',auftrag:'production-orders',ausschreibung:'tenders',antwort:'questions',frage:'questions',urlaub:'leave-requests',rollen:'users',unterweisung_wiederholung:'instructions'};
            return M[n.kind] ? '/app/' + M[n.kind] + '?tenant=' + this.tenant + '&open=' + n.entity_id : null;
        },
        eventLink(e) {
            const p = e && e.event_properties;
            if (!p || !p.subject || p.subject.id === undefined || p.subject.id === null) return null;
            const g = String(e.event_type || '').split('.')[0];
            const M = {task:'tasks',company:'companies',person:'persons',document:'documents',instruction:'instructions',inspection:'inspections',deadline:'deadlines',tender:'tenders',question:'questions',machine:'machines',production_order:'production-orders',financial_report:'financial-reports',leave_request:'leave-requests',participation:'participations',data_object:'data-objects',portfolio:'portfolios',investment:'investments',graph_entity:'graph-entities',graph_edge:'graph-edges',strategy:'strategies',project:'projects',measure:'measures',ai_analysis:'ai-analyses',risk_assessment:'risk-assessments',operating_instruction:'operating-instructions',audit:'audits',audit_finding:'audit-findings',expert_profile:'expert-profiles',tender_application:'tenders',answer:'questions',exec_report:'executive',user:'users'};
            return M[g] ? '/app/' + M[g] + '?tenant=' + this.tenant + '&open=' + p.subject.id : null;
        },
        eventGroup(t) {
            const g = String(t || '').split('.')[0];
            const v = {task: 'Aufgabe', company: this.t('Unternehmen'), person: 'Person', document: this.t('Dokument'), document_version: 'Dokumentversion', instruction: 'Unterweisung', inspection: this.t('Prüfung'), deadline: 'Frist', risk_assessment: 'Gefährdungsbeurteilung', operating_instruction: 'Betriebsanweisung', expert_profile: 'Experte', question: this.t('Frage'), answer: 'Antwort', tender: 'Ausschreibung', tender_application: 'Bewerbung', financial_report: 'Finanzbericht', leave_request: 'Urlaubsantrag', machine: 'Maschine', production_order: 'Produktionsauftrag', participation: 'Beteiligung', data_object: 'Data-Objekt', portfolio: 'Portfolio', investment: 'Investition', graph_entity: 'Entität', graph_edge: 'Kante', strategy: 'Strategie', project: 'Projekt', measure: 'Maßnahme', ai_analysis: 'KI-Analyse', exec_report: 'Executive-Report', audit: this.t('Audit'), audit_finding: 'Feststellung', user: 'Benutzer', role: 'Rolle', tenant: this.t('Mandant'), webhook: 'Webhook', connector: 'Konnektor', integration_source: 'Webhook-Quelle'}[g];
            return this.lang === 'en' ? (T_EN[v] || v) : v;
        },
        evTypeGroups() { const m = {}; (this.evTypeList || []).forEach(t => { const g = this.eventGroup(t.type); m[g] = (m[g] || 0) + t.events; }); return Object.entries(m).map(([g, events]) => ({g, events})).sort((a, b) => b.events - a.events); },
        eventLabel(t) {
            const a = String(t || '').split('.').pop();
            return {created: 'erstellt', updated: this.t('aktualisiert'), deleted: 'gelöscht', completed: 'abgeschlossen', approved: 'genehmigt', awarded: 'vergeben', uploaded: this.t('hochgeladen'), answered: 'beantwortet', created_event: 'erstellt', added: this.t('hinzugefügt'), roles_updated: this.t('Rollen geändert'), removed: this.t('entfernt'), left: 'verlassen', permissions_updated: this.t('Rechte geändert'), password_changed: this.t('Passwort geändert'), logged_in: 'angemeldet', logged_out: 'abgemeldet', pulled: 'abgerufen', received: 'empfangen',
                invoice_created: 'Rechnung erstellt', invoice_paid: 'Rechnung bezahlt', payment_received: 'Zahlung erhalten',
                expense_created: 'Ausgabe erfasst', customer_created: 'Kunde erstellt', lead_created: 'Lead erstellt',
                lead_qualified: 'Lead qualifiziert', order_created: 'Auftrag erstellt', order_done: 'Auftrag fertig',
                order_done_on_time: 'Auftrag pünktlich', order_complaint: 'Reklamation', order_lead_time: 'Durchlaufzeit',
                timeentry_billable: 'Fakturierbare Std.', website_visitors: 'Websitebesucher', offer_created: 'Angebot erstellt'}[a] || a;
        },
        eventSummary(e) {
            const p = (e && e.event_properties) || {};
            const b = (p.payload && p.payload.body) || p.payload || {};
            const parts = [];
            if (p.subject && p.subject.title && p.subject.type !== 'integration_source') parts.push(p.subject.title);
            const loc = this.lang === 'en' ? 'en-GB' : 'de-DE';
            for (const [k, v] of Object.entries(b)) {
                if (parts.length >= 4) break;
                if (v === null || v === undefined || v === '' || typeof v === 'object') continue;
                if (/_id$/.test(k) || /^(source_id|source_name|tenantId|tenant_id|currency|ip)$/.test(k)) continue;
                if (/amount|total|price|value|cost|sum/.test(k)) parts.push(Number(v).toLocaleString(loc, {maximumFractionDigits: 2}) + ' €');
                else if (/occurred|date|_at$/.test(k)) { const d = new Date(v); if (!isNaN(d)) parts.push(d.toLocaleDateString(loc)); }
                else if (typeof v === 'string' && v.length <= 60) parts.push(v);
                else if (typeof v === 'number') parts.push(String(v));
            }
            return parts.join(' · ');
        },
        toolRow(e) {
            const p = (e && e.event_properties) || {};
            const b = (p.payload && p.payload.body) || {};
            const out = {id: e.id, _etype: (e.event_type || '').split('.').pop(), aktion: this.eventLabel(e.event_type)};
            for (const [k, v] of Object.entries(b)) {
                if (v === null || v === undefined || v === '' || typeof v === 'object') continue;
                if (/^(source_id|source_name|tenantId|tenant_id|currency|ip)$/.test(k)) continue;
                out[k] = v;
            }
            if (!out.occurred_at && e.created_at) out.occurred_at = e.created_at;
            return out;
        },
        tdTypes() {
            const keys = [...new Set((this.rows || []).map(r => r._etype).filter(Boolean))];
            return keys.map(k => ({k, label: this.eventLabel('webhook.' + k), n: (this.rows || []).filter(r => r._etype === k).length})).sort((a, b) => b.n - a.n);
        },
        tdColumns() {
            const rs = (this.rows || []).filter(r => !this.tdType || r._etype === this.tdType);
            const keys = [...new Set(rs.flatMap(r => Object.keys(r)))].filter(k => !HIDE.has(k) && !k.startsWith('_'));
            const PR = ['aktion','number','customer','name','amount','occurred_at','hours','category','count','value','on_time','days'];
            return keys.sort((a, b) => {
                const ia = PR.indexOf(a), ib = PR.indexOf(b);
                return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib) || a.localeCompare(b);
            }).slice(0, 7);
        },
        createFields() {
            const SKIP = new Set([...HIDE, 'status', 'created_by', 'updated_by', 'completed_at', 'approved_at', 'approved_by', 'awarded_at', 'current_version', 'file_path', 'mime_type', 'size_bytes', 'role_names']);
            if (this.section === 'users') return [
                {key:'name', type:'text', req:true},
                {key:'email', type:'text', req:true},
                {key:'password', type:'text', req:false, hint:this.t('leer = zufällig generiert')},
            ];
            if (this.section === 'tokens') return [
                {key:'name', type:'text', req:true},
                {key:'expires_in_days', type:'number', req:false, hint:'leer = unbegrenzt'},
            ];
            if (this.section === 'integrations') return [
                {key:'name', type:'text', req:true},
            ];
            if (this.section === 'connectors') return [
                {key:'name', type:'text', req:true},
                {key:'url', type:'text', req:true},
                {key:'interval_minutes', type:'number', req:false, hint:'leer = 60'},
                {key:'active', type:'checkbox', req:false},
            ];
            const LONGTEXT = new Set(['description','content','notes','measures','bio','body','proposal','result','message','answer','question','summary','goal','scope','rationale','findings']);
            const src = (this.rows && this.rows[0]) || {};
            const ENUMS = {
                'leave-requests': {type: ['vacation','sick','other']},
                'risk-assessments': {risk_level: ['low','medium','high']},
                'audits': {type: ['internal','external'], status: ['planned','in_progress','done','cancelled']},
                'audit-findings': {severity: ['low','medium','high','critical'], status: ['open','in_progress','resolved','accepted']},
            };

            const enums = ENUMS[this.section] || {};
            const DEFS = {
                tasks: ['title','description','assignee_id','due_at','status'],
                instructions: ['title','content','person_id','responsible_id','document_id','due_at'],
                inspections: ['title','subject','type','person_id','responsible_id','scheduled_at'],
                deadlines: ['title','description','responsible_id','due_at'],
                'risk-assessments': ['title','area','hazard','measures','person_id','risk_level','review_at'],
                'operating-instructions': ['title','content','document_id','valid_from'],
                audits: ['title','type','auditor','company_id','responsible_id','starts_on','ends_on'],
                'audit-findings': ['title','description','audit_id','severity','responsible_id','due_at'],
                companies: ['name','legal_form','street','zip','city','country'],
                persons: ['first_name','last_name','email','phone','type','company_id'],
                documents: ['title','category'],
                questions: ['title','body','category','expert_profile_id'],
                tenders: ['title','description','company_id','required_skills','budget','deadline_at'],
                answers: ['body'],
                'expert-profiles': ['person_id','headline','bio','skills','hourly_rate'],
                strategies: ['name','description','starts_at','ends_at','status'],
                projects: ['name','description','strategy_id','owner_id','starts_at','ends_at'],
                measures: ['title','description','project_id','responsible_id','due_at'],
                portfolios: ['name','type','currency'],
                investments: ['name','asset_class','portfolio_id','quantity','cost_basis','current_value','acquired_at'],
                participations: ['name','legal_form','company_id','stake_pct','invested_amount','current_valuation'],
                machines: ['name','type','capacity_units_per_day'],
                'production-orders': ['order_no','product','quantity','machine_id','assigned_to','due_at'],
                'leave-requests': ['person_id','type','starts_on','ends_on','note'],
                'financial-reports': ['company_id','period','revenue','ebitda','cashflow','liquidity'],
                'graph-entities': ['name','type'],
                'graph-edges': ['from_entity_id','relation','to_entity_id'],
                'data-objects': ['name','category'],
                events: ['type'],
            };
            const keys = Object.keys(src).length
                ? Object.keys(src).filter(k => !SKIP.has(k) && (!k.endsWith('_id') || FKMAP[k])).slice(0, 12)
                : (DEFS[this.section] || ['title', 'description']);
            return keys.map((k, i) => ({
                key: k,
                type: FKMAP[k] ? 'fk' : (enums[k] ? 'enum' : (typeof src[k] === 'boolean' ? 'checkbox' : (typeof src[k] === 'number' ? 'number' : (/_at$/.test(k) ? 'datetime-local' : (/_on$|_date$|valid_from$/.test(k) ? 'date' : (LONGTEXT.has(k) ? 'textarea' : 'text')))))),
                table: FKMAP[k] || null,
                opts: enums[k] || null,
                req: i === 0 || ['name', 'title'].includes(k),
            }));
        },
        fkOptions(table) {
            const m = this.lookups[table] || {};
            return Object.entries(m).sort((a,b) => String(a[1]).localeCompare(String(b[1]), 'de'));
        },
        hasPerm(p) { return !this.me || !Array.isArray(this.me.permissions) || this.me.permissions.includes(p); },
        permDom(key) {
            const M = {companies:'companies',persons:'persons',documents:'documents',tasks:'tasks',instructions:'compliance',inspections:'compliance',deadlines:'compliance','risk-assessments':'compliance','operating-instructions':'compliance','expert-profiles':'experts',questions:'experts',tenders:'experts',strategies:'projects',projects:'projects',measures:'projects',portfolios:'investments',investments:'investments',participations:'participations',machines:'production','production-orders':'production','leave-requests':'hr','financial-reports':'finance',audits:'audits','audit-findings':'audits','data-objects':'datalake','ai-analyses':'ai','graph-entities':'graph','graph-edges':'graph',users:'roles',events:'metrics',executive:'executive','exec-reports':'executive',integrations:'metrics',connectors:'metrics',tooldata:'metrics'};
            return M[key] || null;
        },
        managePerm() { return this.permDom(this.section); },
        canView(key) { if (key === 'users' || key === 'dashboard') return true; const p = this.permDom(key); return !p || this.hasPerm(p + '.view'); },
        canManage() { const p = this.managePerm(); return !p || this.hasPerm(p + '.manage'); },
        visGroups() { return this.groups.map(g => ({...g, items: g.items.filter(i => this.canView(i.key))})).filter(g => g.items.length); },
        evDays() {
            if (!this.evSummary) return [];
            const days = [];
            for (let i = this.evWindow - 1; i >= 0; i--) {
                const d = new Date(); d.setDate(d.getDate() - i);
                const key = d.toISOString().slice(0, 10);
                days.push({key, label: d.toLocaleDateString((this.lang==='en'?'en-GB':'de-DE'), {weekday: 'short', day: '2-digit'}), n: this.evSummary.per_day[key] || 0});
            }
            const max = Math.max(1, ...days.map(d => d.n));
            return days.map(d => ({...d, pct: Math.round(d.n / max * 100)}));
        },
        evGroups() { return this.evSummary ? Object.entries(this.evSummary.by_group || {}).slice(0, 10) : []; },
        evHours() {
            if (!this.evSummary || !this.evSummary.by_hour) return [];
            const hs = [];
            for (let h = 0; h < 24; h++) hs.push({h, n: this.evSummary.by_hour[h] || 0});
            const max = Math.max(1, ...hs.map(x => x.n));
            return hs.map(x => ({...x, pct: Math.round(x.n / max * 100)}));
        },
        evWeekdays() {
            if (!this.evSummary || !this.evSummary.by_weekday) return [];
            const L = this.lang === 'en' ? ['','Su','Mo','Tu','We','Th','Fr','Sa'] : ['','So','Mo','Di','Mi','Do','Fr','Sa'];
            const ws = [];
            for (let d = 2; d <= 7; d++) ws.push({d, n: this.evSummary.by_weekday[d] || 0});
            ws.push({d: 1, n: this.evSummary.by_weekday[1] || 0});
            const max = Math.max(1, ...ws.map(w => w.n));
            return ws.map(w => ({...w, label: L[w.d], pct: Math.round(w.n / max * 100)}));
        },
        evActorsTop() { return this.evSummary && this.evSummary.top_actors ? this.evSummary.top_actors.filter(a => a && a.id).slice(0, 3) : []; },
        evSubjectsTop() { return this.evSummary && this.evSummary.top_subjects ? this.evSummary.top_subjects.slice(0, 3) : []; },
        topSubjectLink(s) {
            const g = String(s && s.type || '').split('\\').pop().replace(/([a-z])([A-Z])/g, '$1_$2').toLowerCase();
            const M = {task:'tasks',company:'companies',person:'persons',document:'documents',instruction:'instructions',inspection:'inspections',deadline:'deadlines',tender:'tenders',question:'questions',machine:'machines',production_order:'production-orders',financial_report:'financial-reports',leave_request:'leave-requests',participation:'participations',data_object:'data-objects',portfolio:'portfolios',investment:'investments',graph_entity:'graph-entities',graph_edge:'graph-edges',strategy:'strategies',project:'projects',measure:'measures',ai_analysis:'ai-analyses',risk_assessment:'risk-assessments',operating_instruction:'operating-instructions',audit:'audits',audit_finding:'audit-findings',expert_profile:'expert-profiles',tender_application:'tenders',answer:'questions',exec_report:'executive',user:'users'};
            return M[g] && s.id ? '/app/' + M[g] + '?tenant=' + this.tenant + '&open=' + s.id : '/app/events?tenant=' + this.tenant + '&q=' + encodeURIComponent(s.title || '');
        },
        writable() { return !['events','ai-analyses','metrics','users','notifications','tooldata'].includes(this.section) && this.canManage(); },
        canEdit() { return this.writable() && !['data-objects','tokens'].includes(this.section); },
        canCreate() { return this.section === 'ai-analyses' ? this.hasPerm('ai.manage') : (this.section === 'users' ? this.hasPerm('roles.manage') : (this.section === 'tokens' ? true : this.writable())); },
        openCreate(prefill) {
            if (this.section === 'ai-analyses') {
                this.api('/api/v1/ai-analyses', {method:'POST', headers:{'Content-Type':'application/json'}, body:'{}'})
                    .then(r => { if (r.ok) this.loadSection(); else this.toast(this.t('Analyse fehlgeschlagen (HTTP ')+r.status+')'); });
                return;
            }
            this.editing = null; this.dupMode = false; this.form = prefill || {}; if (this.section === 'tokens') { this.form.abilities = this.form.abilities || []; if (!this.tokenAbilities.length) this.api('/api/v1/tokens/abilities').then(r => r.ok ? r.json() : []).then(d => { this.tokenAbilities = d; }); } if (this.section === 'users') { this.form.roles = this.form.roles || []; if (!this.allRoles.length) this.api('/api/v1/roles').then(r => r.ok ? r.json() : []).then(d => { this.allRoles = Array.isArray(d) ? d : (d.data || []); }); } this.formError = ''; this.formDirty = false; this.showCreate = true;
            this.$nextTick(() => { const el = document.querySelector('#createForm input, #createForm select'); if (el) el.focus(); });
        },
        views() {
            try { return JSON.parse(localStorage.getItem('af_views_' + this.section) || '{}'); } catch (e) { return {}; }
        },
        saveView() {
            const name = prompt('Name der Ansicht:');
            if (!name) return;
            const all = this.views();
            all[name] = {query: this.query, statusFilter: this.statusFilter, roleFilter: this.roleFilter, kindFilter: this.kindFilter, codeFilter: this.codeFilter, unreadOnly: this.unreadOnly, mutedOnly: this.mutedOnly, overdueOnly: this.overdueOnly, dueSoonOnly: this.dueSoonOnly, dueTodayOnly: this.dueTodayOnly, myOnly: this.myOnly, unassignedOnly: this.unassignedOnly, evGroup: this.evGroup, evActor: this.evActor, groupBy: this.groupBy, sortKey: this.sortKey, sortAsc: this.sortAsc, hiddenCols: this.hiddenCols};
            localStorage.setItem('af_views_' + this.section, JSON.stringify(all));
            this.viewPicker = false;
            this.toast(this.t('Ansicht „') + name + (' ' + this.t((' ' + this.t(this.t('“ gespeichert.'))))));
        },
        applyView(name) {
            const v = this.views()[name];
            if (!v) return;
            this.query = v.query || ''; this.statusFilter = v.statusFilter || ''; this.roleFilter = v.roleFilter || ''; this.kindFilter = v.kindFilter || ''; this.codeFilter = v.codeFilter || ''; this.unreadOnly = !!v.unreadOnly; this.mutedOnly = !!v.mutedOnly; this.overdueOnly = !!v.overdueOnly; this.dueSoonOnly = !!v.dueSoonOnly; this.dueTodayOnly = !!v.dueTodayOnly; this.myOnly = !!v.myOnly; this.unassignedOnly = !!v.unassignedOnly; this.evGroup = v.evGroup || ''; this.evActor = v.evActor || '';
            this.groupBy = v.groupBy || ''; this.sortKey = v.sortKey || ''; this.sortAsc = v.sortAsc !== false; this.hiddenCols = v.hiddenCols || {};
            this.viewPicker = false; this.toast(this.t('Ansicht „') + name + '“ angewendet.');
        },
        deleteView(name) {
            const all = this.views(); delete all[name];
            localStorage.setItem('af_views_' + this.section, JSON.stringify(all));
            this.viewPicker = false; this.viewPicker = true;
            this.toast(this.t('Ansicht „') + name + (' ' + this.t((' ' + this.t(this.t('“ gelöscht.'))))));
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
            if (!(rows||[]).length) return;
            const esc = v => '"' + String(v === null || v === undefined ? '' : v).replace(/"/g, '""') + '"';
            const csvVal = (r, c) => {
                let v = r[c];
                const rn = this.resolveId(c, v);
                if (rn) return rn;
                if (typeof v === 'boolean') return v ? 'Ja' : this.t('Nein');
                if (['status','severity','type'].includes(c) && v) return STATUS_DE[String(v).toLowerCase()] || v;
                if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(v)) return new Date(v).toLocaleDateString((this.lang==='en'?'en-GB':'de-DE'));
                return v;
            };
            const cols = this.visCols();
            const lines = [cols.map(c => esc(this.label(c))).join(';'), ...rows.map(r => cols.map(c => esc(csvVal(r, c))).join(';'))];
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['\ufeff' + lines.join('\n')], {type:'text/csv'}));
            a.download = this.exportName('csv');
            a.click();
            this.toast((rows||[]).length + ' ' + this.eintrag((rows||[]).length) + ' exportiert.');
        },
        exportJson(only) {
            const rows = only || this.sorted(this.filtered());
            if (!(rows||[]).length) return;
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([JSON.stringify(rows, null, 2)], {type: 'application/json'}));
            a.download = this.exportName('json');
            a.click();
            this.toast((rows||[]).length + ' ' + this.eintrag((rows||[]).length) + ' exportiert (JSON).');
        },
        async exportEvents() {
            let url = '/api/v1/events/export';
            const params = [];
            if (this.evGroup) params.push('group=' + encodeURIComponent(this.evGroup));
            if (this.evActor) params.push('actor=' + encodeURIComponent(this.evActor));
            if (params.length) url += '?' + params.join('&');
            const r = await this.api(url);
            if (!r.ok) { this.toast(this.t('Export fehlgeschlagen'), 'error'); return; }
            const blob = await r.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            const dispo = r.headers.get('Content-Disposition') || '';
            const m = dispo.match(/filename="?([^";]+)/);
            a.download = m ? m[1] : this.exportName('ndjson');
            a.click();
            URL.revokeObjectURL(a.href);
            this.toast(this.t('Ereignisse exportiert'));
        },
        async exportNotifs() {
            let url = '/api/v1/notifications/export';
            const params = [];
            if (this.unreadOnly) params.push('unread=1');
            if (this.mutedOnly) params.push('muted=1');
            if (this.kindFilter) params.push('kind=' + encodeURIComponent(this.kindFilter));
            if (this.codeFilter) params.push('code=' + encodeURIComponent(this.codeFilter));
            if (this.query) params.push('q=' + encodeURIComponent(this.query));
            if (params.length) url += '?' + params.join('&');
            const r = await this.api(url);
            if (!r.ok) { this.toast(this.t('Export fehlgeschlagen'), 'error'); return; }
            const blob = await r.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            const dispo = r.headers.get('Content-Disposition') || '';
            const m = dispo.match(/filename="?([^";]+)/);
            a.download = m ? m[1] : this.exportName('ndjson');
            a.click();
            URL.revokeObjectURL(a.href);
            this.toast(this.t('Benachrichtigungen exportiert'));
        },
        exportName(ext) {
            const t = this.tenantName().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'export';
            const d = new Date().toISOString().slice(0, 10);
            return 'allocore-' + this.section + '-' + t + '-' + d + '.' + ext;
        },
        canImport() { return this.writable() && !['documents','data-objects','ai-analyses','events','metrics'].includes(this.section); },
        async importCsv() {
            const text = this.importText.trim();
            if (!text) return;
            const lines = text.split(/\r?\n/).filter(l => l.trim());
            if (lines.length < 2) { this.importErr = true; this.importResult = this.t('Mindestens Kopfzeile + eine Datenzeile nötig.'); return; }
            const delim = lines[0].includes(';') ? ';' : ',';
            const splitLine = l => {
                const out = []; let cur = '', q = false;
                for (const ch of l) { if (ch === '"') { q = !q; continue; } if (ch === delim && !q) { out.push(cur); cur = ''; continue; } cur += ch; }
                out.push(cur); return out.map(s => s.trim());
            };
            const fields = this.createFields();
            const valid = new Set(fields.map(f => f.key));
            const byLabel = {};
            fields.forEach(f => { byLabel[this.label(f.key).toLowerCase()] = f.key; });
            const heads = splitLine(lines[0]).map(h => valid.has(h) ? h : (byLabel[h.toLowerCase()] || h));
            const unknown = heads.filter(h => !valid.has(h));
            if (unknown.length) { this.importErr = true; this.importResult = this.t('Unbekannte Spalten: ') + unknown.join(', '); return; }
            this.importing = true; this.importErr = false; this.importResult = ''; this.importProgress = '';
            let ok = 0, fail = 0; const total = lines.length - 1; let done = 0; const badRows = [];
            for (const l of lines.slice(1)) {
                const cells = splitLine(l); const body = {};
                heads.forEach((h, i) => { if (cells[i] !== undefined && cells[i] !== '') body[h] = cells[i]; });
                const r = await this.api(this.item().ep, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)}).catch(() => null);
                if (r && r.ok) ok++; else { fail++; badRows.push(done + 2); }
                this.importProgress = (++done) + '/' + total;
            }
            this.importing = false; this.importProgress = '';
            this.importErr = fail > 0;
            this.importResult = ok + ' importiert' + (fail ? ', ' + fail + ' fehlgeschlagen (Zeilen: ' + badRows.join(', ') + ')' : '') + '.';
            if (ok) this.loadSection();
        },
        importTemplate() {
            const ex = f => f.type === 'date' ? '2026-01-15' : f.type === 'datetime-local' ? '2026-01-15T10:00' : f.type === 'number' ? '0' : f.type === 'checkbox' ? '1' : f.type === 'fk' ? '' : 'Beispiel';
            const fields = this.createFields();
            return fields.map(f => f.key).join(';') + '\n' + fields.map(ex).join(';') + '\n';
        },
        openDuplicate() {
            this.editing = null;
            this.dupMode = true;
            const fields = this.createFields();
            this.form = {};
            fields.forEach(f => { let v = this.detail[f.key]; if (f.type === 'datetime-local' && v) v = String(v).replace(' ', 'T').slice(0, 16); this.form[f.key] = v === null ? '' : v; });
            this.formError = ''; this.formDirty = false; this.showCreate = true;
            this.$nextTick(() => { const el = document.querySelector('#createForm input, #createForm select'); if (el) el.focus(); });
        },
        openEdit() {
            this.dupMode = false;
            this.editing = this.detail;
            const fields = this.createFields();
            this.form = {};
            fields.forEach(f => { let v = this.editing[f.key]; if (f.type === 'datetime-local' && v) v = String(v).replace(' ', 'T').slice(0, 16); this.form[f.key] = v === null ? '' : v; });
            this.formError = ''; this.formDirty = false; this.showCreate = true;
            this.$nextTick(() => { const el = document.querySelector('#createForm input, #createForm select'); if (el) el.focus(); });
        },
        submitCreate(keepOpen) {
            this.formError = '';
            const body = {};
            this.createFields().forEach(f => {
                let v = this.form[f.key];
                if (f.type === 'datetime-local' && v) v = String(v).replace('T', ' ') + (String(v).length === 16 ? ':00' : '');
                if (v !== undefined && v !== '') body[f.key] = v;
            });
            if (this.section === 'users' && Array.isArray(this.form.roles) && this.form.roles.length) body.roles = this.form.roles;
            if (this.section === 'tokens' && Array.isArray(this.form.abilities) && this.form.abilities.length) body.abilities = this.form.abilities;
            const method = this.editing ? 'PUT' : 'POST';
            const url = this.item().ep + (this.editing ? '/' + this.editing.id : '');
            let fetchOpts = {method, headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)};
            if (['documents','data-objects'].includes(this.section) && !this.editing) {
                const f = this.$refs.fileInput && this.$refs.fileInput.files[0];
                if (!f) { this.formError = this.t('Bitte eine Datei wählen.'); return; }
                const fd = new FormData();
                fd.append(this.section === 'data-objects' ? 'name' : 'title', body.title || body.name || f.name);
                if (body.category) fd.append('category', body.category);
                fd.append('file', f);
                fetchOpts = {method: 'POST', body: fd};
            }
            this.api(url, fetchOpts)
                .then(r => {
                    if (!r.ok) {
                        return r.json().then(d => {
                            const errs = d && d.errors ? Object.entries(d.errors).map(([k, ms]) => this.label(k) + ': ' + (Array.isArray(ms) ? ms[0] : ms)).join(' · ') : null;
                            this.formError = errs || (d && d.message ? d.message : 'HTTP ' + r.status + ' — Pflichtfelder fehlen?');
                        }).catch(() => { this.formError = 'HTTP ' + r.status + ' — Pflichtfelder fehlen?'; });
                    }
                    if (keepOpen && !this.editing) { this.form = {}; this.formDirty = false; this.formError = ''; this.toast(this.t('Eintrag angelegt.')); this.loadSection(); this.$nextTick(() => { const el = document.querySelector('#createForm input, #createForm select, #createForm textarea'); if (el) el.focus(); }); return r.json().then(d => { this.afterCreate(d); return d; }); }
                    this.showCreate = false; this.formDirty = false; this.detail = null; this.editing = null; this.loadSection(); return r.json().then(d => { this.afterCreate(d); return d; });
                });
        },
        afterCreate(d) {
            if (this.section === 'users' && d && d.initial_password) {
                this.toast(this.t('Benutzer angelegt — Initiales Passwort: ') + d.initial_password);
                try { navigator.clipboard.writeText(d.initial_password); } catch (_) {}
            }
            if (this.section === 'tokens' && d && d.token) {
                this.newToken = d.token;
                this.toast(this.t('API-Token angelegt — einmalig sichtbar.'));
            }
            if (this.section === 'integrations' && d && d.webhook_url) {
                this.newToken = d.webhook_url;
                this.toast(this.t('Webhook-Quelle angelegt — URL kopieren und im Quellsystem hinterlegen.'));
            }
        },
        webhookUrl() {
            return this.detail && this.detail.token ? location.origin + '/api/v1/webhooks/' + this.detail.token : '';
        },
        async runConnector() {
            if (!this.detail || !this.detail.id) return;
            this.rowLoading = true;
            try {
                const r = await this.api('/api/v1/connectors/' + this.detail.id + '/run', {method: 'POST'});
                const d = r && r.ok ? await r.json() : null;
                this.toast(d && d.last_status ? this.t('Konnektor: ') + d.last_status : this.t('Abruf gestartet.'));
                this.detail = null; this.loadSection();
            } finally { this.rowLoading = false; }
        },
        closeCreate() {
            if (this.showCreate && this.formDirty && !confirm(this.t(this.t('Ungespeicherte Änderungen verwerfen?')))) return;
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
        invertSel() {
            const s = {...this.selected};
            this.sorted(this.filtered()).forEach(r => { if (s[r.id]) delete s[r.id]; else s[r.id] = true; });
            this.selected = s;
        },
        selCount() { return Object.keys(this.selected).length; },
        notifBatch(action) {
            const ids = Object.keys(this.selected);
            if (!ids.length) return;
            this.api('/api/v1/notifications/batch', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ids, action})})
                .then(r => {
                    this.selected = {};
                    this.loadSection(); this.unreadNotifs();
                    if (r.ok) this.toast(action === 'read' ? this.t('Als gelesen markiert.') : action === 'unread' ? this.t('Als ungelesen markiert.') : this.t('Entfernt.'));
                });
        },
        bulkStatus(s) {
            const ids = Object.keys(this.selected);
            if (!ids.length) return;
            const prev = (this.rows || []).filter(r => this.selected[r.id]).map(r => ({id: r.id, status: r.status}));
            Promise.all(ids.map(id => this.api(this.item().ep + '/' + id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:s})})))
                .then(() => {
                    this.selected = {}; this.loadSection();
                    this.toast(ids.length + ' × ' + this.statusLabel(s) + '.', {label: this.t('Rückgängig'), fn: () => {
                        Promise.all(prev.map(p => this.api(this.item().ep + '/' + p.id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status: p.status})})))
                            .then(() => { this.toast(this.t('Wiederhergestellt.')); this.loadSection(); });
                    }});
                });
        },
        bulkDelete() {
            const ids = Object.keys(this.selected);
            if (!ids.length || !confirm(ids.length + this.t(' Einträge wirklich löschen?'))) return;
            const snapshots = (this.rows || []).filter(r => this.selected[r.id]).map(r => ({...r}));
            Promise.all(ids.map(id => this.api(this.item().ep + '/' + id, {method:'DELETE'})))
                .then(() => {
                    this.selected = {}; this.loadSection();
                    this.toast(ids.length + this.t(' gelöscht.'), {label: this.t('Rückgängig'), fn: () => this.restoreRows(snapshots)});
                });
        },
        copyVal(v) {
            const s = typeof v === 'object' ? JSON.stringify(v) : String(v);
            navigator.clipboard && navigator.clipboard.writeText(s).then(() => this.toast(this.t('Kopiert.'))).catch(() => this.toast(this.t('Kopieren fehlgeschlagen.')));
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
                this.toast(rs.every(r => r.ok) ? this.t('Wiederhergestellt.') : this.t('Teilweise fehlgeschlagen.'));
                this.loadSection();
            });
        },
        deleteRow(row) {
            if (!row || !row.id || !confirm(this.t(this.t('Wirklich löschen?')))) return;
            const snapshot = {...row};
            this.api(this.item().ep + '/' + row.id, {method: 'DELETE'}).then(() => {
                this.detail = null; this.loadSection();
                this.toast(this.t('Gelöscht.'), {label: this.t('Rückgängig'), fn: () => this.restoreRow(snapshot)});
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
                .then(r => { this.toast(r.ok ? this.t('Wiederhergestellt.') : this.t('Wiederherstellen fehlgeschlagen.')); if (r.ok) this.loadSection(); });
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
            else if (e.key === '?' || e.key === 'h') { e.preventDefault(); this.kbdHelp = !this.kbdHelp; }
            else if (e.key === 'n') { if (this.canCreate() && !this.showCreate && !this.detail && !this.palette) this.openCreate(); }
            else if (e.key === 'e') { if (this.detail && !this.showCreate && this.canEdit()) this.openEdit(); }
            else if (e.key === 'f') { if (!this.detail && !this.showCreate && !this.palette && !['dashboard','executive'].includes(this.section)) this.togglePin(this.section); }
            else if (e.key === 'a') { if (!this.detail && !this.showCreate && !this.palette && this.writable() && this.rows) this.toggleAll(this.selCount() < this.sorted(this.filtered()).length); }
            else if (e.key === 'r') { if (!this.showCreate && !this.palette) { if (this.detail) this.reloadRow(); else this.loadSection(true); } }
            else if (e.key === 'd') { if (this.detail && !this.showCreate && this.section !== 'documents' && this.canEdit()) this.openDuplicate(); }
            else if (e.key === 'i') { if (!this.detail && !this.showCreate && !this.palette && this.canImport()) { this.showImport = true; this.importText = ''; this.importResult = ''; } }
            else if (e.key === 'p') { if (this.detail && !this.showCreate) this.copyLink(); }
            else if (e.key === 'o') { if (!this.detail && !this.showCreate && !this.palette && this.filtered().length) this.detail = this.filtered()[0]; }
            else if (e.key === 'l') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.filtered().length > this.limit) this.limit = this.filtered().length; }
            else if (e.key === 't') { this.toggleDark(); }
            else if (e.key === 'x') { if (!this.detail && !this.showCreate && !this.palette && (this.query || this.statusFilter || this.severityFilter || this.roleFilter || this.kindFilter || this.codeFilter || this.unreadOnly || this.mutedOnly || this.evGroup || this.evDay || this.evActor || this.overdueOnly || this.dueSoonOnly || this.dueTodayOnly || this.myOnly || this.unassignedOnly)) { this.query = ''; this.statusFilter = ''; this.severityFilter = ''; this.roleFilter = ''; this.kindFilter = ''; this.codeFilter = ''; this.unreadOnly = false; this.mutedOnly = false; this.evGroup = ''; this.evDay = ''; evActor = ''; evDay = ''; this.overdueOnly = false; this.dueSoonOnly = false; this.dueTodayOnly = false; this.myOnly = false; this.unassignedOnly = false; } }
            else if (e.key === 'c') { if (!this.detail && !this.showCreate && !this.palette && this.rows) this.colPicker = !this.colPicker; }
            else if (e.key === 'v') { if (!this.detail && !this.showCreate && !this.palette && this.rows) this.viewPicker = !this.viewPicker; }
            else if (e.key === 's') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.rows.some(r => this.dueSoon(r))) this.dueSoonOnly = !this.dueSoonOnly; }
            else if (e.key === 'y') { if (!this.detail && !this.showCreate && !this.palette && this.tenantList.length > 1) { const ts = this.sortedTenants().map(t => t.id); const i = ts.indexOf(this.tenant); this.tenant = ts[(i + 1) % ts.length]; this.loadSection(); this.loadNavBadges(); this.toast('Mandant: ' + this.tenantName()); } }
            else if (e.key === 'z') { if (this._undo) { this._undo.fn(); this._undo = null; } }
            else if (e.key === 'j') { if (this.detail && !this.showCreate) this.copyJson(); }
            else if (e.key === 'k') { if (!this.detail && !this.showCreate && !this.palette) { this.compact = !this.compact; try { localStorage.setItem('af_density', this.compact ? '1' : '0'); } catch (err) {} this.toast(this.compact ? 'Kompakte Zeilen an' : 'Kompakte Zeilen aus'); } }
            else if (e.key === 'w') { if (this.detail && !this.showCreate) { this.drawerWide = !this.drawerWide; try { localStorage.setItem('af_drawer_wide', this.drawerWide ? '1' : '0'); } catch (err) {} } }
            else if (e.key === 'g') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.rows.length) { const opts = ['status', '__period', ...this.visCols().filter(c => /_id$/.test(c)), '']; const cyc = opts.filter(o => o === '' || (o === '__period' ? this.rows.some(r => r.updated_at) : this.rows.some(r => o in r))); const i = cyc.indexOf(this.groupBy); this.groupBy = cyc[(i + 1) % cyc.length]; this.toast(this.groupBy ? this.t('Gruppiert nach: ') + this.label(this.groupBy) : 'Gruppierung aus'); } }
            else if (e.key === 'b') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.rows.some(r => this.dueToday(r))) this.dueTodayOnly = !this.dueTodayOnly; }
            else if (e.key === 'B') { if (!this.detail && !this.showCreate && !this.palette) this.notif = !this.notif; }
            else if (e.key === 'm') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.rows.some(r => 'assignee_id' in r || 'responsible_id' in r || 'owner_id' in r || 'assigned_to' in r)) this.myOnly = !this.myOnly; }
            else if (e.key === 'q') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.rows.some(r => !(r.assignee_id || r.responsible_id || r.owner_id || r.assigned_to))) this.unassignedOnly = !this.unassignedOnly; }
            else if (e.key === 'u') { if (!this.detail && !this.showCreate && !this.palette && this.rows && this.rows.some(r => this.overdue(r))) this.overdueOnly = !this.overdueOnly; }
            else if (e.key === '.') { if (!this.detail && !this.showCreate && !this.palette && this.section !== 'dashboard') window.location.href = '/app/dashboard' + (this.tenant ? '?tenant=' + this.tenant : ''); }
            else if (/^[1-9]$/.test(e.key)) { if (!this.detail && !this.showCreate && !this.palette && this.sorted(this.filtered()).length >= +e.key) this.detail = this.sorted(this.filtered())[e.key - 1]; }
        },
        toggleDark() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            try { localStorage.setItem('af_dark', this.dark ? '1' : '0'); } catch (e) {}
        },
        buildTour() {
            const defs = [
                {sel: '[data-tour="nav"]', t: this.t('Navigation'), d: this.t('Alle Module in einer Seitenleiste — Dashboard, Aufgaben, Dokumente, Compliance, Team und mehr. Ein Klick öffnet die Sektion.')},
                {sel: '[data-tour="tenant"]', t: this.t('Mandant wählen'), d: this.t('Hier wechselst du zwischen deinen Unternehmen/Mandanten — alle Daten gehören zum gewählten Mandanten.')},
                {sel: '[data-tour="navq"]', t: this.t('Module filtern'), d: this.t('Tippen, um Module schnell zu finden — z. B. „Aufgaben" — Enter öffnet den ersten Treffer.')},
                {sel: '[data-tour="globalsearch"]', t: this.t('Globale Suche'), d: this.t('Suche über alles: Firmen, Aufgaben, Dokumente — Enter öffnet den ersten Treffer direkt.')},
                {sel: '[data-tour="kpis"]', t: this.t('KPI-Karten'), d: this.t('Deine wichtigsten Kennzahlen auf einen Blick. Ein Klick auf eine Karte öffnet die zugehörige Sektion.')},
                {sel: '[data-tour="insights"]', t: this.t('KI-Hinweise'), d: this.t('Automatisch erkannte Risiken und offene Punkte — ein Klick springt zum betroffenen Eintrag.')},
                {sel: '[data-tour="bell"]', t: this.t('Benachrichtigungen'), d: this.t('Erinnerungen zu Fristen und Zuweisungen — das Badge zeigt, was ansteht.')},
                {sel: '[data-tour="listsearch"]', t: this.t('Suchen & Filtern'), d: this.t('Jede Liste durchsuchbar und filterbar — Taste „/" fokussiert die Suche, „x" setzt Filter zurück.')},
                {sel: '[data-tour="new"]', t: this.t('Neuer Eintrag'), d: this.t('Hier legst du neue Datensätze an — oder mit Taste „n". Eine Zeile anklicken öffnet Details, Bearbeiten und Status-Aktionen.')},
                {sel: '[data-tour="dark"]', t: this.t('Darstellung'), d: this.t('Zwischen heller und dunkler Darstellung wechseln — auch mit Taste „t".')},
                {sel: null, t: this.t('Fertig!'), d: this.t('Das war der Rundgang. Tipp: Ctrl+K öffnet die Befehlspalette, „?" zeigt alle Tastenkürzel.')},
            ];
            return defs.filter(s => {
                if (! s.sel) return true;
                const el = document.querySelector(s.sel);
                return el && el.offsetParent !== null;
            });
        },
        startTour() {
            this.tourPrompt = false;
            this.tourSteps = this.buildTour();
            this.tourStep = 0;
            this.tour = true;
            this.$nextTick(() => this.tourPlace());
        },
        tourPlace() {
            const s = this.tourSteps[this.tourStep];
            const el = s && s.sel ? document.querySelector(s.sel) : null;
            if (! el) { this.tourRect = null; return; }
            el.scrollIntoView({block: 'center'});
            const r = el.getBoundingClientRect();
            this.tourRect = {top: r.top - 4, left: r.left - 4, width: r.width + 8, height: r.height + 8};
        },
        tourTip() {
            if (! this.tourRect) return 'top:50%;left:50%;transform:translate(-50%,-50%)';
            const w = 320, m = 12, vw = window.innerWidth, vh = window.innerHeight;
            const left = Math.min(Math.max(this.tourRect.left, m), vw - w - m);
            let top = this.tourRect.top + this.tourRect.height + m;
            if (top + 170 > vh) top = Math.max(m, this.tourRect.top - 170 - m);
            return `top:${top}px;left:${left}px`;
        },
        tourNext() {
            if (this.tourStep + 1 >= this.tourSteps.length) { this.endTour(); return; }
            this.tourStep++;
            this.$nextTick(() => this.tourPlace());
        },
        tourBack() {
            if (this.tourStep > 0) { this.tourStep--; this.$nextTick(() => this.tourPlace()); }
        },
        tourDone() { try { localStorage.setItem('af_tour_done', '1'); } catch (e) {} },
        endTour() { this.tour = false; this.tourDone(); },
        copyLink() {
            const url = location.origin + '/app/' + this.section + '?tenant=' + this.tenant + '&open=' + this.detail.id;
            navigator.clipboard.writeText(url).then(() => { this.linkCopied = true; setTimeout(() => this.linkCopied = false, 1500); });
        },
        relAgo(iso) {
            const d = new Date(iso); if (isNaN(d)) return '';
            const s = Math.round((Date.now() - d.getTime()) / 1000);
            if (s < 60) return 'gerade eben';
            const m = Math.round(s / 60); if (m < 60) return 'vor ' + m + ' Min';
            const h = Math.round(m / 60); if (h < 24) return 'vor ' + h + ' Std';
            const t = Math.round(h / 24); return 'vor ' + t + ' ' + (t === 1 ? 'Tag' : 'Tagen');
        },
        copyText() {
            if (!this.detail) return;
            const txt = this.detailKeys().map(k => this.label(k) + ': ' + this.fmtD(this.detail, k)).join('\n');
            navigator.clipboard.writeText(txt).then(() => { this.textCopied = true; setTimeout(() => this.textCopied = false, 1500); });
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
        dueKey(row) { return ['due_at','deadline','deadline_at','ends_on','ends_at','due_date','end_date','next_due_at','review_at','scheduled_at'].find(k => row && row[k]); },
        isOpenStatus(row) {
            const OPEN = ['open','pending','in_progress','running','queued','scheduled','planned','active','submitted','shortlisted','draft','on_hold'];
            return !row.status || OPEN.includes(String(row.status));
        },
        parseDate(v) {
            const m = /^([0-9]{2})\.([0-9]{2})\.([0-9]{4})(?:[ ,]([0-9]{2}):([0-9]{2}))?/.exec(String(v));
            if (m) return new Date(+m[3], +m[2] - 1, +m[1], +(m[4] || 0), +(m[5] || 0));
            return new Date(v);
        },
        overdue(row) {
            const key = this.dueKey(row);
            if (!key || !this.isOpenStatus(row)) return false;
            const d = this.parseDate(row[key]);
            return !isNaN(d) && d < new Date();
        },
        dueSoon(row) {
            const key = this.dueKey(row);
            if (!key || !this.isOpenStatus(row)) return false;
            const d = this.parseDate(row[key]), now = new Date();
            return !isNaN(d) && d >= now && d <= new Date(now.getTime() + 7*864e5);
        },
        dueToday(row) {
            const key = this.dueKey(row);
            if (!key || !this.isOpenStatus(row)) return false;
            const d = this.parseDate(row[key]), now = new Date();
            return !isNaN(d) && d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
        },
        eintrag(n) { return this.t(n === 1 ? 'Eintrag' : this.t('Einträge')); },
        detailTitle() {
            if (!this.detail) return this.title() + ' · Details';
            const d = this.detail;
            const name = d.name || d.title || d.headline || d.subject || d.order_no || (d.first_name ? [d.first_name, d.last_name].filter(Boolean).join(' ') : null) || d.file_name || d.email;
            return name ? this.title() + ' · ' + name : this.title() + ' · Details';
        },
        label(c) {
            const L_EN = {__period:'Period',name:'Name',title:'Title',first_name:'First name',last_name:'Last name',email:'E-mail',phone:'Phone',type:'Type',status:'Status',description:'Description',content:'Content',category:'Category',subject:'Subject',area:'Area',hazard:'Hazard',risk_level:'Risk level',measures:'Measures',result:'Result',notes:'Notes',progress:'Progress',quantity:'Quantity',order_no:'Order no.',product:'Product',scrap_qty:'Scrap',headline:'Headline',bio:'Bio',skills:'Skills',hourly_rate:'Hourly rate',budget:'Budget',price:'Price',proposal:'Proposal',stake_pct:'Stake %',invested_amount:'Invested',current_valuation:'Valuation',capital_need:'Capital need',revenue:'Revenue',cashflow:'Cashflow',ebitda:'EBITDA',liquidity:'Liquidity',period:'Period',legal_form:'Legal form',street:'Street',zip:'ZIP',city:'City',country:'Country',version:'Version',valid_from:'Valid from',interval_months:'Interval (mo.)',capacity_units_per_day:'Capacity/day',asset_class:'Asset class',cost_basis:'Cost basis',current_value:'Current value',currency:'Currency',due_at:'Due',deadline_at:'Deadline',scheduled_at:'Scheduled',starts_on:'From',ends_on:'To',starts_at:'Start',ends_at:'End',acquired_at:'Acquired',valued_at:'Valued at',body:'Body',is_accepted:'Accepted',document_id:'Document',audit_id:'Audit',auditor:'Auditor',standard:'Standard',severity:'Severity',audits_open:'Open audits',findings_open:'Open findings',open_findings_count:'Open findings',findings_count:'Findings',applications_count:'Applications',answers_count:'Answers',open_tasks_count:'Open tasks',members_count:'Members',projects_count:'Projects',measures_count:'Measures',role_names:'Roles',password:'Password',last_login_at:'Last login',last_login_ip:'Login IP',read:'Read',kind:'Kind',entity_id:'Record',abilities:'Abilities',expires_at:'Expires',last_used_at:'Last used',expires_in_days:'Expiry (days)',summary:'Data'};
            const L = {__period:this.t('Zeitraum'),name:'Name',title:'Titel',first_name:'Vorname',last_name:'Nachname',email:'E-Mail',phone:'Telefon',type:'Typ',status:'Status',description:'Beschreibung',content:'Inhalt',category:'Kategorie',subject:'Betreff',area:'Bereich',hazard:this.t('Gefährdung'),risk_level:'Risikostufe',measures:'Maßnahmen',result:'Ergebnis',notes:'Notizen',progress:'Fortschritt',quantity:'Menge',order_no:'Auftrag-Nr.',product:'Produkt',scrap_qty:'Ausschuss',headline:'Schlagzeile',bio:'Bio',skills:'Skills',hourly_rate:'Stundensatz',budget:'Budget',price:'Preis',proposal:'Angebot',stake_pct:'Anteil %',invested_amount:'Investiert',current_valuation:'Bewertung',capital_need:'Kapitalbedarf',revenue:'Umsatz',cashflow:'Cashflow',ebitda:'EBITDA',liquidity:'Liquidität',period:'Periode',legal_form:'Rechtsform',street:'Straße',zip:'PLZ',city:'Stadt',country:'Land',version:'Version',valid_from:this.t('Gültig ab'),interval_months:'Intervall (Mon.)',capacity_units_per_day:this.t('Kapazität/Tag'),asset_class:'Anlageklasse',cost_basis:'Kostenbasis',current_value:'Aktueller Wert',currency:'Währung',due_at:this.t('Fällig'),deadline_at:'Frist',scheduled_at:'Geplant',starts_on:'Von',ends_on:'Bis',starts_at:'Start',ends_at:'Ende',acquired_at:'Erworben',valued_at:'Bewertet am',body:'Inhalt',is_accepted:'Akzeptiert',document_id:'Dokument',audit_id:'Audit',auditor:'Auditor',standard:'Norm',severity:'Schwere',audits_open:'Offene Audits',findings_open:'Offene Festst.',open_findings_count:'Offene Festst.',findings_count:'Feststellungen',applications_count:'Bewerbungen',answers_count:this.t('Antworten'),open_tasks_count:'Offene Aufgaben',members_count:'Mitglieder',projects_count:'Projekte',measures_count:'Maßnahmen',role_names:this.t('Rollen'),password:'Passwort',last_login_at:'Letzte Anmeldung',last_login_ip:'Anmeldung IP',read:this.t('Gelesen'),kind:'Art',entity_id:'Datensatz',abilities:'Rechte',expires_at:'Läuft ab',last_used_at:'Zuletzt genutzt',expires_in_days:'Ablauf (Tage)',summary:'Daten'};
            const LM = this.lang === 'en' ? L_EN : L;
            const TD = this.lang === 'en' ? {aktion:'Action',occurred_at:'Date',number:'Number',customer:'Customer',count:'Count',days:'Days',on_time:'On time'} : {aktion:'Aktion',occurred_at:'Datum',number:'Nummer',customer:this.t('Kunde'),count:'Anzahl',days:'Tage',on_time:'Pünktlich'};
            if (!LM[c] && c.endsWith('_id')) {
                const F = {person_id:'Person',company_id:this.t('Unternehmen'),machine_id:'Maschine',task_id:'Aufgabe',question_id:this.t('Frage'),answer_id:'Antwort',tender_id:'Ausschreibung',project_id:'Projekt',strategy_id:'Strategie',measure_id:'Maßnahme',portfolio_id:'Portfolio',investment_id:'Investment',participation_id:'Beteiligung',expert_profile_id:'Experte',instruction_id:'Unterweisung',inspection_id:this.t('Prüfung'),risk_assessment_id:this.t('Gefährdungsbeurteilung'),financial_report_id:'Finanzbericht',leave_request_id:'Abwesenheit',parent_id:this.t('Übergeordnet'),responsible_id:'Verantwortlich',assignee_id:'Zugewiesen',created_by:'Erstellt von',updated_by:this.t('Geändert von'),approved_by:'Genehmigt von',awarded_by:'Vergeben von',user_id:'Benutzer',document_id:this.t('Dokument'),audit_id:this.t('Audit'),auditor:'Auditor',standard:'Norm',severity:'Schwere',audits_open:'Offene Audits',findings_open:'Offene Festst.',open_findings_count:'Offene Festst.',findings_count:'Feststellungen',applications_count:'Bewerbungen',answers_count:this.t('Antworten'),open_tasks_count:'Offene Aufgaben',members_count:'Mitglieder',projects_count:'Projekte',measures_count:this.t('Maßnahmen')};
                if (F[c]) return F[c];
                return c.slice(0, -3).replace(/_/g,' ').replace(/^\w/, s => s.toUpperCase());
            }
            return LM[c] || TD[c] || c.replace(/_/g,' ').replace(/^\w/, s => s.toUpperCase());
        },
        periodKey(v) {
            const d = new Date(v);
            if (isNaN(d)) return this.t('Unbekannt');
            const now = new Date();
            const day = x => new Date(x.getFullYear(), x.getMonth(), x.getDate());
            const diff = Math.round((day(now) - day(d)) / 86400000);
            return diff <= 0 ? this.t('Heute') : (diff < 7 ? this.t('Diese Woche') : this.t('Älter'));
        },
        dueRel(v) {
            if (!v) return '';
            const d = new Date(v);
            const days = Math.ceil((d - Date.now()) / 86400000);
            const rel = days === -1 ? this.t('gestern') : days === 0 ? this.t('heute') : days === 1 ? this.t('morgen') : days < 0 ? (this.lang==='en' ? `${-days}d ago` : `vor ${-days} T`) : (this.lang==='en' ? `in ${days}d` : `in ${days} T`);
            const col = days < 0 ? 'text-[#A6362E]' : days <= 7 ? 'text-[#CA8A04]' : 'text-[#9CA3AF]';
            return `<span class="${col}">${d.toLocaleDateString((this.lang==='en'?'en-GB':'de-DE'))} (${rel})</span>`;
        },
        reloadRow() {
            if (!this.detail || !this.detail.id || ['dashboard','executive'].includes(this.section)) return;
            this.rowLoading = true;
            this.api(this.item().ep + '/' + this.detail.id).then(r => {
                if (!r.ok) { this.toast(this.t('Aktualisieren fehlgeschlagen (HTTP ') + r.status + ').'); return null; }
                return r.json();
            }).then(d => {
                if (!d) return;
                const row = d.data || d;
                this.detail = row;
                this.rows = (this.rows || []).map(r => String(r.id) === String(row.id) ? row : r);
                this.loadRowEvents(row.id);
                this.rowLoading = false;
                this.toast(this.t('Datensatz aktualisiert.'));
            }).finally(() => { this.rowLoading = false; });
        },
        cell(row, c) {
            let v = row[c];
            if (v === null || v === undefined) return '—';
            if (c === 'id' && typeof v === 'string' && v.length > 8) return `<button onclick="event.stopPropagation();navigator.clipboard.writeText('${v}')" title="ID kopieren: ${v}" class="font-mono text-[11px] text-[#5B6B7E] hover:text-[#CA8A04]">${v.slice(0, 8)}…</button>`;
            if (c === 'event_type' && typeof v === 'string') return `<span class="text-[10px] font-semibold uppercase tracking-wide text-[#CA8A04]">${this.eventGroup(v)}</span> <span class="text-[#5B6B7E]">${this.eventLabel(v)}</span>`;
            const rn = this.resolveId(c, v);
            if (rn) {
                const t = FKMAP[c];
                const sk = t ? ({expert_profiles: 'expert-profiles', graph_entities: 'graph-entities'})[t] || t : null;
                const href = sk && this.groups.flatMap(g => g.items).some(i => i.key === sk) ? `/app/${sk}?tenant=${this.tenant}&open=${encodeURIComponent(v)}` : null;
                return href ? `<a href="${href}" onclick="event.stopPropagation()" title="${v}" class="text-[#1A2433] underline decoration-[#D6DEE9] underline-offset-2 hover:text-[#CA8A04] hover:decoration-[#CA8A04]">${rn}</a>` : `<span title="${v}">${rn}</span>`;
            }
            if (c === 'role_names' && Array.isArray(v)) {
                if (!v.length) return '—';
                return v.map(n => `<span class="inline-block text-[10px] px-1.5 py-0.5 rounded bg-[#F4F6F9] text-[#42536A] font-mono mr-1 mb-0.5" title="${n}">${this.roleLabel(n)}</span>`).join('');
            }
            if (typeof v === 'boolean') return v ? 'Ja' : this.t('Nein');
            if (typeof v === 'string' && /^\d+(\.\d+)?$/.test(v) && c !== 'id' && !c.endsWith('_id') && !/_date|_at|no$|number|phone|zip/i.test(c)) v = parseFloat(v);
            if (typeof v === 'number' && c !== 'id' && !c.endsWith('_id') && Number.isFinite(v)) {
                if (/_?size_?bytes?$|bytes/i.test(c)) {
                    const u = ['B','KB','MB','GB']; let s = v, i = 0;
                    while (s >= 1024 && i < 3) { s /= 1024; i++; }
                    return s.toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {maximumFractionDigits: i ? 1 : 0}) + ' ' + u[i];
                }
                if (/progress|pct|percent|rate$|quote|completion/i.test(c) && v >= 0 && v <= 100) {
                    const col = v >= 100 ? '#2E7D5B' : v >= 50 ? '#CA8A04' : '#A6362E';
                    return `<span class="inline-flex items-center gap-2"><span class="inline-block w-16 h-1.5 rounded-full bg-[#E4E9F0] overflow-hidden"><span class="block h-full rounded-full" style="width:${Math.min(100,v)}%;background:${col}"></span></span><span>${v}%</span></span>`;
                }
                const f = Math.abs(v) % 1 ? v.toLocaleString((this.lang==='en'?'en-GB':'de-DE'), {minimumFractionDigits: 2, maximumFractionDigits: 2}) : v.toLocaleString((this.lang==='en'?'en-GB':'de-DE'));
                return /price|amount|value|cost|rate|budget|revenue|ebitda|salary|euro|eur/i.test(c) ? f + ' €' : f;
            }
            if (c === 'status' || c === 'severity' || c === 'type' || c === 'risk_level') {
                const map = {open:'#CA8A04',pending:'#CA8A04',in_progress:'#CA8A04',running:'#CA8A04',queued:'#5B6B7E',scheduled:'#5B6B7E',planned:'#5B6B7E',on_hold:'#CA8A04',critical:'#A6362E',high:'#A6362E',warning:'#CA8A04',cancelled:'#A6362E',rejected:'#A6362E',done:'#2E7D5B',completed:'#2E7D5B',approved:'#2E7D5B',accepted:'#2E7D5B',mitigated:'#2E7D5B',active:'#2E7D5B',awarded:'#2E7D5B',info:'#5B6B7E'};
                const col = map[String(v).toLowerCase()] || '#5B6B7E';
                const txt = STATUS_DE[String(v).toLowerCase()] || v;
                return `<span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full" style="background:${col}"></span>${txt}</span>`;
            }
            if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(v)) {
                const d = new Date(v);
                let out = d.toLocaleDateString((this.lang==='en'?'en-GB':'de-DE'), {weekday: 'short'}) + ', ' + d.toLocaleDateString((this.lang==='en'?'en-GB':'de-DE'));
                if (d.getHours() !== 0 || d.getMinutes() !== 0) out += ' ' + d.toLocaleTimeString((this.lang==='en'?'en-GB':'de-DE'), {hour: '2-digit', minute: '2-digit'});
                if (/due|deadline|scheduled/.test(c)) {
                    const days = Math.ceil((d - Date.now()) / 86400000);
                    const rel = days === -1 ? this.t('gestern') : days === 0 ? this.t('heute') : days === 1 ? this.t('morgen') : days < 0 ? (this.lang==='en' ? `${-days}d ago` : `vor ${-days} T`) : (this.lang==='en' ? `in ${days}d` : `in ${days} T`);
                    if (days < 0) out += ` <span class="text-[#A6362E]">(${rel})</span>`;
                    else if (days <= 7) out += ` <span class="text-[#CA8A04]">(${rel})</span>`;
                }
                return out;
            }
            if (c === 'kind' && this.nKind(String(v).toLowerCase()) !== String(v).toLowerCase()) {
                return `<span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full bg-[#CA8A04]"></span>${this.nKind(String(v).toLowerCase())}</span>`;
            }
            if (typeof v === 'string' && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v) && /mail/i.test(c))
                return `<a href="mailto:${v}" onclick="event.stopPropagation()" class="text-[#CA8A04] hover:underline">${v}</a>`;
            if (typeof v === 'string' && /^[+0-9][0-9\s\/()-]{5,}$/.test(v) && /phone|tel|mobile/i.test(c))
                return `<a href="tel:${v.replace(/[^+0-9]/g,'')}" onclick="event.stopPropagation()" class="text-[#CA8A04] hover:underline">${v}</a>`;
            if (typeof v === 'string' && /^https?:\/\/\S+$/.test(v))
                return `<a href="${v}" target="_blank" rel="noopener" onclick="event.stopPropagation()" class="text-[#CA8A04] hover:underline">${v.length > 60 ? v.slice(0,60)+'…' : v}</a>`;
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
