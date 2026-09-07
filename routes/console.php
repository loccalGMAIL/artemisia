<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sin esto, `activity_log` crece sin límite. Borra entradas más viejas que
// `activitylog.delete_records_older_than_days` (365 por defecto).
Schedule::command('activitylog:clean')->daily();
