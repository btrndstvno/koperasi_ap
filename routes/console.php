<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks (Laravel 11+ style)
|--------------------------------------------------------------------------
|
| Jalankan scheduler dengan: php artisan schedule:work
| Atau di cron server: * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Bunga Tabungan Bulanan: Setiap tanggal 28 jam 23:00
Schedule::command('app:distribute-interest')
    ->monthlyOn(28, '23:00')
    ->description('Distribusi bunga tabungan bulanan');

// SHU Tahunan: Setiap 1 Januari jam 01:00 (DIKOMENTARI - jalankan manual)
// Schedule::command('app:distribute-shu')
//     ->yearlyOn(1, 1, '01:00')
//     ->description('Pembagian SHU tahunan');

