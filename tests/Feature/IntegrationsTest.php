<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\DataPlatform\Models\IntegrationSource;
use Modules\DataPlatform\Models\MetricSnapshot;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function acting(Tenant $tenant): User
    {
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());

        return $user;
    }

    public function test_integration_source_crud_and_webhook_ingest(): void
    {
        $tenant = Tenant::create(['name' => 'Wh GmbH']);
        $this->acting($tenant);

        $created = $this->postJson('/api/v1/integrations', ['name' => 'Shop A'], ['X-Tenant' => $tenant->id]);
        $created->assertCreated();
        $token = $created->json('token');
        $this->assertStringContainsString('/api/v1/webhooks/'.$token, $created->json('webhook_url'));

        // Webhook ohne Auth — Token identifiziert Quelle + Mandant
        $res = $this->postJson('/api/v1/webhooks/'.$token, [
            'type' => 'order',
            'subject' => ['type' => 'order', 'id' => '42', 'title' => 'Bestellung 42'],
            'total' => 199.0,
        ]);
        $res->assertCreated();

        $event = DB::table('stored_events')->where('event_properties->type', 'webhook.order')->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame($tenant->id, json_decode($event->meta_data, true)['tenant_id']);
        $this->assertSame('Bestellung 42', json_decode($event->event_properties, true)['subject']['title']);
        $this->assertEquals(199.0, json_decode($event->event_properties, true)['payload']['body']['total']);

        // last_received_at gesetzt
        $this->assertNotNull(IntegrationSource::find($created->json('id'))->last_received_at);

        // deaktivierte Quelle → 404
        $this->putJson('/api/v1/integrations/'.$created->json('id'), ['active' => false], ['X-Tenant' => $tenant->id])->assertOk();
        $this->postJson('/api/v1/webhooks/'.$token, ['type' => 'order'])->assertNotFound();
    }

    public function test_webhook_unknown_token_404(): void
    {
        $this->postJson('/api/v1/webhooks/wh_nope', [])->assertNotFound();
    }

    public function test_integrations_require_auth_and_tenant(): void
    {
        $this->getJson('/api/v1/integrations')->assertStatus(400); // ohne X-Tenant keine Identifikation
    }

    public function test_connector_crud_and_pull(): void
    {
        Http::fake(['https://api.example.com/feed' => Http::response('{"orders":3}', 200)]);

        $tenant = Tenant::create(['name' => 'Conn GmbH']);
        $this->acting($tenant);

        $created = $this->postJson('/api/v1/connectors', [
            'name' => 'Shop-Feed',
            'url' => 'https://api.example.com/feed',
            'interval_minutes' => 60,
        ], ['X-Tenant' => $tenant->id]);
        $created->assertCreated();
        $id = $created->json('id');

        $this->postJson("/api/v1/connectors/{$id}/run", [], ['X-Tenant' => $tenant->id])->assertOk();

        $connector = DB::table('integration_connectors')->find($id);
        $this->assertSame('http_200', $connector->last_status);
        $this->assertNotNull($connector->last_run_at);

        // Pull-Ergebnis landet im Data Lake + Event Store
        $this->assertSame(1, DB::table('data_objects')->where('tenant_id', $tenant->id)->count());
        $event = DB::table('stored_events')->where('event_properties->type', 'connector.pulled')->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertSame('Shop-Feed', json_decode($event->event_properties, true)['subject']['title']);

        // Fehlerfall: Status error, kein Objekt
        Http::fake(['https://api.example.com/bad' => Http::response('', 500)]);
        $bad = $this->postJson('/api/v1/connectors', ['name' => 'Bad', 'url' => 'https://api.example.com/bad'], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/connectors/'.$bad->json('id').'/run', [], ['X-Tenant' => $tenant->id])->assertOk();
        $this->assertSame('http_500', DB::table('integration_connectors')->find($bad->json('id'))->last_status);

        $this->deleteJson("/api/v1/connectors/{$id}", [], ['X-Tenant' => $tenant->id])->assertNoContent();
    }

    public function test_connector_requires_tenant(): void
    {
        $this->getJson('/api/v1/connectors')->assertStatus(400);
    }

    public function test_metrics_ingest_maps_webhook_events_to_ext_metrics(): void
    {
        $tenant = Tenant::create(['name' => 'Ingest GmbH']);
        $this->acting($tenant);

        $token = $this->postJson('/api/v1/integrations', ['name' => 'InvoiceMaker'], ['X-Tenant' => $tenant->id])->json('token');
        $post = fn (array $body) => $this->postJson('/api/v1/webhooks/'.$token, $body)->assertCreated();

        $post(['type' => 'invoice_paid', 'amount' => 10000]);
        $post(['type' => 'invoice_paid', 'amount' => 2500]);
        $post(['type' => 'expense_created', 'amount' => 4000]);
        $post(['type' => 'expense_created', 'amount' => 800, 'category' => 'marketing']);
        $post(['type' => 'lead_created']);
        $post(['type' => 'lead_created']);
        $post(['type' => 'lead_qualified']);
        $post(['type' => 'offer_created', 'value' => 20000, 'probability' => 50]);
        $post(['type' => 'payment_received', 'amount' => 9000]);

        $this->artisan('metrics:ingest')->assertSuccessful();

        $snap = fn (string $metric) => (float) MetricSnapshot::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('metric', $metric)->value('value');

        $this->assertEquals(12500.0, $snap('ext_revenue_paid'));
        $this->assertEquals(4800.0, $snap('ext_costs'));
        $this->assertEquals(800.0, $snap('ext_costs_marketing'));
        $this->assertEquals(2.0, $snap('ext_leads'));
        $this->assertEquals(1.0, $snap('ext_mql'));
        $this->assertEquals(10000.0, $snap('ext_pipeline_value'));
        $this->assertEquals(9000.0, $snap('ext_cash_in'));

        // idempotent: zweiter Lauf überschreibt dieselben Snapshots, kein Doppel-Count
        $this->artisan('metrics:ingest')->assertSuccessful();
        $this->assertEquals(12500.0, $snap('ext_revenue_paid'));
    }
}
