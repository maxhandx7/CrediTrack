<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_cliente_no_puede_ver_los_datos_de_un_prestamista(): void
    {
        // El bug original: el cliente #1 usaba Auth::id() = 1 y veía todo lo del usuario #1.
        $lender = User::create(['name' => 'Alan', 'email' => 'a@a.com', 'password' => 'secret123']);
        $client = $lender->clients()->create(['name' => 'Pedro', 'document' => '111']);
        $this->assertSame($lender->id, $client->id);

        $token = $client->createToken('client_token', ['client'])->plainTextToken;

        foreach (['/api/clients', '/api/loans', '/api/payments', '/api/schedules', '/api/analytics/export'] as $url) {
            $this->withToken($token)->getJson($url)->assertForbidden();
        }
    }

    public function test_el_registro_esta_cerrado_por_defecto(): void
    {
        $this->postJson('/api/auth/user/register', ['name' => 'X', 'email' => 'x@x.com', 'password' => 'secret123'])
            ->assertForbidden();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_el_login_tiene_limite_de_intentos(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/auth/user/login', ['email' => 'a@a.com', 'password' => 'mala'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/user/login', ['email' => 'a@a.com', 'password' => 'mala'])->assertTooManyRequests();
    }

    public function test_la_cedula_sola_ya_no_da_acceso(): void
    {
        Http::fake();
        $lender = User::create(['name' => 'Alan', 'email' => 'a@a.com', 'password' => 'secret123']);
        $lender->clients()->create(['name' => 'Pedro', 'document' => '111', 'phone' => '3001234567']);

        $this->postJson('/api/auth/client/login', ['document' => '111'])->assertNotFound();
        $this->postJson('/api/auth/client/verify', ['document' => '111', 'code' => '000000'])->assertUnprocessable();
    }

    public function test_acceso_de_cliente_con_codigo_por_whatsapp(): void
    {
        $sent = null;
        Http::fake(function ($request) use (&$sent) {
            $sent = $request->data();

            return Http::response(['id' => 'ok']);
        });

        $lender = User::create(['name' => 'Alan', 'email' => 'a@a.com', 'password' => 'secret123']);
        $lender->clients()->create(['name' => 'Pedro', 'document' => '111', 'phone' => '300 123 4567']);

        $this->postJson('/api/auth/client/request-code', ['document' => '111'])->assertOk();
        $this->assertSame('573001234567@c.us', $sent['chatId']);
        preg_match('/\*(\d{6})\*/', $sent['text'], $m);

        $token = $this->postJson('/api/auth/client/verify', ['document' => '111', 'code' => $m[1]])
            ->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/client/loans')->assertOk();
        // El código es de un solo uso.
        $this->postJson('/api/auth/client/verify', ['document' => '111', 'code' => $m[1]])->assertUnprocessable();
    }

    public function test_cedula_inexistente_responde_igual_y_no_envia_nada(): void
    {
        Http::fake();

        $this->postJson('/api/auth/client/request-code', ['document' => '999'])->assertOk()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Si la cédula está registrada'));
        Http::assertNothingSent();
    }
}
