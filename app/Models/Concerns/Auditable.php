<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;

/** Deja registro de quién creó, cambió o borró el registro. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        foreach (['created', 'updated', 'deleted'] as $event) {
            static::$event(function ($model) use ($event) {
                $changes = $event === 'updated'
                    ? collect($model->getChanges())->except(['updated_at', 'remember_token'])->all()
                    : null;

                if ($event === 'updated' && $changes === []) {
                    return;
                }

                ActivityLog::create([
                    'user_id' => auth('web')->id(),
                    'subject_type' => class_basename($model),
                    'subject_id' => $model->getKey(),
                    'event' => $event,
                    'changes' => $changes,
                ]);
            });
        }
    }
}
