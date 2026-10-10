<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('access-logs:prune')->monthly();
Schedule::command('contracts:generate-periods')
    ->dailyAt('00:10')
    ->timezone(config('app.business_timezone'));
