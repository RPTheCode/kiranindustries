<?php

namespace App\Traits;

use App\Services\ActivityLogger;

/**
 * Automatically log create / update / delete for Eloquent models (masters, etc.).
 */
trait RecordsActivity
{
    public static function bootRecordsActivity(): void
    {
        static::created(function ($model) {
            ActivityLogger::logModel($model, 'created');
        });

        static::updated(function ($model) {
            ActivityLogger::logModel($model, 'updated');
        });

        static::deleted(function ($model) {
            ActivityLogger::logModel($model, 'deleted');
        });
    }
}
