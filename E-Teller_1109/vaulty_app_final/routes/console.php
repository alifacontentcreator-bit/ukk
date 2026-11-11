<?php
/* Tujuan file: File ini mendefinisikan jalur masuk dan pemetaan permintaan untuk fitur console ke middleware atau handler aplikasi. */


use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
