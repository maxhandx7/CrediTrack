<?php

namespace Tests\Feature;

use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\PromiseStatus;
use App\Models\Loan;
use App\Models\PaymentPromise;
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

class V4Test extends TestCase
{
    use RefreshDatabase;

    private User $lender;

    private Loan $loan;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Http::fake();
        $this->travelTo('2026-10-08 09:00');

        $this->lender = User::create(['name' => 'Alan', 'email' => 'a@a.com', 'password' => 'secret123', 'phone' => '3145561727',
            'settings' => ['late_fee' => ['enabled' => true, 'percent' => 5, 'grace_days' => 3]]]);
        $client = $this->lender->clients()->create(['name' => 'Andrés Manyoma', 'document' => '10', 'phone' => '3001112233']);
        $this->loan = $this->lender->loans()->create(['client_id' => $client->id, 'amount' => 1750000, 'interest_rate' => 0,
            'interest_type' => 'simple', 'start_date' => '2026-08-03', 'due_date' => '2027-01-15', 'payment_frequency' => 'quincenal']);
        app(ScheduleGenerator::class)->generate($this->loan);
        foreach (['2026-09-03', '2026-09-18', '2026-09-20'] as $d) {
            app(Payments::class)->register($this->loan->fresh(), ['amount' => 150000, 'date' => $d], notify: false);
        }
    }

    public function test_reestructurar_deja_el_prestamo_al_dia_sin_tocar_el_historial(): void
    {
        $loan = app(LoanRestructurer::class)->restructure($this->loan->fresh(), Carbon::parse('2026-10-12'), PaymentFrequency::Biweekly, 150000);

        $this->assertSame(LoanStatus::Active, $loan->status);
        $this->assertEquals(1300000, $loan->balance);
        $this->assertEquals(1750000, $loan->total_amount);
    }

    public function test_anular_un_pago_lo_deja_en_el_historial_pero_no_cuenta(): void
    {
        $payment = $this->loan->payments()->latest('id')->first();
        app(Payments::class)->void($payment, 'Duplicado');

        $this->assertEquals(1450000, $this->loan->fresh()->balance);
        $this->assertSame(3, $this->loan->payments()->count());
    }

    public function test_la_mora_se_cobra_una_vez_y_se_puede_perdonar(): void
    {
        $fees = app(LateFees::class);
        $created = $fees->apply();
        $this->assertGreaterThan(0, $created);
        $this->assertSame(0, $fees->apply());

        $this->loan->schedules()->where('kind', 'fee')->get()->each(fn ($f) => $fees->waive($f));
        $this->assertEquals(1750000, $this->loan->fresh()->total_amount);
    }

    public function test_promesa_se_cumple_con_un_pago_posterior(): void
    {
        $promise = PaymentPromise::create(['user_id' => $this->lender->id, 'loan_id' => $this->loan->id, 'promised_date' => '2026-10-10', 'amount' => 150000]);
        $this->travelTo('2026-10-09 10:00');
        app(Payments::class)->register($this->loan->fresh(), ['amount' => 150000, 'date' => '2026-10-09'], notify: false);

        $this->assertSame(PromiseStatus::Kept, $promise->fresh()->status);
    }

    public function test_recibo_y_estado_de_cuenta_publicos_en_pdf(): void
    {
        $payment = $this->loan->payments()->first();

        $this->get($payment->receiptUrl())->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($this->loan->fresh()->statementUrl())->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/recibo/'.str_repeat('x', 40))->assertNotFound();
    }

    public function test_portal_del_cliente_con_codigo_por_whatsapp(): void
    {
        config(['services.waha.enabled' => true, 'services.waha.url' => 'http://waha.test']);
        $client = $this->loan->client;

        $this->get('/mi-cuenta')->assertRedirect('/mi-cuenta/ingresar');
        $this->post('/mi-cuenta/ingresar', ['document' => '10'])->assertRedirect('/mi-cuenta/codigo');

        // Las notificaciones están simuladas (fake): el código se lee de la notificación enviada.
        $code = null;
        Notification::assertSentTo($client, ClientAccessCode::class, function (ClientAccessCode $n) use (&$code) {
            $code = $n->code;

            return true;
        });

        $this->post('/mi-cuenta/codigo', ['document' => '10', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/mi-cuenta/codigo', ['document' => '10', 'code' => $code])->assertRedirect('/mi-cuenta');
        $this->get('/mi-cuenta')->assertOk()->assertSee('Hola, Andrés')->assertSee('1.300.000');
    }

    public function test_el_panel_exige_login_y_cada_prestamista_ve_lo_suyo(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $other = User::create(['name' => 'Otro', 'email' => 'o@o.com', 'password' => 'secret123']);
        $this->actingAs($other)->get('/admin/loans/'.$this->loan->id)->assertNotFound();

        // Filament cierra la sesión si cambia el usuario dentro de ella: se empieza una sesión limpia.
        $this->flushSession();
        $this->actingAs($this->lender)->get('/admin/loans/'.$this->loan->id)->assertOk();
    }
}
