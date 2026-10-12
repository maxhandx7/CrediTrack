<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Subscription;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

/** Si la prueba o la suscripción venció, el panel solo deja ver "Suscripción" (y cerrar sesión). */
class EnsureCompanyHasAccess
{
    public function handle(Request $request, Closure $next)
    {
        $company = Filament::getTenant();

        if ($company && ! $company->hasAccess() && ! $request->user()?->is_platform_admin) {
            $url = Subscription::getUrl(tenant: $company);
            if ($request->url() !== $url && ! $request->is('livewire*')) {
                return redirect($url);
            }
        }

        return $next($request);
    }
}
