<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_id',
        'amount',
        'interest_rate',
        'interest_type',
        'start_date',
        'due_date',
        'payment_frequency',
        'status',
        'notes',
    ];

     public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function schedules()
    {
        return $this->hasMany(LoanSchedule::class);
    }

    // Accesor rápido: saldo total pagado
    public function getTotalPagadoAttribute()
    {
        return $this->payments()->sum('amount');
    }
}
