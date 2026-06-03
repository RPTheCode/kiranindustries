<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

trait LogsMasterCrud
{
    protected function logMasterCreated(Model $model): void
    {
        ActivityLogger::logMaster($model, 'created');
    }

    protected function logMasterUpdated(Model $model): void
    {
        ActivityLogger::logMaster($model, 'updated');
    }

    protected function logMasterDeleted(Model $model): void
    {
        ActivityLogger::logMaster($model, 'deleted');
    }
}
