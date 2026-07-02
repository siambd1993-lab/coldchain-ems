<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- Scheduled jobs (run by the Horizon/scheduler worker) --------------------
// Roll up raw telemetry into 1-minute/5-minute/1-hour buckets.
Schedule::command('telemetry:rollup --window=1m')->everyMinute()->withoutOverlapping();
Schedule::command('telemetry:rollup --window=1h')->hourly()->withoutOverlapping();

// Generate recurring storage-rent invoices (daily/weekly/monthly rate cards).
Schedule::command('billing:accrue')->dailyAt('00:30');

// Detect gateways that went silent (no telemetry / LWT) and raise alerts.
Schedule::command('devices:health-sweep')->everyFiveMinutes();
