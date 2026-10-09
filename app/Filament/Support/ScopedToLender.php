<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Builder;

/** Cada prestamista ve únicamente sus propios registros. */
trait ScopedToLender
{
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(static::getModel()::make()->qualifyColumn('user_id'), auth()->id());
    }
}
