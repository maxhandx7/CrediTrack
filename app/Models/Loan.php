<?php

namespace App\Models;

use App\Enums\InterestType;
use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\ScheduleStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id', 'client_id', 'amount', 'total_amount', 'installments_count', 'interest_rate',
        'interest_type', 'start_date', 'due_date', 'payment_frequency', 'status', 'notes', 'documents',
    ];

    protected $attributes = ['status' => 'activo', 'installments_count' => 0];

    /** Se agregan a la respuesta JSON para que el frontend no tenga que calcularlos. */
    protected $appends = ['total_paid', 'balance'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'interest_type' => InterestType::class,
            'payment_frequency' => PaymentFrequency::class,
            'status' => LoanStatus::class,
            'start_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'documents' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Loan $loan) => $loan->statement_token ??= Str::random(40));
    }

    public function statementUrl(): string
    {
        // Registros antiguos, importados o restaurados pueden no tener token: se genera al vuelo.
        if (blank($this->statement_token) && $this->exists) {
            $this->forceFill(['statement_token' => Str::random(40)])->saveQuietly();
        }

        return route('public.statement', $this->statement_token);
    }

    /** Próxima cuota con saldo (o null si está al día y sin cuotas pendientes). */
    public function nextSchedule(): ?LoanSchedule
    {
        return ($this->relationLoaded('schedules') ? $this->schedules : $this->schedules()->get())
            ->first(fn (LoanSchedule $s) => $s->amount_pending > 0);
    }

    public function overdueAmount(): float
    {
        return round((float) ($this->relationLoaded('schedules') ? $this->schedules : $this->schedules()->get())
            ->where('status', ScheduleStatus::Overdue)->sum(fn ($s) => $s->amount_pending), 2);
    }

    public function progress(): int
    {
        return (float) $this->total_amount > 0 ? (int) min(100, round($this->total_paid / (float) $this->total_amount * 100)) : 0;
    }

    /** Lo que el cliente ha abonado (usa la relación si ya está cargada). */
    public function getTotalPaidAttribute(): float
    {
        return round((float) ($this->relationLoaded('payments')
            ? $this->payments->whereNull('voided_at')->sum('amount')
            : $this->validPayments()->sum('amount')), 2);
    }

    /** Lo que falta por pagar, CON intereses (antes se ignoraban). */
    public function getBalanceAttribute(): float
    {
        return max(0, round((float) $this->total_amount - $this->total_paid, 2));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Pagos que cuentan (los anulados quedan en el historial pero no suman). */
    public function validPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->whereNull('voided_at');
    }

    public function promises(): HasMany
    {
        return $this->hasMany(PaymentPromise::class)->latest('promised_date');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessageLog::class)->latest();
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('scheduled_date')->orderBy('id');
    }
}