<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanSchedule extends Model
{
    protected $fillable = ['loan_id', 'kind', 'parent_id', 'scheduled_date', 'amount_due', 'amount_paid', 'paid_at', 'status', 'note'];

    protected $attributes = ['status' => 'pendiente', 'amount_paid' => 0, 'kind' => 'installment'];

    public function isFee(): bool
    {
        return $this->kind === 'fee';
    }

    /** Recargo por mora generado para esta cuota (si existe). */
    public function fee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'parent_id')->where('kind', 'fee');
    }

    protected $appends = ['amount_pending'];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date:Y-m-d',
            'paid_at' => 'date:Y-m-d',
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'status' => ScheduleStatus::class,
        ];
    }

    public function getAmountPendingAttribute(): float
    {
        return max(0, round((float) $this->amount_due - (float) $this->amount_paid, 2));
    }

    public function daysOverdue(): int
    {
        return $this->scheduled_date->isPast() ? (int) $this->scheduled_date->diffInDays(today(), true) : 0;
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
