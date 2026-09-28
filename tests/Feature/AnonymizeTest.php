<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Person;
use Modules\DataPlatform\Models\AnonymizedRecord;
use Tests\TestCase;

class AnonymizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_creates_pseudonymized_twins_without_pii(): void
    {
        $tenant = Tenant::create(['name' => 'A GmbH']);
        $user = User::factory()->create(['name' => 'Max Muster', 'email' => 'max@x.de']);
        tenancy()->initialize($tenant);
        $user->assignRole('holding');

        Person::create([
            'tenant_id' => $tenant->id,
            'first_name' => 'Erika',
            'last_name' => 'Musterfrau',
            'email' => 'erika@x.de',
            'type' => 'employee',
        ]);

        $this->artisan('anonymize:sync', ['--tenant' => $tenant->id])->assertSuccessful();

        $records = AnonymizedRecord::where('tenant_id', $tenant->id)->get();
        $this->assertCount(2, $records);

        foreach ($records as $record) {
            $payload = json_encode($record->payload);
            $this->assertStringNotContainsString('max@x.de', $payload);
            $this->assertStringNotContainsString('Max Muster', $payload);
            $this->assertStringNotContainsString('erika@x.de', $payload);
            $this->assertStringNotContainsString('Erika', $payload);
            $this->assertStringNotContainsString('Musterfrau', $payload);
            // Pseudonym ist stabil und nicht rückwärts lesbar
            $this->assertSame(64, strlen($record->pseudonym));
        }

        // Idempotent: zweiter Lauf aktualisiert statt dupliziert
        $this->artisan('anonymize:sync', ['--tenant' => $tenant->id])->assertSuccessful();
        $this->assertSame(2, AnonymizedRecord::where('tenant_id', $tenant->id)->count());
    }

    public function test_index_lists_anonymized_records(): void
    {
        $tenant = Tenant::create(['name' => 'B GmbH']);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole('holding');
        Sanctum::actingAs($user->fresh());

        $this->artisan('anonymize:sync', ['--tenant' => $tenant->id])->assertSuccessful();

        $this->getJson('/api/v1/anonymized-records', ['X-Tenant' => $tenant->id])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['email', 'name']);
    }
}
