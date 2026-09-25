<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg tracking-tight text-[#0B0B0F]">Dashboard</h2>
            <span class="text-xs text-[#5B6B7E]">Unternehmenssteuerung</span>
        </div>
    </x-slot>

    <div class="py-8" x-data="dashboard()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Tenant selector --}}
            <div class="bg-[#0B0B0F] rounded-xl px-5 py-4 flex flex-wrap items-center gap-4">
                <label class="text-[13px] font-medium text-[#FACC15]">Mandant</label>
                <select x-model="tenant" @change="load()"
                        class="rounded-lg bg-[#1A1A1F] border-[#2A2A31] text-white text-sm focus:border-[#FACC15] focus:ring-[#FACC15]/30 min-w-56">
                    <option value="">— wählen —</option>
                    @foreach($tenants as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <span x-show="error" x-text="error" class="text-[13px] text-[#F87171]"></span>
            </div>

            {{-- Empty state until a tenant is chosen --}}
            <div x-show="!metrics && !error" class="bg-white border border-dashed border-[#D6DEE9] rounded-xl px-6 py-12 text-center">
                <p class="text-sm text-[#5B6B7E]">Wählen Sie oben einen Mandanten, um Kennzahlen, Insights und Team anzuzeigen.</p>
            </div>

            {{-- Insights --}}
            <div x-show="insights.length" class="space-y-2">
                <template x-for="i in insights" :key="i.code">
                    <div class="flex items-start gap-3 rounded-lg border bg-white px-4 py-3 text-sm"
                         :class="{
                            'border-[#A6362E]/40': i.severity === 'critical',
                            'border-[#CA8A04]/50': i.severity === 'warning',
                            'border-[#D6DEE9]': i.severity === 'info'
                         }">
                        <span class="mt-0.5 inline-block h-2 w-2 rounded-full shrink-0"
                              :class="{
                                'bg-[#A6362E]': i.severity === 'critical',
                                'bg-[#CA8A04]': i.severity === 'warning',
                                'bg-[#5B6B7E]': i.severity === 'info'
                              }"></span>
                        <span class="text-[#1A2433]" x-text="i.message"></span>
                    </div>
                </template>
            </div>

            {{-- KPI cards --}}
            <div x-show="metrics" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <template x-for="m in cards" :key="m.key">
                    <div class="bg-white border border-[#E4E9F0] rounded-xl px-5 py-4">
                        <div class="text-[11px] font-medium text-[#5B6B7E]" x-text="m.label"></div>
                        <div class="mt-1.5 font-mono text-2xl font-semibold tracking-tight text-[#0B0B0F]" x-text="metric(m.key)"></div>
                        <div class="mt-2 h-0.5 w-8 rounded-full bg-[#FACC15]"></div>
                    </div>
                </template>
            </div>

            {{-- Team & Rollen --}}
            <div x-show="users" class="bg-white border border-[#E4E9F0] rounded-xl p-5">
                <h3 class="text-sm font-semibold text-[#0B0B0F] mb-1">Team &amp; Rollen</h3>
                <p class="text-[13px] text-[#5B6B7E] mb-4">Rollen gelten pro Mandant (Dokument C).</p>
                <div class="h-px bg-gradient-to-r from-[#FACC15] via-[#E4E9F0] to-transparent mb-1"></div>
                <template x-for="u in users" :key="u.id">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 border-t border-[#E4E9F0] first:border-t-0">
                        <div class="w-64 min-w-0">
                            <div class="text-sm font-medium text-[#1A2433] truncate" x-text="u.name"></div>
                            <div class="text-xs text-[#5B6B7E] truncate" x-text="u.email"></div>
                        </div>
                        <template x-for="r in roles" :key="r">
                            <label class="text-xs flex items-center gap-1.5 text-[#42536A]">
                                <input type="checkbox" :checked="u.roles.includes(r)"
                                       @change="toggleRole(u, r)" class="rounded border-[#D6DEE9] text-[#CA8A04] focus:ring-[#CA8A04]/40">
                                <span x-text="r"></span>
                            </label>
                        </template>
                        <button @click="saveRoles(u)"
                                class="ml-auto text-xs px-3 py-1.5 bg-[#0B0B0F] text-white rounded-lg hover:bg-[#1A1A1F] transition">
                            Speichern
                        </button>
                    </div>
                </template>
                <div x-show="users && users.length === 0" class="text-sm text-[#5B6B7E]">
                    Keine Benutzer mit Rolle in diesem Mandant.
                </div>
                <span x-show="roleMsg" x-text="roleMsg" class="text-xs text-[#5B6B7E]"></span>
            </div>
        </div>
    </div>

    <script>
        function dashboard() {
            return {
                tenant: '',
                metrics: null,
                users: null,
                roles: [],
                roleMsg: '',
                insights: [],
                error: '',
                cards: [
                    {key:'companies',label:'Unternehmen'},
                    {key:'persons',label:'Personen'},
                    {key:'documents',label:'Dokumente'},
                    {key:'tasks_open',label:'Offene Aufgaben'},
                    {key:'instructions',label:'Unterweisungen'},
                    {key:'compliance_rate',label:'Compliance %'},
                    {key:'deadlines_open',label:'Offene Fristen'},
                    {key:'risk_high',label:'Hohe Risiken'},
                    {key:'tenders_open',label:'Offene Ausschreibungen'},
                    {key:'expert_profiles',label:'Experten'},
                    {key:'questions',label:'Fragen'},
                    {key:'inspections',label:'Prüfungen'},
                ],
                metric(k) {
                    const v = this.metrics && this.metrics[k];
                    return v ? parseFloat(v.value) : '–';
                },
                load() {
                    if (!this.tenant) return;
                    this.error = '';
                    this.users = null;
                    this.insights = [];
                    this.api('/api/v1/metrics').then(r => {
                        if (!r.ok) { this.error = 'HTTP '+r.status+' — keine Berechtigung?'; this.metrics = null; return null; }
                        return r.json();
                    }).then(d => { if (d) this.metrics = d; });
                    this.api('/api/v1/insights').then(r => r.ok ? r.json() : [])
                        .then(d => { this.insights = d.filter(i => i.code !== 'all_clear'); });
                    this.loadTeam();
                },
                api(path, opts) {
                    opts = opts || {};
                    opts.headers = Object.assign({
                        'Authorization': 'Bearer {{ $apiToken }}',
                        'X-Tenant': this.tenant,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    }, opts.headers || {});
                    return fetch(path, opts);
                },
                loadTeam() {
                    this.api('/api/v1/roles').then(r => r.ok ? r.json() : [])
                        .then(rs => { this.roles = rs.map(x => x.name); });
                    this.api('/api/v1/users').then(r => r.ok ? r.json() : [])
                        .then(us => {
                            Promise.all(us.map(u =>
                                this.api('/api/v1/users/'+u.id+'/roles')
                                    .then(r => r.ok ? r.json() : {roles: []})
                                    .then(d => Object.assign(u, {roles: d.roles}))
                            )).then(() => { this.users = us; });
                        });
                },
                toggleRole(u, r) {
                    u.roles = u.roles.includes(r) ? u.roles.filter(x => x !== r) : u.roles.concat(r);
                },
                saveRoles(u) {
                    this.roleMsg = '';
                    this.api('/api/v1/users/'+u.id+'/roles', {
                        method: 'PUT',
                        body: JSON.stringify({roles: u.roles}),
                    }).then(r => {
                        this.roleMsg = r.ok ? 'Gespeichert: '+u.name : 'Fehler HTTP '+r.status+' (roles.manage fehlt?)';
                    });
                }
            }
        }
    </script>
</x-app-layout>
