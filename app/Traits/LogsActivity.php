<?php

namespace App\Traits;

use App\Services\ActivityLogger;

trait LogsActivity
{
    /**
     * Log a custom action (sync, approve, login, bulk, etc.).
     */
    protected function logActivity(string $module, string $action, string $description): void
    {
        ActivityLogger::log($module, $action, $description);
    }
}
