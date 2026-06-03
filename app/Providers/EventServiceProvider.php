<?php

namespace App\Providers;

use App\Events\UserCreated;
use App\Listeners\SendUserCreatedEmail;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        UserCreated::class => [
            SendUserCreatedEmail::class,
        ],
    ];

    public function boot(): void
    {
        foreach (['created', 'updated', 'deleted'] as $action) {
            Event::listen("eloquent.{$action}: *", function (string $eventName, $payload) use ($action) {
                $model = Arr::wrap($payload)[0] ?? null;

                if ($model instanceof Model) {
                    ActivityLogger::logModel($model, $action);
                }
            });
        }
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
