<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ALLOCORE Dashboard</h2>
    </x-slot>

    <div class="py-8" x-data="dashboard()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-4 flex items-center gap-4">
                <label class="text-sm font-medium text-gray-700">Mandant:</label>
                <select x-model="tenant" @change="load()" class="rounded-md border-gray-300 text-sm">
                    <option value="">— wählen —</option>
                    @foreach($tenants as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <span x-show="error" x-text="error" class="text-sm text-red-600"></span>
            </div>

            <div x-show="insights.length" class="space-y-2">
                <template x-for="i in insights" :key="i.code">
                    <div class="border-l-4 p-3 bg-white shadow-sm sm:rounded-lg text-sm"
                         :class="{
                            'border-red-500 text-red-800': i.severity === 'critical',
                            'border-yellow-500 text-yellow-800': i.severity === 'warning',
                            'border-blue-500 text-blue-800': i.severity === 'info'
                         }"
                         x-text="i.message"></div>
                </template>
            </div>

            <div x-show="metrics" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <template x-for="m in cards" :key="m.key">
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500" x-text="m.label"></div>
                        <div class="text-2xl font-bold" x-text="metric(m.key)"></div>
                    </div>
                </template>
            </div>

            <div x-show="users" class="bg-white shadow-sm sm:rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Team &amp; Rollen</h3>
                <template x-for="u in users" :key="u.id">
                    <div class="flex items-center gap-4 py-2 border-t">
                        <div class="w-64">
                            <div class="text-sm font-medium" x-text="u.name"></div>
                            <div class="text-xs text-gray-500" x-text="u.email"></div>
                        </div>
                        <template x-for="r in roles" :key="r">
                            <label class="text-xs flex items-center gap-1">
                                <input type="checkbox" :checked="u.roles.includes(r)"
                                       @change="toggleRole(u, r)" class="rounded border-gray-300">
                                <span x-text="r"></span>
                            </label>
                        </template>
                        <button @click="saveRoles(u)"
                                class="ml-auto text-xs px-2 py-1 bg-gray-800 text-white rounded">
                            Speichern
                        </button>
                    </div>
                </template>
                <div x-show="users && users.length === 0" class="text-sm text-gray-500">
                    Keine Benutzer mit Rolle in diesem Mandant.
                </div>
                <span x-show="roleMsg" x-text="roleMsg" class="text-xs text-gray-600"></span>
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
                    fetch('/api/v1/metrics', {headers: {
                api(path) {
                    return fetch(path, {headers: {
                        'Authorization': 'Bearer {{ $apiToken }}',
                        'X-Tenant': this.tenant,
                        'Accept': 'application/json',
                    }});
                },
                load() {
                    if (!this.tenant) return;
                    this.error = '';
                    this.insights = [];
                    this.api('/api/v1/metrics').then(r => {
                        if (!r.ok) { this.error = 'HTTP '+r.status+' — keine Berechtigung?'; this.metrics = null; return null; }
                        return r.json();
                    }).then(d => { if (d) this.metrics = d; });
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
                    this.api('/api/v1/insights').then(r => r.ok ? r.json() : [])
                        .then(d => { this.insights = d.filter(i => i.code !== 'all_clear'); });
                }
            }
        }
    </script>
</x-app-layout>
