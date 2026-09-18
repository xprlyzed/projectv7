<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CloseAuctions extends Command
{
    protected $signature = 'auctions:close';

    protected $description = 'Süresi dolan açık artırmaları kapatır, kazananı belirler ve sipariş oluşturur';

    public function handle(OrderService $orders): int
    {
        $result = $orders->closeDueAuctions();

        $this->info("Kapatıldı: {$result['sold']} satış, {$result['ended']} satışsız bitti.");

        return self::SUCCESS;
    }

    /** Cron olmayan ortamlar için fırsatçı, throttle'lı kapatma. */
    public static function runThrottled(OrderService $orders): void
    {
        if (! Cache::add('auctions_close_lock', 1, now()->addSeconds(20))) {
            return;
        }

        $orders->closeDueAuctions();
    }
}
