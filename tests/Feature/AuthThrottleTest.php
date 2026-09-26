<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_is_throttled_after_six_attempts_per_minute(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/register', [
                'name' => 'T',
                'email' => "t{$i}@x.de",
                'password' => 'Passw0rd12345',
                'password_confirmation' => 'Passw0rd12345',
            ]);
            $this->post('/logout');
        }

        $this->post('/register', [
            'name' => 'T',
            'email' => 't7@x.de',
            'password' => 'Passw0rd12345',
            'password_confirmation' => 'Passw0rd12345',
        ])->assertStatus(429);
    }

    public function test_forgot_password_is_throttled(): void
    {
        for ($i = 0; $i < 7; $i++) {
            $res = $this->post('/forgot-password', ['email' => "t{$i}@x.de"]);
        }

        $res->assertStatus(429);
    }
}
