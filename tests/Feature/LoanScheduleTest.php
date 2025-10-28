<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $client;
    protected $loan;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario (prestamista)
        $this->user = User::factory()->create();

        // Crear cliente asociado
        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Crear préstamo
        $this->loan = Loan::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);
    }

    /** @test */
    public function un_usuario_autenticado_puede_crear_un_cronograma()
    {
        $this->actingAs($this->user, 'sanctum');

        $data = [
            'loan_id' => $this->loan->id,
            'scheduled_date' => now()->addDays(10)->toDateString(),
            'amount_due' => 50000,
        ];

        $response = $this->postJson('/api/schedules', $data);

        $response->assertStatus(201)
                 ->assertJson([
                     'message' => 'Cuota programada correctamente',
                     'schedule' => ['amount_due' => 50000],
                 ]);

        $this->assertDatabaseHas('loan_schedules', $data);
    }

    /** @test */
    public function puede_listar_sus_cronogramas()
    {
        LoanSchedule::factory()->count(3)->create([
            'loan_id' => $this->loan->id,
        ]);

        $this->actingAs($this->user, 'sanctum');
        $response = $this->getJson('/api/schedules');

        $response->assertStatus(200)
                 ->assertJsonCount(3);
    }

    /** @test */
    public function puede_actualizar_un_cronograma()
    {
        $schedule = LoanSchedule::factory()->create([
            'loan_id' => $this->loan->id,
            'status' => 'pendiente',
        ]);

        $this->actingAs($this->user, 'sanctum');

        $response = $this->putJson("/api/schedules/{$schedule->id}", [
            'status' => 'pagado',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Cronograma actualizado correctamente',
                     'schedule' => ['status' => 'pagado'],
                 ]);

        $this->assertDatabaseHas('loan_schedules', ['status' => 'pagado']);
    }

    /** @test */
    public function puede_eliminar_un_cronograma()
    {
        $schedule = LoanSchedule::factory()->create([
            'loan_id' => $this->loan->id,
        ]);

        $this->actingAs($this->user, 'sanctum');
        $response = $this->deleteJson("/api/schedules/{$schedule->id}");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Cuota eliminada correctamente']);

        $this->assertDatabaseMissing('loan_schedules', ['id' => $schedule->id]);
    }
}
