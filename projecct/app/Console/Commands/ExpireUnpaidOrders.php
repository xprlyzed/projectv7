<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = '48 saattir ödenmeyen siparişleri iptal eder ve sıradaki teklife devreder';

    public function handle(OrderService $orders): int
    {
        $count = $orders->expireUnpaidOrders();
        $this->info("İptal edilen ödenmemiş sipariş: {$count}");

        return self::SUCCESS;
    }
}
