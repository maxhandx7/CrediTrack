<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    use Auditable;

    protected $fillable = [
        'loan_id', 'user_id', 'date', 'amount', 'method', 'reference', 'attachment',
        'remaining_balance', 'notes', 'voided_at', 'void_reason',
    ];

    protected $attributes = ['method' => 'efectivo'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'method' => PaymentMethod::class,
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->receipt_token ??= Str::random(40);
            $payment->receipt_number ??= (int) static::where('user_id', $payment->user_id)->max('receipt_number') + 1;
        });
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function receiptLabel(): string
    {
        return 'RC-'.str_pad((string) $this->receipt_number, 4, '0', STR_PAD_LEFT);
    }

    public function receiptUrl(): string
    {
        if (blank($this->receipt_token) && $this->exists) {
            $this->forceFill(['receipt_token' => Str::random(40)])->saveQuietly();
        }

        return route('public.receipt', $this->receipt_token);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}