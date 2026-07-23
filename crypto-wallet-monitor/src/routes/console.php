<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('wallets:capture-balances')->hourly();
Schedule::command('telegram:poll')->everyMinute();
Schedule::command('alerts:evaluate')->everyFifteenMinutes();
