<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/** Deudor. Entra al portal /mi-cuenta con su cédula + código por WhatsApp. */
class Client extends Model implements AuthenticatableContract
{
    use Auditable, Authenticatable, HasApiTokens, Notifiable;

    protected $fillable = [
        'user_id', 'name', 'email', 'phone', 'whatsapp_opt_in', 'document', 'address', 'notes',
        'credit_limit', 'cosigner_name', 'cosigner_document', 'cosigner_phone', 'documents',
    ];

    protected $attributes = ['whatsapp_opt_in' => true];

    protected function casts(): array
    {
        return [
            'whatsapp_opt_in' => 'boolean',
            'credit_limit' => 'decimal:2',
            'documents' => 'array',
        ];
    }

    /** No hay contraseña ni "recordarme": el acceso es siempre con código por WhatsApp. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /** Laravel guarda un sello de la contraseña en la sesión; los clientes no tienen una. */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function firstName(): string
    {
        return strtok(trim($this->name), ' ') ?: $this->name;
    }

    public function routeNotificationForWaha(): ?string
    {
        return $this->whatsapp_opt_in ? $this->phone : null;
    }

    /**
     * Calificación de cumplimiento: % de cuotas pagadas a tiempo (con días de gracia)
     * entre las que ya vencieron. Sin historial → null.
     *
     * @return array{score: int, label: string, color: string}|null
     */
    public function paymentScore(int $graceDays = 3): ?array
    {
        $due = LoanSchedule::query()
            ->whereHas('loan', fn ($q) => $q->where('client_id', $this->id))
            ->where('kind', 'installment')
            ->whereDate('scheduled_date', '<', today()->toDateString())
            ->get(['scheduled_date', 'paid_at', 'status']);

        if ($due->isEmpty()) {
            return null;
        }

        $onTime = $due->filter(fn ($s) => $s->status === ScheduleStatus::Paid
            && $s->paid_at && $s->paid_at->lte($s->scheduled_date->copy()->addDays($graceDays)))->count();
        $score = (int) round($onTime / $due->count() * 100);

        return match (true) {
            $score >= 90 => ['score' => $score, 'label' => 'Excelente', 'color' => 'success'],
            $score >= 75 => ['score' => $score, 'label' => 'Bueno', 'color' => 'info'],
            $score >= 50 => ['score' => $score, 'label' => 'Regular', 'color' => 'warning'],
            default => ['score' => $score, 'label' => 'Riesgoso', 'color' => 'danger'],
        };
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Loan::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessageLog::class)->latest();
    }

    public function loginCodes(): HasMany
    {
        return $this->hasMany(ClientLoginCode::class);
    }
}