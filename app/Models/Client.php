<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/** Deudor. Puede entrar al portal con su cédula + un código enviado por WhatsApp. */
class Client extends Model
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['user_id', 'name', 'email', 'phone', 'whatsapp_opt_in', 'document', 'address', 'notes'];

    protected $attributes = ['whatsapp_opt_in' => true];

    protected function casts(): array
    {
        return ['whatsapp_opt_in' => 'boolean'];
    }

    public function firstName(): string
    {
        return strtok(trim($this->name), ' ') ?: $this->name;
    }

    public function routeNotificationForWaha(): ?string
    {
        return $this->whatsapp_opt_in ? $this->phone : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function loginCodes(): HasMany
    {
        return $this->hasMany(ClientLoginCode::class);
    }
}
