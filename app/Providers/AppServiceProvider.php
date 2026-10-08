<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Carbon::setLocale('es');
        Model::shouldBeStrict(! $this->app->isProduction());

        // Login de prestamistas: 5 intentos por minuto por correo + IP.
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));

        // Códigos de clientes: limita por IP y por cédula (evita adivinar códigos o spamear WhatsApp).
        RateLimiter::for('client-code', fn (Request $r) => [
            Limit::perMinute(5)->by('ip:'.$r->ip()),
            Limit::perHour(10)->by('doc:'.trim((string) $r->input('document'))),
        ]);
    }
}
