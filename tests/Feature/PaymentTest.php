<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Loan;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_prestamista_puede_registrar_un_pago()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/payments', [
            'loan_id' => $loan->id,
            'date' => now()->toDateString(),
            'amount' => 200000,
            'notes' => 'Abono inicial',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payments', ['amount' => 200000]);
    }

    public function test_puede_listar_pagos_de_un_prestamo()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);
        Payment::factory()->count(3)->create(['loan_id' => $loan->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/loans/{$loan->id}/payments");

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }

    public function test_no_puede_registrar_pago_sin_monto()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create(['user_id' => $user->id, 'client_id' => $client->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/payments', [
            'loan_id' => $loan->id,
        ]);

        $response->assertStatus(422);
    }
}
