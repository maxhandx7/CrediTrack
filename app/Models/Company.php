<?php

namespace App\Models;

use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Empresa cliente de la plataforma (el "tenant"). Todo su contenido es privado para ella. */
class Company extends Model implements HasAvatar
{
    use Notifiable;

    public const ROLES = ['owner' => 'Dueño', 'lender' => 'Prestamista', 'collector' => 'Cobrador'];

    /** Valores por defecto de todas las opciones de la empresa. */
    public const DEFAULTS = [
        'portal' => [
            'show_balance' => true,      // saldo total y total a pagar
            'show_schedule' => true,     // todas las cuotas (si no: solo la próxima)
            'show_payments' => true,     // historial de pagos y recibos
            'show_statement' => true,    // estado de cuenta en PDF
            'show_contact' => true,      // botón "Escribir"
        ],
        'notify' => [
            'enabled' => true,
            'reminder_before' => true,   // víspera
            'reminder_today' => true,    // día del vencimiento
            'overdue' => true,
            'overdue_days' => '1,3,7,15,30',
            'receipt' => true,
            'promise' => true,
            'lender_digest' => true,
            'include_balance' => true,   // los mensajes mencionan el saldo
            'include_link' => true,      // los mensajes llevan enlace al PDF
            'send_hour' => 8,
            'skip_sundays' => false,
        ],
        'late_fee' => ['enabled' => false, 'percent' => 5, 'grace_days' => 3],
        'templates' => [],
        'whatsapp' => ['session' => null],
    ];

    protected $fillable = ['name', 'slug', 'phone', 'logo', 'plan_id', 'status', 'trial_ends_at', 'paid_until', 'settings', 'webhook_url', 'webhook_secret'];

    protected $hidden = ['webhook_secret'];

    protected $attributes = ['status' => 'trial'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'date',
            'paid_until' => 'date',
            'webhook_secret' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $c) {
            if (blank($c->slug)) {
                $base = Str::slug($c->name) ?: 'empresa';
                $slug = $base;
                for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }
                $c->slug = $slug;
            }
        });
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default ?? data_get(self::DEFAULTS, $key));
    }

    // ── Suscripción ─────────────────────────────────────────

    /** ¿Puede usar el panel? (prueba vigente o pago al día, con días de gracia) */
    public function hasAccess(?Carbon $on = null): bool
    {
        $on ??= today();

        return match ($this->status) {
            'suspended' => false,
            'trial' => $this->trial_ends_at === null || $this->trial_ends_at->gte($on),
            'active' => $this->paid_until === null || $this->paid_until->copy()->addDays((int) config('saas.grace_days', 3))->gte($on),
            default => false, // past_due
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'trial' => 'Prueba gratis',
            'active' => 'Activa',
            'past_due' => 'Vencida',
            'suspended' => 'Suspendida',
            default => $this->status,
        };
    }

    public function statusColor(): string
    {
        return ['trial' => 'info', 'active' => 'success', 'past_due' => 'warning', 'suspended' => 'danger'][$this->status] ?? 'gray';
    }

    /** Días que le quedan (prueba o pago); null si no vence. */
    public function daysLeft(): ?int
    {
        $end = $this->status === 'trial' ? $this->trial_ends_at : $this->paid_until;

        return $end ? (int) today()->diffInDays($end, false) : null;
    }

    /** Registra un pago de suscripción y extiende el periodo desde el vencimiento (o desde hoy). */
    public function registerSubscriptionPayment(float $amount, int $months, ?int $planId = null, string $method = 'manual', ?string $reference = null, ?int $recordedBy = null): SubscriptionPayment
    {
        return DB::transaction(function () use ($amount, $months, $planId, $method, $reference, $recordedBy) {
            $current = $this->status === 'active' && $this->paid_until?->isFuture() ? $this->paid_until : today();
            $start = $current->copy();
            $end = $current->copy()->addMonthsNoOverflow($months);

            $payment = $this->subscriptionPayments()->create([
                'plan_id' => $planId ?? $this->plan_id, 'amount' => $amount, 'months' => $months,
                'period_start' => $start, 'period_end' => $end, 'method' => $method,
                'reference' => $reference, 'recorded_by' => $recordedBy,
            ]);

            $this->forceFill(['status' => 'active', 'paid_until' => $end, 'plan_id' => $planId ?? $this->plan_id])->save();

            return $payment;
        });
    }

    // ── Límites del plan ────────────────────────────────────

    public function canAddActiveLoan(): bool
    {
        $max = $this->plan?->max_active_loans;

        return $max === null || $this->loans()->whereIn('status', ['activo', 'atrasado'])->count() < $max;
    }

    public function canAddMember(): bool
    {
        $max = $this->plan?->max_users;

        return $max === null || $this->users()->count() < $max;
    }

    public function dailyMessageLimit(): int
    {
        return (int) ($this->plan?->max_daily_messages ?? config('saas.default_daily_messages', 150));
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) $this->plan?->hasFeature($feature);
    }

    // ── WhatsApp ────────────────────────────────────────────

    /** Sesión de WAHA a usar: la propia (si el plan lo permite) o la compartida de la plataforma. */
    public function whatsappSession(): ?string
    {
        return $this->hasFeature('own_whatsapp') ? ($this->setting('whatsapp.session') ?: null) : null;
    }

    public function wahaSession(): ?string
    {
        return $this->whatsappSession();
    }

    public function routeNotificationForWaha(): ?string
    {
        return $this->setting('notify.lender_digest') ? $this->phone : null;
    }

    public function displayName(): string
    {
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo ? route('company.logo', $this) : null;
    }

    // ── Relaciones ──────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', 'owner');
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

    public function paymentPromises(): HasMany
    {
        return $this->hasMany(PaymentPromise::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->latest('period_end');
    }
}
