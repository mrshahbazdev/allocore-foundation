<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/api/v1/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.migrations', 'ok');
    }

    public function test_version_endpoint_returns_build_info(): void
    {
        $response = $this->get('/api/v1/version');

        $response->assertStatus(200)
            ->assertJsonPath('platform', 'allocore-foundation')
            ->assertJsonStructure(['app_version', 'laravel', 'php']);
    }
}
