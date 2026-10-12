<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'price_monthly', 'max_active_loans', 'max_users', 'max_daily_messages', 'features', 'is_active', 'sort'];

    protected function casts(): array
    {
        return ['price_monthly' => 'decimal:2', 'features' => 'array', 'is_active' => 'boolean'];
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) data_get($this->features, $feature, false);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
