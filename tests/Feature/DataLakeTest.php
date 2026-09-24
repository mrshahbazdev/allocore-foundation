<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DataLakeTest extends TestCase
{
    use RefreshDatabase;

    private function auth(string $role = 'holding'): array
    {
        $tenant = Tenant::create(['name' => 'Lake '.$role]);
        $user = User::factory()->create();
        tenancy()->initialize($tenant);
        $user->assignRole($role);
        Sanctum::actingAs($user->fresh());
        tenancy()->end();

        return [$tenant, $user];
    }

    public function test_upload_download_delete_data_object(): void
    {
        [$tenant] = $this->auth();

        $file = UploadedFile::fake()->create('werkvertrag.pdf', 100, 'application/pdf');
        $res = $this->postJson('/api/v1/data-objects', [
            'name' => 'Werkvertrag Müller', 'category' => 'contract', 'file' => $file,
        ], ['X-Tenant' => $tenant->id]);
        $res->assertCreated()->assertJsonPath('category', 'contract');

        $id = $res->json('id');
        $this->getJson("/api/v1/data-objects/{$id}", ['X-Tenant' => $tenant->id])->assertOk();
        $this->getJson("/api/v1/data-objects/{$id}/download", ['X-Tenant' => $tenant->id])->assertOk();

        $this->getJson('/api/v1/data-objects?category=contract', ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonCount(1, 'data');

        $this->deleteJson("/api/v1/data-objects/{$id}", [], ['X-Tenant' => $tenant->id])->assertNoContent();
        $this->getJson("/api/v1/data-objects/{$id}", ['X-Tenant' => $tenant->id])->assertNotFound();
    }

    public function test_cross_tenant_access_denied(): void
    {
        [$tenantA, $user] = $this->auth();
        $tenantB = Tenant::create(['name' => 'Lake B']);

        $res = $this->postJson('/api/v1/data-objects', [
            'name' => 'X', 'file' => UploadedFile::fake()->create('x.pdf', 10),
        ], ['X-Tenant' => $tenantA->id]);
        $id = $res->json('id');

        Sanctum::actingAs(User::find($user->id));
        $this->getJson("/api/v1/data-objects/{$id}", ['X-Tenant' => $tenantB->id])->assertNotFound();

        tenancy()->initialize($tenantB);
        User::find($user->id)->assignRole('auditor');
        Sanctum::actingAs(User::find($user->id));
        tenancy()->end();
        $this->getJson("/api/v1/data-objects/{$id}", ['X-Tenant' => $tenantB->id])->assertNotFound();
    }
}
