<?php

namespace App\Traits;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Call from master CRUD controllers when you need a guaranteed audit entry.
 */
trait LogsMasterActivity
{
    protected function logMasterActivity(Model $model, string $action): void
    {
        ActivityLogger::logMaster($model, $action);
    }
}
