<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tandai tagihan yang lewat batas waktu sebagai kedaluwarsa.
Schedule::command('tagihan:expire')->everyMinute()->withoutOverlapping();
