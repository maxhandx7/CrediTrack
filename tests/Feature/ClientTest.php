<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_prestamista_puede_crear_un_cliente()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/clients', [
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'phone' => '3001234567',
            'document' => '123456789',
            'address' => 'Calle Falsa 123',
            'notes' => 'Cliente puntual',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('clients', ['name' => 'Juan Pérez']);
    }

    public function test_no_puede_crear_cliente_sin_nombre()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/clients', [
            'email' => 'juan@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_puede_listar_sus_clientes()
    {
        $user = User::factory()->create();
        Client::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/clients');

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }
}
