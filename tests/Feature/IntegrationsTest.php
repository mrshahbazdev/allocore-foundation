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

    public function test_connect_lists_tenants_and_creates_source(): void
    {
        $tenant = Tenant::create(['name' => 'Zahntechnik']);
        $user = User::factory()->create(['password' => bcrypt('geheim123')]);
        tenancy()->initialize($tenant);
        $user->assignRole('holding');

        // Schritt 1: Zugangsdaten → Mandantenliste
        $res = $this->postJson('/api/v1/connect', [
            'email' => $user->email,
            'password' => 'geheim123',
        ]);
        $res->assertOk();
        $res->assertJsonPath('tenants.0.id', $tenant->id);

        // Schritt 2: tenant_id → Webhook-Quelle + URL
        $res = $this->postJson('/api/v1/connect', [
            'email' => $user->email,
            'password' => 'geheim123',
            'tenant_id' => $tenant->id,
            'source_name' => 'Allocore Suite',
        ]);
        $res->assertCreated();
        $this->assertStringContainsString('/api/v1/webhooks/wh_', $res->json('webhook_url'));
        $this->assertSame($tenant->id, IntegrationSource::find($res->json('source_id'))->tenant_id);

        // fremder Mandant → 403
        $other = Tenant::create(['name' => 'Fremd GmbH']);
        $this->postJson('/api/v1/connect', [
            'email' => $user->email,
            'password' => 'geheim123',
            'tenant_id' => $other->id,
        ])->assertForbidden();

        // falsches Passwort → 422
        $this->postJson('/api/v1/connect', [
            'email' => $user->email,
            'password' => 'falsch',
        ])->assertStatus(422);
    }

    public function test_connect_authorize_oauth_flow(): void
    {
        $tenant = Tenant::create(['name' => 'Zahntechnik']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');

        $redirectUri = 'https://allocore.de/admin/allocore/callback';

        // GET /connect/authorize — zeigt Mandanten des Nutzers
        $res = $this->actingAs($user)->get('/connect/authorize?'.http_build_query([
            'redirect_uri' => $redirectUri,
            'state' => 'abc123',
        ]));
        $res->assertOk();
        $res->assertSee('Zahntechnik');

        // POST /connect/authorize — erzeugt Code und leitet zurück
        $res = $this->actingAs($user)->post('/connect/authorize', [
            'redirect_uri' => $redirectUri,
            'state' => 'abc123',
            'tenant_id' => $tenant->id,
        ]);
        $res->assertRedirect();
        parse_str(parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('abc123', $q['state']);
        $this->assertStringStartsWith('ac_', $q['code']);

        // Fremder Mandant → 403
        $other = Tenant::create(['name' => 'Fremd GmbH']);
        $this->actingAs($user)->post('/connect/authorize', [
            'redirect_uri' => $redirectUri,
            'tenant_id' => $other->id,
        ])->assertForbidden();

        // POST /api/v1/connect/exchange — Code → Webhook-URL (einmalig)
        $res = $this->postJson('/api/v1/connect/exchange', ['code' => $q['code']]);
        $res->assertCreated();
        $this->assertStringContainsString('/api/v1/webhooks/wh_', $res->json('webhook_url'));
        $this->assertSame($tenant->id, IntegrationSource::find($res->json('source_id'))->tenant_id);

        // Zweite Nutzung desselben Codes → 422
        $this->postJson('/api/v1/connect/exchange', ['code' => $q['code']])->assertStatus(422);
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

        $post(['type' => 'order_created', 'order_id' => 'o1']);
        $post(['type' => 'order_done', 'order_id' => 'o1']);
        $post(['type' => 'order_done_on_time', 'order_id' => 'o1']);
        $post(['type' => 'order_lead_time', 'days' => 6]);
        $post(['type' => 'order_complaint', 'order_id' => 'o2']);

        $this->artisan('metrics:ingest')->assertSuccessful();
        $this->assertEquals(1.0, $snap('ext_orders'));
        $this->assertEquals(1.0, $snap('ext_orders_done'));
        $this->assertEquals(1.0, $snap('ext_orders_on_time'));
        $this->assertEquals(6.0, $snap('ext_lead_time_days'));
        $this->assertEquals(1.0, $snap('ext_complaints'));

        // idempotent: zweiter Lauf überschreibt dieselben Snapshots, kein Doppel-Count
        $this->artisan('metrics:ingest')->assertSuccessful();
        $this->assertEquals(12500.0, $snap('ext_revenue_paid'));
    }
}
