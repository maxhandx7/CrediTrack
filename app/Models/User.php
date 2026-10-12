<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Prestamista: dueño de sus clientes, préstamos y pagos. */
class User extends Authenticatable implements FilamentUser, HasTenants
{
    use Auditable, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'address', 'notifications_enabled', 'webhook_url', 'webhook_secret', 'settings', 'status', 'role', 'is_platform_admin'];

    protected $hidden = ['password', 'remember_token', 'webhook_secret'];

    protected $attributes = ['status' => 'active', 'role' => 'user', 'notifications_enabled' => true, 'is_platform_admin' => false];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notifications_enabled' => 'boolean',
            'webhook_secret' => 'encrypted',
            'settings' => 'array',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $panel->getId() === 'platform' ? $this->is_platform_admin : true;
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->companies;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->is_platform_admin || $this->companies()->whereKey($tenant)->exists();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role')->withTimestamps();
    }

    /** Rol del usuario en una empresa: owner | lender | collector (o null). */
    public function roleIn(Company $company): ?string
    {
        $role = $this->companies->firstWhere('id', $company->id)?->pivot?->role;

        return $role ?? ($this->is_platform_admin ? 'owner' : null);
    }

    public function isActive(): bool
    {
        return $this->status !== 'inactive';
    }

    public function isAdmin(): bool
    {
        return $this->is_platform_admin;
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