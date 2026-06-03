<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    public function created(Model $model): void
    {
        ActivityLogger::logModel($model, 'created');
    }

    public function updated(Model $model): void
    {
        ActivityLogger::logModel($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        ActivityLogger::logModel($model, 'deleted');
    }
}
