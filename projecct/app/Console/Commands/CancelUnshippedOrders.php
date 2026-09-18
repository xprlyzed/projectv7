<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class CancelUnshippedOrders extends Command
{
    protected $signature = 'orders:cancel-unshipped';

    protected $description = 'Kargolama süresi (3 gün) geçmiş ödenmiş siparişleri iptal eder ve tam iade yapar';

    public function handle(OrderService $orders): int
    {
        $count = $orders->cancelUnshippedOrders();
        $this->info("İptal edilen kargolanmamış sipariş: {$count}");

        return self::SUCCESS;
    }
}
