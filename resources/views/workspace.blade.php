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
<div class="min-h-screen lg:flex" x-data="workspace(@js($section))" x-cloak>

    {{-- Sidebar --}}
    <aside class="bg-[#0B0B0F] text-white lg:w-64 lg:min-h-screen lg:sticky lg:top-0 flex flex-col">
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
                    <div class="px-5 pt-4 pb-1.5 text-[10px] font-semibold tracking-widest text-[#6B7280]" x-text="group.label"></div>
                    <template x-for="item in group.items" :key="item.key">
                        <a :href="'/app/' + item.key + (tenant ? '?tenant='+tenant : '')"
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
                            <div class="flex items-start gap-3 rounded-lg border bg-white px-4 py-3 text-sm"
                                 :class="{'border-[#A6362E]/40': i.severity==='critical','border-[#CA8A04]/50': i.severity==='warning','border-[#D6DEE9]': i.severity==='info'}">
                                <span class="mt-0.5 inline-block h-2 w-2 rounded-full shrink-0"
                                      :class="{'bg-[#A6362E]': i.severity==='critical','bg-[#CA8A04]': i.severity==='warning','bg-[#5B6B7E]': i.severity==='info'}"></span>
                                <span x-text="i.message"></span>
                            </div>
                        </template>
                    </div>
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4">
                        <template x-for="m in kpiCards" :key="m.key">
                            <div class="bg-white border border-[#E4E9F0] rounded-xl px-5 py-4">
                                <div class="text-[11px] font-medium text-[#5B6B7E]" x-text="m.label"></div>
                                <div class="mt-1.5 font-mono text-2xl font-semibold tracking-tight text-[#0B0B0F]" x-text="metric(m.key)"></div>
                                <div class="mt-2 h-0.5 w-8 rounded-full bg-[#FACC15]"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Generic list --}}
            <template x-if="section !== 'dashboard'">
                <div class="bg-white border border-[#E4E9F0] rounded-xl overflow-hidden">
                    <div x-show="!rows" class="px-6 py-12 text-center text-sm text-[#5B6B7E]">
                        Wählen Sie links einen Mandanten, um Daten zu laden.
                    </div>
                    <div x-show="rows !== null" class="flex items-center justify-between gap-3 px-5 py-3 border-b border-[#E4E9F0]">
                        <span class="text-xs text-[#5B6B7E] shrink-0" x-text="filtered().length + ' / ' + (rows ? rows.length : 0) + ' Einträge'"></span>
                        <input x-model="query" placeholder="Suchen…" class="w-48 rounded-lg border-[#D6DEE9] text-xs py-1.5 focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                        <button @click="openCreate()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition shrink-0">+ Neu</button>
                    </div>
                    <div x-show="rows && rows.length === 0" class="px-6 py-12 text-center text-sm text-[#5B6B7E]">
                        Keine Einträge vorhanden.
                    </div>
                    <table x-show="rows && rows.length" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[#E4E9F0] bg-[#FAFBFC] text-left">
                                <template x-for="c in columns" :key="c">
                                    <th @click="sort(c)" class="px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E] cursor-pointer select-none hover:text-[#0B0B0F]">
                                        <span x-text="label(c)"></span><span class="ml-1 text-[#CA8A04]" x-text="sortKey===c ? (sortAsc?'▲':'▼') : ''"></span>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in sorted(filtered()).slice(0, limit)" :key="idx">
                                <tr @click="detail = row" :class="overdue(row) ? 'bg-[#A6362E]/5' : ''" class="border-b border-[#F0F3F7] last:border-b-0 hover:bg-[#FAFBFC] cursor-pointer">
                                    <template x-for="c in columns" :key="c">
                                        <td class="px-5 py-3 text-[#1A2433]" x-html="cell(row, c)"></td>
                                    </template>
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
                    <button @click="detail = null" class="text-[#5B6B7E] hover:text-[#0B0B0F]">&times;</button>
                </div>
                <div class="flex-1 overflow-y-auto p-6">
                    <dl class="space-y-3 text-sm">
                        <template x-for="k in Object.keys(detail || {})" :key="k">
                            <div class="flex gap-3">
                                <dt class="w-36 shrink-0 text-[#5B6B7E]" x-text="label(k)"></dt>
                                <dd class="min-w-0 font-mono text-[13px] text-[#1A2433] break-words" x-text="fmtD(detail, k)"></dd>
                            </div>
                        </template>
                    </dl>
                </div>
                <div x-show="statusActions().length" class="px-6 py-3 border-t border-[#E4E9F0] flex flex-wrap gap-2">
                    <template x-for="a in statusActions()" :key="a[0]">
                        <button @click="applyStatus(a[1])" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10" x-text="a[0]"></button>
                    </template>
                </div>
                <div class="px-6 py-4 border-t border-[#E4E9F0] flex justify-end gap-2">
                    <button x-show="section === 'documents'" @click="downloadDoc(detail)" class="text-xs px-3 py-1.5 border border-[#CA8A04]/50 text-[#CA8A04] rounded-lg hover:bg-[#CA8A04]/10">Download</button>
                    <button @click="openEdit()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F]">Bearbeiten</button>
                    <button @click="deleteRow(detail)" class="text-xs px-3 py-1.5 border border-[#A6362E]/40 text-[#A6362E] rounded-lg hover:bg-[#A6362E]/5">Löschen</button>
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
                            <input x-model="form[f.key]" :type="f.type"
                                   class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                        </div>
                    </template>
                    <div x-show="section === 'documents' && !editing">
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
        {label:'START', items:[{key:'dashboard',label:'Dashboard'}]},
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
        ]},
    ];
    const HIDE = new Set(['id','tenant_id','created_at','updated_at','deleted_at','pivot','data','roles','permissions','email_verified_at']);
    const KPI = [
        {key:'companies',label:'Unternehmen'},{key:'persons',label:'Personen'},
        {key:'documents',label:'Dokumente'},{key:'tasks_open',label:'Offene Aufgaben'},
        {key:'instructions',label:'Unterweisungen'},{key:'compliance_rate',label:'Compliance %'},
        {key:'deadlines_open',label:'Offene Fristen'},{key:'risk_high',label:'Hohe Risiken'},
        {key:'tenders_open',label:'Offene Ausschreibungen'},{key:'expert_profiles',label:'Experten'},
        {key:'questions',label:'Fragen'},{key:'inspections',label:'Prüfungen'},
    ];
    return {
        section: initial, groups: GROUPS, kpiCards: KPI,
        tenant: '', rows: null, columns: [], metrics: null, insights: [], lookups: {},
        tenantList: {{ \Illuminate\Support\Js::from($tenants->map(fn($t) => ['id' => $t->id, 'name' => $t->name])) }},
        loading: false, error: '', detail: null, showCreate: false, form: {}, formError: '', query: '', editing: null,
        sortKey: '', sortAsc: true, limit: 100,
        init() {
            const t = new URLSearchParams(location.search).get('tenant');
            if (t) this.tenant = t;
            if (this.tenant) this.loadSection();
        },
        item() {
            return GROUPS.flatMap(g => g.items).find(i => i.key === this.section) || {label:this.section};
        },
        title() { return this.item().label; },
        tenantName() { const t = this.tenantList.find(x => x.id === this.tenant); return t ? t.name : '— kein Mandant —'; },
        subtitle() { return (this.section === 'dashboard' ? 'Unternehmenssteuerung' : 'Modul ' + (this.item().label||this.section)) + ' · ' + this.tenantName(); },
        metric(k) { const v = this.metrics && this.metrics[k]; return v ? parseFloat(v.value) : '–'; },
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
            this.loading = true; this.error = ''; this.limit = 100;
            const url = new URL(location.href); url.searchParams.set('tenant', this.tenant);
            history.replaceState(null,'',url);
            if (this.section === 'dashboard') {
                this.api('/api/v1/metrics').then(r => r.ok ? r.json() : (this.error='HTTP '+r.status, null))
                    .then(d => { this.metrics = d; this.loading = false; });
                this.api('/api/v1/insights').then(r => r.ok ? r.json() : [])
                    .then(d => this.insights = d.filter(i => i.code !== 'all_clear'));
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
        statusActions() {
            if (!this.detail || !('status' in this.detail)) return [];
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
            return (A[this.section] || []).filter(a => a[1] !== this.detail.status);
        },
        applyStatus(s) {
            this.api(this.item().ep + '/' + this.detail.id, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({status:s})})
                .then(r => { if (r.ok) { this.detail = null; this.loadSection(); } else alert('Aktion fehlgeschlagen (HTTP '+r.status+')'); });
        },
        async downloadDoc(row) {
            const r = await this.api('/api/v1/documents/' + row.id + '/download');
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
        filtered() {
            if (!this.rows) return [];
            const q = this.query.trim().toLowerCase();
            if (!q) return this.rows;
            return this.rows.filter(r => Object.values(r).some(v => String(v).toLowerCase().includes(q)));
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
            return Object.keys(src).filter(k => !SKIP.has(k) && !k.endsWith('_id')).slice(0, 10).map(k => ({
                key: k,
                type: typeof src[k] === 'number' ? 'number' : (/_at$|_date$/.test(k) ? 'date' : 'text'),
            }));
        },
        openCreate() { this.editing = null; this.form = {}; this.formError = ''; this.showCreate = true; },
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
            if (this.section === 'documents' && !this.editing) {
                const f = this.$refs.fileInput && this.$refs.fileInput.files[0];
                if (!f) { this.formError = 'Bitte eine Datei wählen.'; return; }
                const fd = new FormData();
                fd.append('title', body.title || f.name);
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
            const MAP = {person_id:'persons',company_id:'companies',machine_id:'machines',project_id:'projects',strategy_id:'strategies',portfolio_id:'portfolios',tender_id:'tenders',question_id:'questions',document_id:'documents',expert_profile_id:'expert_profiles',responsible_id:'users',assignee_id:'users',owner_id:'users',asked_by:'users',approved_by:'users',answered_by:'users',created_by:'users',uploaded_by:'users',assigned_to:'persons'};
            const lk = MAP[c];
            if (!lk || !this.lookups[lk]) return null;
            return this.lookups[lk][v] || null;
        },
        overdue(row) {
            const OPEN = ['open','pending','in_progress','running','queued','scheduled','planned','active','submitted','shortlisted','draft','on_hold'];
            const key = ['due_at','deadline','ends_on','due_date','end_date','next_due_at'].find(k => row[k]);
            if (!key || (row.status && !OPEN.includes(String(row.status)))) return false;
            const d = new Date(row[key]);
            return !isNaN(d) && d < new Date();
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
                const DE = {open:'Offen',pending:'Ausstehend',in_progress:'Läuft',active:'Aktiv',done:'Fertig',completed:'Abgeschlossen',approved:'Genehmigt',archived:'Archiviert',draft:'Entwurf',maintenance:'Wartung',retired:'Ausgemustert',awarded:'Vergeben',info:'Info',warning:'Warnung',critical:'Kritisch',high:'Hoch',medium:'Mittel',low:'Niedrig',scheduled:'Geplant',cancelled:'Abgesagt',rejected:'Abgelehnt',answered:'Beantwortet',closed:'Geschlossen',submitted:'Eingereicht',shortlisted:'Vorauswahl',queued:'Warteschlange',running:'Läuft',mitigated:'Gemindert',accepted:'Akzeptiert',planned:'Geplant',on_hold:'Pausiert',inactive:'Inaktiv'};
                const txt = DE[String(v).toLowerCase()] || v;
                return `<span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full" style="background:${col}"></span>${txt}</span>`;
            }
            if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(v)) return new Date(v).toLocaleDateString('de-DE');
            if (typeof v === 'string' && v.length > 80) return v.slice(0,80)+'…';
            return v;
        },
    }
}
</script>
</body>
</html>
