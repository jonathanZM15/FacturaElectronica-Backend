<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_login_and_me()
    {
        // register
        $resp = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $resp->assertStatus(201)->assertJsonStructure(['user', 'token']);

        // login
        $resp2 = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $resp2->assertStatus(200)->assertJsonStructure(['user', 'token']);

        // use token from login for protected routes
        $token = $resp2->json('token');

        // token should be persisted in the database
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // me
        $resp3 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user');

        $resp3->assertStatus(200)->assertJsonFragment(['email' => 'test@example.com']);

        // logout (with token)
        $resp4 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $resp4->assertStatus(200)->assertJson(['message' => 'Sesión cerrada']);

        // using same token after logout should fail
        $resp5 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user');

        $resp5->assertStatus(401);

        // ensure tokens removed in DB
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
