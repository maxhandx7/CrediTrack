<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cada WhatsApp enviado a un cliente: qué se dijo, cuándo y si llegó. */
class MessageLog extends Model
{
    protected $fillable = ['user_id', 'client_id', 'loan_id', 'type', 'body', 'status', 'error'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
