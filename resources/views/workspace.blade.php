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
                @foreach($tenants as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
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
                    <div x-show="rows !== null" class="flex items-center justify-between px-5 py-3 border-b border-[#E4E9F0]">
                        <span class="text-xs text-[#5B6B7E]" x-text="(rows ? rows.length : 0) + ' Einträge'"></span>
                        <button @click="openCreate()" class="text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition">+ Neu</button>
                    </div>
                    <div x-show="rows && rows.length === 0" class="px-6 py-12 text-center text-sm text-[#5B6B7E]">
                        Keine Einträge vorhanden.
                    </div>
                    <table x-show="rows && rows.length" class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-[#E4E9F0] bg-[#FAFBFC] text-left">
                                <template x-for="c in columns" :key="c">
                                    <th class="px-5 py-3 text-[11px] font-semibold tracking-wide text-[#5B6B7E]" x-text="label(c)"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in rows" :key="idx">
                                <tr @click="detail = row" class="border-b border-[#F0F3F7] last:border-b-0 hover:bg-[#FAFBFC] cursor-pointer">
                                    <template x-for="c in columns" :key="c">
                                        <td class="px-5 py-3 text-[#1A2433]" x-html="cell(row, c)"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
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
                                <dd class="min-w-0 font-mono text-[13px] text-[#1A2433] break-words" x-text="fmt(detail[k])"></dd>
                            </div>
                        </template>
                    </dl>
                </div>
                <div class="px-6 py-4 border-t border-[#E4E9F0] flex justify-end">
                    <button @click="deleteRow(detail)" class="text-xs px-3 py-1.5 border border-[#A6362E]/40 text-[#A6362E] rounded-lg hover:bg-[#A6362E]/5">Löschen</button>
                </div>
            </div>
        </div>

        {{-- Create modal --}}
        <div x-show="showCreate" class="fixed inset-0 z-40 flex items-center justify-center" style="display:none">
            <div class="absolute inset-0 bg-[#0B0B0F]/40" @click="showCreate = false"></div>
            <div class="relative w-full max-w-md bg-white rounded-xl shadow-xl">
                <div class="px-6 py-4 border-b border-[#E4E9F0]">
                    <h2 class="font-semibold text-[#0B0B0F]" x-text="'Neu: ' + title()"></h2>
                </div>
                <form @submit.prevent="submitCreate" class="p-6 space-y-4">
                    <template x-for="f in createFields()" :key="f.key">
                        <div>
                            <label class="block text-[13px] font-medium text-[#42536A] mb-1" x-text="label(f.key)"></label>
                            <input x-model="form[f.key]" :type="f.type"
                                   class="w-full rounded-lg border-[#D6DEE9] text-sm focus:border-[#CA8A04] focus:ring-[#CA8A04]/30">
                        </div>
                    </template>
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
        tenant: '', rows: null, columns: [], metrics: null, insights: [],
        loading: false, error: '', detail: null, showCreate: false, form: {}, formError: '',
        init() {
            const t = new URLSearchParams(location.search).get('tenant');
            if (t) this.tenant = t;
            if (this.tenant) this.loadSection();
        },
        item() {
            return GROUPS.flatMap(g => g.items).find(i => i.key === this.section) || {label:this.section};
        },
        title() { return this.item().label; },
        subtitle() { return this.section === 'dashboard' ? 'Unternehmenssteuerung' : 'Modul · ' + (this.item().ep||''); },
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
            this.loading = true; this.error = '';
            const url = new URL(location.href); url.searchParams.set('tenant', this.tenant);
            history.replaceState(null,'',url);
            if (this.section === 'dashboard') {
                this.api('/api/v1/metrics').then(r => r.ok ? r.json() : (this.error='HTTP '+r.status, null))
                    .then(d => { this.metrics = d; this.loading = false; });
                this.api('/api/v1/insights').then(r => r.ok ? r.json() : [])
                    .then(d => this.insights = d.filter(i => i.code !== 'all_clear'));
                return;
            }
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
        openCreate() { this.form = {}; this.formError = ''; this.showCreate = true; },
        submitCreate() {
            this.formError = '';
            const body = {};
            this.createFields().forEach(f => { if (this.form[f.key] !== undefined && this.form[f.key] !== '') body[f.key] = this.form[f.key]; });
            this.api(this.item().ep, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)})
                .then(r => {
                    if (!r.ok) { this.formError = 'HTTP '+r.status+' — Pflichtfelder fehlen?'; return null; }
                    this.showCreate = false; this.loadSection(); return r.json();
                });
        },
        deleteRow(row) {
            if (!row || !row.id || !confirm('Wirklich löschen?')) return;
            this.api(this.item().ep + '/' + row.id, {method: 'DELETE'}).then(() => { this.detail = null; this.loadSection(); });
        },
        label(c) { return c.replace(/_/g,' ').replace(/^\w/, s => s.toUpperCase()); },
        cell(row, c) {
            let v = row[c];
            if (v === null || v === undefined) return '—';
            if (typeof v === 'boolean') return v ? 'Ja' : 'Nein';
            if (c === 'status' || c === 'severity' || c === 'type') {
                const map = {open:'#CA8A04',critical:'#A6362E',high:'#A6362E',warning:'#CA8A04',done:'#2E7D5B',approved:'#2E7D5B',active:'#2E7D5B',info:'#5B6B7E'};
                const col = map[String(v).toLowerCase()] || '#5B6B7E';
                return `<span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full" style="background:${col}"></span>${v}</span>`;
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
