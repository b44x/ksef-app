<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Purchase invoices arrive in KSeF whenever a seller sends them: look every quarter of an hour. Needs the scheduler
// (`php artisan schedule:work`, or `* * * * * php artisan schedule:run` in cron); `composer run dev` starts it.
// KSeF's availability is announced by the Ministry; the pages show the last answer, kept fresh here.
Schedule::command('ksef:check-availability')->everyFiveMinutes();
Schedule::command('ksef:sync-purchases')->everyFifteenMinutes()->withoutOverlapping();
// A daily summary of what needs attention, by e-mail, for companies that gave an address (and the server a mail setup).
Schedule::command('ksef:send-digest')->dailyAt('07:30');
// Recurring invoices are made early in the morning, so that they are waiting when the working day starts.
Schedule::command('invoices:generate-recurring')->dailyAt('05:00');
