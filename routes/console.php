<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purchase invoices arrive in KSeF whenever a seller sends them: look every quarter of an hour. Needs the scheduler
// (`php artisan schedule:work`, or `* * * * * php artisan schedule:run` in cron); `composer run dev` starts it.
Schedule::command('ksef:sync-purchases')->everyFifteenMinutes()->withoutOverlapping();
