<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Süresi dolan hikayeleri her saat otomatik temizle (24 saat sonra silinir)
Schedule::command('stories:prune')->hourly();

// Süresi dolan açık artırmaları her dakika kapat + kazananı belirle
Schedule::command('auctions:close')->everyMinute();

// Kargolanmış ama onaylanmamış siparişleri süre dolunca otomatik tamamla (7 gün)
Schedule::command('orders:auto-release')->hourly();

// Kalp atışı gelmeyen (kopmuş) canlı yayınları kapat (Görev 3)
Schedule::command('streams:reap')->everyMinute()->withoutOverlapping();

// Bitmek üzere olan ilanlar için teklif verenlere/izleyenlere bildirim (Görev 4.2)
Schedule::command('auctions:notify-ending-soon')->everyFiveMinutes()->withoutOverlapping();

// Ödenmeyen siparişleri iptal et + sıradaki teklife devret (Görev 6.1)
Schedule::command('orders:expire-unpaid')->everySixHours()->withoutOverlapping();

// Kargolanmamış (süresi geçmiş) siparişleri iptal et + iade (Görev 6.2)
Schedule::command('orders:cancel-unshipped')->hourly()->withoutOverlapping();
