<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiCoachTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_creates_analysis_and_notifies_admins(): void
    {
        $tenant = Tenant::create(['name' => 'Coach GmbH']);
        $admin = User::factory()->create();
        tenancy()->initialize($tenant);
        $admin->assignRole('holding');

        Artisan::call('ai:coach', ['--tenant' => $tenant->id]);

        $analysis = DB::table('ai_analyses')->where('tenant_id', $tenant->id)->latest('id')->first();
        $this->assertNotNull($analysis);
        $this->assertSame('coach', $analysis->kind);
        $this->assertSame('completed', $analysis->status);

        $notif = DB::table('notifications')->where('notifiable_id', $admin->id)->latest('created_at')->first();
        $this->assertNotNull($notif);
        $data = json_decode($notif->data, true);
        $this->assertSame('ki_coach', $data['kind']);
        $this->assertStringContainsString('KI-Coach', $data['title']);
    }
}
