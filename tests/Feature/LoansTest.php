<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PaymentReceived;
use Tests\TestCase;

class LoansTest extends TestCase
{
    use RefreshDatabase;

    private User $lender;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-09 09:00');
        $this->lender = User::create(['name' => 'Alan', 'email' => 'a@a.com', 'password' => 'secret123', 'webhook_url' => 'https://afdeveloper.test/webhooks/creditrack']);
        $this->lender->forceFill(['webhook_secret' => 'secreto'])->save();
        $this->token = $this->lender->createToken('t', ['lender'])->plainTextToken;
    }

    private function loan(): array
    {
        $client = $this->lender->clients()->create(['name' => 'Pedro', 'document' => '111', 'phone' => '3001234567']);

        return $this->withToken($this->token)->postJson('/api/loans', [
            'client_id' => $client->id, 'amount' => 1000000, 'interest_rate' => 10, 'interest_type' => 'simple',
            'start_date' => '2026-10-01', 'due_date' => '2026-12-01', 'payment_frequency' => 'mensual',
        ])->assertCreated()->json('loan');
    }

    public function test_el_saldo_incluye_intereses_y_el_prestamo_no_se_cierra_antes_de_tiempo(): void
    {
        Bus::fake([SendWebhook::class]);
        Notification::fake();
        $loan = $this->loan();
        $this->assertEquals(1200000, $loan['total_amount']);

        $this->withToken($this->token)->postJson('/api/payments', ['loan_id' => $loan['id'], 'amount' => 1000000, 'date' => '2026-10-09'])
            ->assertOk()->assertJsonPath('loan.status', 'activo')->assertJsonPath('loan.balance', 200000);

        $this->withToken($this->token)->postJson('/api/payments', ['loan_id' => $loan['id'], 'amount' => 300000, 'date' => '2026-10-09'])
            ->assertUnprocessable(); // supera el saldo

        $this->withToken($this->token)->postJson('/api/payments', ['loan_id' => $loan['id'], 'amount' => 200000, 'date' => '2026-10-09'])
            ->assertOk()->assertJsonPath('loan.status', 'pagado');

        Notification::assertSentTimes(PaymentReceived::class, 2);
        Bus::assertDispatched(SendWebhook::class, fn ($job) => $job->payload['event'] === 'payment.created');
    }

    public function test_no_se_puede_cambiar_el_monto_de_un_prestamo_con_pagos(): void
    {
        Bus::fake();
        Notification::fake();
        $loan = $this->loan();
        $this->withToken($this->token)->postJson('/api/payments', ['loan_id' => $loan['id'], 'amount' => 100000, 'date' => '2026-10-09']);

        $this->withToken($this->token)->putJson("/api/loans/{$loan['id']}", ['amount' => 5000000])->assertUnprocessable();
        $this->withToken($this->token)->putJson("/api/loans/{$loan['id']}", ['notes' => 'ok'])->assertOk();
    }

    public function test_el_webhook_va_firmado(): void
    {
        Http::fake();
        $this->loan(); // QUEUE_CONNECTION=sync en tests: se envía de inmediato

        Http::assertSent(function ($request) {
            parse_str(str_replace(',', '&', $request->header('X-CrediTrack-Signature')[0]), $p);

            return $request->url() === 'https://afdeveloper.test/webhooks/creditrack'
                && hash_equals(hash_hmac('sha256', $p['t'].'.'.$request->body(), 'secreto'), $p['v1'])
                && $request['event'] === 'loan.created';
        });
    }

    public function test_la_cobranza_diaria_esta_programada(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('creditrack:collections');
        $this->artisan('creditrack:collections --dry-run')->assertSuccessful();
    }
}
