<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\PromiseStatus;
use App\Models\Company;
use App\Models\Loan;
use App\Models\PaymentPromise;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\ClientAccessCode;
use App\Services\LateFees;
use App\Services\LoanRestructurer;
use App\Services\Payments;
use App\Services\ScheduleGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SaasTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private Loan $loan;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::fake();
        $this->travelTo('2026-10-08 09:00');

        [$this->company, $this->owner] = $this->makeCompany('Créditos AF', 'a@a.com');
        $this->company->update(['settings' => ['late_fee' => ['enabled' => true, 'percent' => 5, 'grace_days' => 3]]]);
        $client = $this->company->clients()->create(['name' => 'Andrés Manyoma', 'document' => '10', 'phone' => '3001112233']);
        $this->loan = $this->company->loans()->create(['client_id' => $client->id, 'user_id' => $this->owner->id, 'amount' => 1750000,
            'interest_rate' => 0, 'interest_type' => 'simple', 'start_date' => '2026-08-03', 'due_date' => '2027-01-15', 'payment_frequency' => 'quincenal']);
        app(ScheduleGenerator::class)->generate($this->loan);
        foreach (['2026-09-03', '2026-09-18', '2026-09-20'] as $d) {
            app(Payments::class)->register($this->loan->fresh(), ['amount' => 150000, 'date' => $d], notify: false);
        }
    }

    private function makeCompany(string $name, string $email, string $role = 'owner'): array
    {
        $user = User::create(['name' => $name.' user', 'email' => $email, 'password' => 'secret123']);
        $company = Company::create(['name' => $name, 'status' => 'active', 'plan_id' => Plan::where('slug', 'profesional')->value('id')]);
        $company->users()->attach($user, ['role' => $role]);

        return [$company, $user];
    }

    private function url(string $path = ''): string
    {
        return "/admin/{$this->company->slug}".$path;
    }

    // ── Lógica de cartera (dentro de una empresa) ──

    public function test_reestructurar_deja_el_prestamo_al_dia(): void
    {
        $loan = app(LoanRestructurer::class)->restructure($this->loan->fresh(), Carbon::parse('2026-10-12'), PaymentFrequency::Biweekly, 150000);
        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertEquals(1300000, $loan->balance);
    }

    public function test_anular_mora_y_promesas(): void
    {
        app(Payments::class)->void($this->loan->payments()->latest('id')->first(), 'Duplicado');
        $this->assertEquals(1450000, $this->loan->fresh()->balance);

        $fees = app(LateFees::class);
        $this->assertGreaterThan(0, $fees->apply($this->company));
        $this->assertSame(0, $fees->apply($this->company));

        $promise = PaymentPromise::create(['company_id' => $this->company->id, 'loan_id' => $this->loan->id, 'promised_date' => '2026-10-10', 'amount' => 150000]);
        $this->travelTo('2026-10-09 10:00');
        app(Payments::class)->register($this->loan->fresh(), ['amount' => 150000, 'date' => '2026-10-09'], notify: false);
        $this->assertSame(PromiseStatus::Kept, $promise->fresh()->status);
    }

    // ── Aislamiento entre empresas ──

    public function test_una_empresa_no_ve_los_datos_de_otra(): void
    {
        [$other, $otherOwner] = $this->makeCompany('Otra Empresa', 'o@o.com');

        $this->actingAs($this->owner)->get($this->url('/loans/'.$this->loan->id))->assertOk();
        $this->flushSession();
        // El dueño de otra empresa no puede entrar a esta empresa…
        $this->actingAs($otherOwner)->get($this->url('/loans/'.$this->loan->id))->assertNotFound();
        // …ni ver el préstamo desde la suya.
        $this->actingAs($otherOwner)->get("/admin/{$other->slug}/loans/".$this->loan->id)->assertNotFound();
    }

    public function test_el_cobrador_no_accede_a_equipo_ni_configuracion(): void
    {
        $collector = User::create(['name' => 'Cobrador', 'email' => 'c@c.com', 'password' => 'secret123']);
        $this->company->users()->attach($collector, ['role' => 'collector']);

        $this->actingAs($collector)->get($this->url('/loans/'.$this->loan->id))->assertOk();
        $this->actingAs($collector)->get($this->url('/team'))->assertForbidden();
        $this->actingAs($collector)->get($this->url('/profile'))->assertForbidden();
        $this->actingAs($collector)->get($this->url('/loans/create'))->assertForbidden();
    }

    public function test_suscripcion_vencida_solo_deja_ver_suscripcion(): void
    {
        $this->company->update(['status' => 'active', 'paid_until' => today()->subDays(10)]);

        $this->actingAs($this->owner)->get($this->url('/loans'))->assertRedirect($this->url('/subscription'));
        $this->actingAs($this->owner)->get($this->url('/subscription'))->assertOk()->assertSee('suscripción está vencida');
    }

    public function test_panel_de_plataforma_solo_para_administradores(): void
    {
        $this->actingAs($this->owner)->get('/plataforma')->assertForbidden();
        $this->flushSession();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@x.com', 'password' => 'secret123', 'is_platform_admin' => true]);
        $this->actingAs($admin)->get('/plataforma')->assertOk();
        $this->actingAs($admin)->get('/plataforma/companies')->assertOk()->assertSee('Créditos AF');
    }

    // ── Privacidad del portal ──

    public function test_portal_muestra_u_oculta_el_saldo_segun_la_empresa(): void
    {
        config(['services.waha.enabled' => true, 'services.waha.url' => 'http://waha.test']);
        $client = $this->loan->client;

        $this->post('/mi-cuenta/ingresar', ['document' => '10'])->assertRedirect('/mi-cuenta/codigo');
        $code = null;
        Notification::assertSentTo($client, ClientAccessCode::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->post('/mi-cuenta/codigo', ['document' => '10', 'code' => $code])->assertRedirect('/mi-cuenta');
        $this->get('/mi-cuenta')->assertOk()->assertSee('1.300.000')->assertSee('Créditos AF');

        $this->company->update(['settings' => ['portal' => ['show_balance' => false]]]);
        // Saldo oculto: no ve la deuda total, sí lo que debe pagar para ponerse al día.
        $this->get('/mi-cuenta')->assertOk()->assertDontSee('1.300.000')->assertSee('186.364')->assertSee('para ponerte al día');
        $this->get($this->loan->fresh()->statementUrl())->assertNotFound();   // el estado de cuenta también se oculta
    }

    public function test_recibo_publico_en_pdf(): void
    {
        $this->get($this->loan->payments()->first()->receiptUrl())->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pagina_de_inicio_muestra_los_planes(): void
    {
        $this->get('/')->assertOk()->assertSee('Profesional')->assertSee('días gratis');
    }
}