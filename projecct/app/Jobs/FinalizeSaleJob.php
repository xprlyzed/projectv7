<?php

namespace App\Jobs;

use App\Events\AuctionSold;
use App\Models\Auction;
use App\Models\Bid;
use App\Services\LiveKitPublisher;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Canlı yayın satışını SUNUCU tarafında sonlandırır (Görev 2 — para güvenliği).
 *
 * Geri sayım başlatıldığında 10 sn gecikmeli dispatch edilir. İş çalıştığında:
 *  - İlan satırını kilitler (lockForUpdate).
 *  - Geri sayımın hâlâ geçerli/aynı tur olduğunu (token) ve iptal edilmediğini doğrular.
 *  - O ANKI gerçek en yüksek teklifi yeniden sorgular (geri sayım başındaki değil).
 *  - Rezerv fiyat kontrolü yapar.
 *  - Geçerse OrderService ile emanet siparişi oluşturur (kazananı belirler).
 *
 * İstemci geri sayımı yalnızca görseldir; satışı istemci TETİKLEMEZ.
 */
class FinalizeSaleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param int $auctionId    İlan ID
     * @param int $countdownToken Bu geri sayım turunu tanımlayan token (countdown_ends_at timestamp)
     */
    public function __construct(
        public int $auctionId,
        public int $countdownToken,
    ) {}

    public function handle(OrderService $orders): void
    {
        // 1) Kilit altında doğrulama + kazanan teklifi belirle
        $winningBidId = DB::transaction(function () {
            $auction = Auction::whereKey($this->auctionId)->lockForUpdate()->first();
            if (! $auction) {
                return null;
            }

            // Token uyuşmuyorsa: yeni bir geri sayım başlatılmış ya da temizlenmiş → bu tur geçersiz
            $currentToken = optional($auction->countdown_ends_at)->getTimestamp();
            if ((int) $currentToken !== $this->countdownToken) {
                return null;
            }

            // Geri sayım iptal edilmişse (örn. yeni teklif geldi) → satma
            if ($auction->countdown_cancelled_at !== null) {
                $this->clearCountdown($auction);
                return null;
            }

            // İlan hâlâ satışa uygun mu?
            if (! $auction->isActive()) {
                $this->clearCountdown($auction);
                return null;
            }

            // O ANKI gerçek en yüksek teklif (job çalışma anındaki güncel veri)
            $top = $auction->bids()->reorder()
                ->orderByDesc('amount')->orderBy('created_at')
                ->first();

            if (! $top) {
                $this->clearCountdown($auction);
                return null;
            }

            // Rezerv fiyat kontrolü
            $reserve = (float) ($auction->reserve_price ?? 0);
            if ($reserve > 0 && (float) $top->amount < $reserve) {
                $this->clearCountdown($auction);
                return null;
            }

            return $top->id;
        });

        if (! $winningBidId) {
            return;
        }

        // 2) Satışı oluştur (OrderService kendi transaction+lock'unu kullanır, idempotent)
        $auction = Auction::find($this->auctionId);
        $bid = Bid::find($winningBidId);
        if (! $auction || ! $bid) {
            return;
        }

        $order = $orders->createFromWinningBid($auction, $bid);

        $auction->forceFill([
            'is_live'                => false,
            'live_ended_at'          => now(),
            'countdown_started_at'   => null,
            'countdown_ends_at'      => null,
            'countdown_bid_id'       => null,
            'countdown_cancelled_at' => null,
        ])->save();

        // 3) Odaya "satıldı" bildir
        LiveKitPublisher::publish($auction->id, 'auction-sold', [
            'winner_name' => $bid->user?->name ?? 'Kullanıcı',
            'amount'      => (float) $bid->amount,
            'display'     => number_format($bid->amount, 0, ',', '.') . ' ₺',
            'order_number'=> $order->order_number,
        ]);

        try {
            broadcast(new AuctionSold(
                auction: $auction,
                buyerName: $bid->user?->name ?? 'Kullanıcı',
                amount: $bid->amount,
                displayPrice: number_format($bid->amount, 0, ',', '.') . ' ₺',
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function clearCountdown(Auction $auction): void
    {
        $auction->forceFill([
            'countdown_started_at'   => null,
            'countdown_ends_at'      => null,
            'countdown_bid_id'       => null,
            'countdown_cancelled_at' => null,
        ])->save();
    }
}
