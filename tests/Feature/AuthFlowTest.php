<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_and_logout_revokes_token()
    {
        // register
        $resp = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test2@example.com',
            'password' => 'secret123',
        ]);

        $resp->assertStatus(201)->assertJsonStructure(['user', 'token']);

        // login
        $resp2 = $this->postJson('/api/login', [
            'email' => 'test2@example.com',
            'password' => 'secret123',
        ]);

        $resp2->assertStatus(200)->assertJsonStructure(['user', 'token']);

        $token = $resp2->json('token');

        // token works
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user')
            ->assertStatus(200);

        // logout
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout')
            ->assertStatus(200);

        // token should no longer work
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user')
            ->assertStatus(401);
    }
}
