<?php

namespace App\Models;

use App\Enums\InterestType;
use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'user_id', 'client_id', 'amount', 'total_amount', 'installments_count', 'interest_rate',
        'interest_type', 'start_date', 'due_date', 'payment_frequency', 'status', 'notes',
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
        ];
    }

    /** Lo que el cliente ha abonado (usa la relación si ya está cargada). */
    public function getTotalPaidAttribute(): float
    {
        return round((float) ($this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : $this->payments()->sum('amount')), 2);
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

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanSchedule::class)->orderBy('scheduled_date')->orderBy('id');
    }
}
