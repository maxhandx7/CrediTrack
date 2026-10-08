<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

/** Bitácora para no enviar dos veces el mismo aviso (ej. "recordatorio cuota 15, día 2026-10-09"). */
class SentNotification extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['key'];

    /** Devuelve true solo la PRIMERA vez que se reclama la clave. Seguro ante ejecuciones simultáneas. */
    public static function claim(string $key): bool
    {
        try {
            static::create(['key' => $key]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
