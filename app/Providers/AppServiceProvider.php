<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('zoom.service', fn() => new \App\Services\ZoomService());
        $this->app->singleton('googlemeet.service', fn() => new \App\Services\GoogleMeetService());
    }

    public function boot(): void
    {
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Failed::class,
            \App\Listeners\LogFailedLogin::class
        );
    }
}
