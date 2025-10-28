<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_prestamista_puede_iniciar_sesion_con_email_y_password()
    {
        $user = User::factory()->create([
            'password' => bcrypt('17964290'),
        ]);

        $response = $this->postJson('/api/auth/user/login', [
            'email' => $user->email,
            'password' => '17964290',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user']);
    }

    public function test_login_con_credenciales_incorrectas_falla()
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/auth/user/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);
        $response->assertStatus(401);
    }

    public function test_cliente_puede_iniciar_sesion_con_documento()
    {
        $client = Client::factory()->create([
            'document' => '123456789',
        ]);

        $response = $this->postJson('/api/auth/client/login', [
            'document' => '123456789',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'client']);
    }
}
