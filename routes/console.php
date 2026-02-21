<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('app:reset-monthly-usage')
    ->monthlyOn(1, '00:00');

//Schedule::command('app:reset-monthly-usage')->everyMinute();

/*
Schedule::command('datasets:cleanup')
    ->daily()
    ->withoutOverlapping();
*/
