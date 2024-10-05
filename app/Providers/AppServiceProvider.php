<?php

namespace App\Providers;

use App\Services\GeneralService;
use App\Services\NotificationService;
use Illuminate\Support\ServiceProvider;
use App\Services\ActivityLogger;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->bind(GeneralService::class, function ($app) {
            return new GeneralService();
        });

        $this->app->bind(NotificationService::class, function ($app) {
            return new NotificationService();
        });

        $this->app->bind(ActivityLogger::class, function ($app) {
            return new ActivityLogger();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
