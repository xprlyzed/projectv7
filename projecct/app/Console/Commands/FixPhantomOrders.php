<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Hatalı (phantom) sipariş tespiti: bir kullanıcının sayfayı ziyaret etmesiyle yanlışlıkla
 * oluşmuş olabilecek siparişleri bulur. Kriter:
 *   - Bağlı açık artırmanın HİÇ teklifi yok, ama sipariş oluşmuş, VEYA
 *   - Siparişin winning_bid_id'si o ilanın gerçek en yüksek teklifi DEĞİL.
 *
 * Varsayılan olarak yalnızca RAPORLAR (dry-run). Silmek için --purge verilmelidir.
 * Production veritabanına doğrudan müdahale ETMEZ; --purge kararı operatöre aittir.
 */
class FixPhantomOrders extends Command
{
    protected $signature = 'orders:fix-phantom {--purge : Tespit edilen hatalı siparişleri iptal/iade et}';

    protected $description = 'Yanlışlıkla oluşmuş (teklifsiz ilan / yanlış kazanan) siparişleri raporlar';

    public function handle(): int
    {
        $suspects = [];

        Order::with('auction')->chunkById(200, function ($orders) use (&$suspects) {
            foreach ($orders as $order) {
                $auction = $order->auction;
                if (! $auction) {
                    continue;
                }

                $topBid = $auction->bids()->reorder()->orderByDesc('amount')->orderBy('created_at')->first();

                $noBids       = $topBid === null;
                $wrongWinner  = $topBid && $order->winning_bid_id && (int) $order->winning_bid_id !== (int) $topBid->id;

                if ($noBids || $wrongWinner) {
                    $suspects[] = [
                        'order'  => $order->order_number,
                        'reason' => $noBids ? 'teklifsiz-ilan' : 'yanlış-kazanan',
                        'model'  => $order,
                    ];
                }
            }
        });

        if (empty($suspects)) {
            $this->info('Hatalı sipariş bulunamadı. ✅');
            return self::SUCCESS;
        }

        $this->warn('Şüpheli sipariş sayısı: ' . count($suspects));
        $this->table(
            ['Sipariş No', 'Sebep'],
            array_map(fn ($s) => [$s['order'], $s['reason']], $suspects)
        );

        if (! $this->option('purge')) {
            $this->line('');
            $this->info('Yalnızca rapor (dry-run). Silmek için: php artisan orders:fix-phantom --purge');
            return self::SUCCESS;
        }

        $orderService = app(\App\Services\OrderService::class);
        $count = 0;
        foreach ($suspects as $s) {
            // Emanetteki para varsa iade edilir; sipariş iptal edilir.
            $orderService->cancelAndRefund($s['model'], 'Otomatik temizlik: hatalı oluşmuş sipariş (' . $s['reason'] . ').');
            $count++;
        }

        $this->info("{$count} hatalı sipariş iptal/iade edildi.");
        return self::SUCCESS;
    }
}
