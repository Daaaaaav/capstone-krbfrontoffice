<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:auto-approve')->everyMinute()->withoutOverlapping()->runInBackground();

Schedule::command('bookings:auto-start')->everyMinute()->withoutOverlapping()->runInBackground();

Schedule::command('bookings:auto-complete')->everyMinute()->withoutOverlapping()->runInBackground();

Schedule::command('booking:update-status')->everyMinute()->withoutOverlapping()->runInBackground();

Schedule::command('booking:auto-reject')->everyMinute()->withoutOverlapping()->runInBackground();

Schedule::command('visitors:notify-scheduled')->everyMinute()->withoutOverlapping()->runInBackground();
