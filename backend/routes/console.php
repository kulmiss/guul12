<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Quality gate: Beta questions with enough clean answers become Published (03:15 Addis Ababa time).
Schedule::command('quality:promote')->dailyAt('00:15')->withoutOverlapping();

// Database backup every night (02:30 Addis Ababa) and a server check every 10 minutes.
Schedule::command('guul:backup')->dailyAt('23:30')->withoutOverlapping();
Schedule::command('guul:watchdog')->everyTenMinutes()->withoutOverlapping();

// Nova dashboard numbers, worked out ahead of time so the dashboard never runs slow queries.
Schedule::command('guul:stats')->everyTenMinutes()->withoutOverlapping();
