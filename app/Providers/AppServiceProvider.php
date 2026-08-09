<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use App\Services\AI\LSTMClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('zoom.service', fn() => new \App\Services\ZoomService());
        $this->app->singleton('googlemeet.service', fn() => new \App\Services\GoogleMeetService());

        // Bind LSTMClient as a singleton so only one instance is constructed
        // per request lifecycle — avoids redundant AISettings::get() calls and
        // ensures all callers (LSTMPredictions, OccupancyForecasting, retrain)
        // share the same cached HTTP responses.
        $this->app->singleton(LSTMClient::class);
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
