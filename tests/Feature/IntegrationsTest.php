<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\DataPlatform\Models\IntegrationSource;
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
}
