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

            <div x-show="metrics" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <template x-for="m in cards" :key="m.key">
                    <div class="bg-white shadow-sm sm:rounded-lg p-4">
                        <div class="text-xs uppercase text-gray-500" x-text="m.label"></div>
                        <div class="text-2xl font-bold" x-text="metric(m.key)"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function dashboard() {
            return {
                tenant: '',
                metrics: null,
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
                    fetch('/api/v1/metrics', {headers: {
                        'Authorization': 'Bearer {{ $apiToken }}',
                        'X-Tenant': this.tenant,
                        'Accept': 'application/json',
                    }}).then(r => {
                        if (!r.ok) { this.error = 'HTTP '+r.status+' — keine Berechtigung?'; this.metrics = null; return null; }
                        return r.json();
                    }).then(d => { if (d) this.metrics = d; });
                }
            }
        }
    </script>
</x-app-layout>
