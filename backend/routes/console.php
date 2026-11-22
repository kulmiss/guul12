<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Quality gate: Beta questions with enough clean answers become Published (03:15 Addis Ababa time).
Schedule::command('quality:promote')->dailyAt('00:15')->withoutOverlapping();
