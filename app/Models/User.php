<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/** Prestamista: dueño de sus clientes, préstamos y pagos. */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'address', 'notifications_enabled', 'webhook_url', 'webhook_secret'];

    protected $hidden = ['password', 'remember_token', 'webhook_secret'];

    protected $attributes = ['status' => 'active', 'role' => 'user', 'notifications_enabled' => true];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notifications_enabled' => 'boolean',
            'webhook_secret' => 'encrypted',
        ];
    }

    public function isActive(): bool
    {
        return $this->status !== 'inactive';
    }

    public function routeNotificationForWaha(): ?string
    {
        return $this->notifications_enabled ? $this->phone : null;
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
