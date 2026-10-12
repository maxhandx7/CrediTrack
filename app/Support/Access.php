<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Permisos por rol dentro de la empresa.
 *  - Dueño:       todo (equipo, configuración, suscripción, borrar).
 *  - Prestamista: opera la cartera completa; no equipo/configuración/suscripción.
 *  - Cobrador:    ve la cartera, registra pagos y promesas, envía recordatorios. No crea, edita, borra,
 *                 reestructura, anula ni cancela.
 */
class Access
{
    private const MATRIX = [
        'owner' => ['*'],
        'lender' => ['clients.manage', 'loans.manage', 'payments.register', 'payments.void', 'promises.manage',
            'loans.restructure', 'loans.cancel', 'fees.waive', 'messages.send', 'activity.view'],
        'collector' => ['payments.register', 'promises.manage', 'messages.send'],
    ];

    public static function role(?User $user = null, ?Company $company = null): ?string
    {
        $user ??= auth()->user();
        $company ??= Filament::getTenant();

        if (! $user || ! $company) {
            return null;
        }

        return $user->roleIn($company);
    }

    public static function can(string $ability, ?User $user = null, ?Company $company = null): bool
    {
        $role = self::role($user, $company);
        $allowed = self::MATRIX[$role] ?? [];

        return in_array('*', $allowed, true) || in_array($ability, $allowed, true);
    }

    public static function isOwner(): bool
    {
        return self::role() === 'owner';
    }
}
