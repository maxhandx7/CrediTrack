<?php

namespace App\Models\Concerns;

use App\Models\Company;

/**
 * Registros de una empresa: si no se indica quién los creó (user_id), se toma el usuario
 * conectado o, sin sesión (comandos, importaciones), el dueño de la empresa.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::creating(function ($model) {
            if (blank($model->company_id) && filled($model->client_id ?? null)) {
                $model->company_id = \App\Models\Client::whereKey($model->client_id)->value('company_id');
            }
            if (blank($model->company_id) && filled($model->loan_id ?? null)) {
                $model->company_id = \App\Models\Loan::whereKey($model->loan_id)->value('company_id');
            }
            if (filled($model->user_id) || blank($model->company_id)) {
                return;
            }

            $model->user_id = auth('web')->id()
                ?? Company::find($model->company_id)?->owners()->value('users.id');
        });
    }
}
