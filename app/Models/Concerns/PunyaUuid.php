<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/** ID publik yang stabil (dipakai di API/webhook); ID numerik tidak pernah dibagikan. */
trait PunyaUuid
{
    public static function bootPunyaUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
