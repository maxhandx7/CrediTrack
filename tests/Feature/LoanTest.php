<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Loan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    public function test_prestamista_puede_crear_prestamo()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/loans', [
            'client_id' => $client->id,
            'amount' => 1000000,
            'interest_rate' => 5,
            'interest_type' => 'simple',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'payment_frequency' => 'mensual',
            'status' => 'activo',
            'notes' => 'Préstamo de prueba',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('loans', ['amount' => 1000000]);
    }

    public function test_puede_ver_prestamos_de_un_cliente()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        Loan::factory()->count(2)->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/{$client->id}/loans");

        $response->assertStatus(200)
                 ->assertJsonCount(2);
    }

    public function test_no_puede_crear_prestamo_sin_cliente()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/loans', [
            'amount' => 500000,
        ]);

        $response->assertStatus(422);
    }
}
