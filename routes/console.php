<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PRD §14.2: batch penyusutan bulanan aktiva tetap.
Schedule::command('depreciation:run')->monthlyOn(1, '01:00');

// PRD §16: batch harian reminder jatuh tempo angsuran.
Schedule::command('notifikasi:cek-jatuh-tempo-angsuran')->dailyAt('06:00');

// PRD §10: batch bulanan tagihan iuran kios (hanya jenis iuran flat).
Schedule::command('upf:generate-tagihan-periodik')->monthlyOn(1, '05:00');
