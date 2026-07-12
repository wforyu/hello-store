<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Cron Automation Schedule ─────────────────────────

Schedule::command('flash-sale:activate')->everyMinute();
Schedule::command('flash-sale:complete')->everyMinute();
Schedule::command('coupon:expire')->dailyAt('00:00');
Schedule::command('backup:database')->dailyAt('02:00');
Schedule::command('stock:alert')->hourly();
