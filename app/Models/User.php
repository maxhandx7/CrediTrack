<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/** Prestamista: dueño de sus clientes, préstamos y pagos. */
class User extends Authenticatable implements FilamentUser
{
    use Auditable, HasApiTokens, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'address', 'notifications_enabled', 'webhook_url', 'webhook_secret', 'settings', 'status', 'role'];

    protected $hidden = ['password', 'remember_token', 'webhook_secret'];

    protected $attributes = ['status' => 'active', 'role' => 'user', 'notifications_enabled' => true];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notifications_enabled' => 'boolean',
            'webhook_secret' => 'encrypted',
            'settings' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive();
    }

    public function isActive(): bool
    {
        return $this->status !== 'inactive';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /** Nombre con el que firma los mensajes y recibos (ej. "Créditos Alan"). */
    public function displayName(): string
    {
        return $this->setting('business_name') ?: $this->name;
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

    public function promises(): HasMany
    {
        return $this->hasMany(PaymentPromise::class);
    }
}
