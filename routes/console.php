<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('usage:refresh')->daily();
Schedule::command('contracts:daily')->hourly()->withoutOverlapping();

// CUR-01: daily exchange rates. The first run stores the day's rates; the three later runs are the retries of
// spec 6.1 and only fetch pairs that are still missing, so a provider outage is retried 3 times during the day.
Schedule::command('rates:fetch')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('rates:fetch')->twiceDaily(6, 12)->withoutOverlapping();
Schedule::command('rates:fetch')->dailyAt('18:00')->withoutOverlapping();

// LED-03: overdue invoices and reminders. Runs hourly and acts only at 09:00 in each company's time zone (CP-085).
Schedule::command('invoices:overdue-daily')->hourly()->withoutOverlapping();
