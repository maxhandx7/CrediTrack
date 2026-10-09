<?php

namespace App\Models;

use App\Enums\PromiseStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Me dijo que paga el viernes": se recuerda ese día y se verifica si cumplió. */
class PaymentPromise extends Model
{
    use Auditable;

    protected $fillable = ['user_id', 'loan_id', 'promised_date', 'amount', 'status', 'notes', 'resolved_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'promised_date' => 'date',
            'amount' => 'decimal:2',
            'status' => PromiseStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Lo abonado desde que se hizo la promesa (pagos REGISTRADOS después de crearla,
     * con fecha hasta el día acordado). Un pago anterior no cumple una promesa nueva.
     */
    public function paidSoFar(): float
    {
        return (float) $this->loan()->firstOrFail()->validPayments()
            ->where('created_at', '>=', $this->created_at)
            ->whereDate('date', '<=', $this->promised_date->toDateString())
            ->sum('amount');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
