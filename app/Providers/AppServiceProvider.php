<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\NotificationRepository;
use App\Services\NotificationService;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the repository and service
        $this->app->singleton(NotificationRepository::class);
        $this->app->singleton(NotificationService::class, function ($app) {
            return new NotificationService($app->make(NotificationRepository::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
