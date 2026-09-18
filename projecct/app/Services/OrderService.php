<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use App\Notifications\OrderNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alım-satım sonrası emanet (escrow) tabanlı sipariş yaşam döngüsü.
 *
 * Akış: awaiting_payment -> paid (para emanette) -> shipped -> delivered -> completed
 * Koruma: kazanan otomatik atanır, para emanette tutulur, teslimat sonrası satıcıya aktarılır,
 *         süre dolunca otomatik serbest bırakılır, anlaşmazlıkta admin karar verir.
 */
class OrderService
{
    public function __construct(private readonly BalanceService $balance) {}

    /**
     * Süresi dolan aktif açık artırmaları kapatır: en yüksek teklifi bulur, rezerv fiyat
     * kontrolünü yapar ve geçerse sipariş oluşturur. Hem cron komutu hem opportunistic
     * polling bu tek metodu çağırır (kod tekrarını önler).
     */
    public function closeDueAuctions(): array
    {
        $due = Auction::where('status', 'active')
            ->where('ends_at', '<=', now())
            ->get();

        $sold = 0;
        $ended = 0;

        foreach ($due as $auction) {
            $topBid = $auction->bids()->reorder()->orderByDesc('amount')->orderBy('created_at')->first();
            $reserve = (float) ($auction->reserve_price ?? 0);

            if (! $topBid || ($reserve > 0 && (float) $topBid->amount < $reserve)) {
                $auction->update(['status' => 'ended']);
                $ended++;
                continue;
            }

            $this->createFromWinningBid($auction, $topBid);
            $sold++;
        }

        return ['sold' => $sold, 'ended' => $ended];
    }

    /**
     * Kazanan teklif ile açık artırmadan sipariş oluşturur ve mümkünse parayı emanete alır.
     */
    public function createFromWinningBid(Auction $auction, Bid $bid): Order
    {
        return DB::transaction(function () use ($auction, $bid) {
            // Aynı ilana eşzamanlı kapanışları serileştir
            Auction::whereKey($auction->id)->lockForUpdate()->first();

            $existing = Order::where('auction_id', $auction->id)->first();
            if ($existing) {
                return $existing;
            }

            $rate       = (float) (function_exists('setting') ? setting('commission_rate', 10) : 10);
            $amount     = (float) $bid->amount;
            $commission = round($amount * $rate / 100, 2);

            $order = Order::create([
                'order_number'      => $this->generateOrderNumber(),
                'auction_id'        => $auction->id,
                'buyer_id'          => $bid->user_id,
                'seller_id'         => $auction->user_id,
                'winning_bid_id'    => $bid->id,
                'amount'            => $amount,
                'commission_amount' => $commission,
                'status'            => 'awaiting_payment',
                'escrow_status'     => 'none',
            ]);

            $auction->forceFill([
                'status'         => 'sold',
                'winning_bid_id' => $bid->id,
                'sold_at'        => now(),
                'current_price'  => $amount,
            ])->save();

            $this->event($order, 'awaiting_payment', 'Sipariş oluşturuldu',
                'Açık artırmayı '.number_format($amount, 0, ',', '.').' ₺ ile kazandınız.', $bid->user_id, 'bi-trophy');

            // Alıcının bakiyesi yeterliyse parayı hemen emanete al
            $this->tryHoldEscrow($order);

            $this->notify($order->buyer, $order, 'Açık artırmayı kazandınız! 🎉',
                $auction->title.' — '.number_format($amount, 0, ',', '.').' ₺', 'bi-trophy', '#f59e0b');
            $this->notify($order->seller, $order, 'Ürününüz satıldı',
                $auction->title.' — '.number_format($amount, 0, ',', '.').' ₺', 'bi-cash-coin', '#10b981');

            return $order;
        });
    }

    /** Alıcının bakiyesini emanete al (yeterliyse). awaiting_payment -> paid */
    public function tryHoldEscrow(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'awaiting_payment') {
                return $locked && $locked->escrow_status === 'held';
            }

            try {
                $this->balance->debit(
                    user: $locked->buyer,
                    amount: (float) $locked->amount,
                    description: 'Sipariş ödemesi (emanet) — '.$locked->order_number,
                    meta: ['order_id' => $locked->id, 'type' => 'escrow_hold'],
                );
            } catch (\RuntimeException $e) {
                return false; // yetersiz bakiye
            }

            $locked->update([
                'status'        => 'paid',
                'escrow_status' => 'held',
                'paid_at'       => now(),
                'ship_by_at'    => now()->addDays(3), // Görev 6.2: kargolama son tarihi
            ]);

            $this->event($locked, 'paid', 'Ödeme alındı (emanet)',
                'Tutar güvenli şekilde emanete alındı. Kargo sonrası satıcıya aktarılacak.', $locked->buyer_id, 'bi-shield-check');

            $this->notify($locked->seller, $locked, 'Ödeme alındı',
                $locked->order_number.' için ödeme emanete alındı. Kargoya verebilirsiniz.', 'bi-shield-check', '#3b82f6');

            return true;
        });
    }

    /** Teslimat adresini kaydeder. */
    public function setShippingAddress(Order $order, array $data): void
    {
        $address = trim($data['address_line'] ?? '');

        $order->update([
            'recipient_name'   => $data['recipient_name'] ?? null,
            'recipient_phone'  => $data['recipient_phone'] ?? null,
            'shipping_address' => $address,
            'address_city'     => $data['address_city'] ?? null,
            'address_district' => $data['address_district'] ?? null,
            'address_zip'      => $data['address_zip'] ?? null,
        ]);

        $this->event($order, $order->status, 'Teslimat adresi girildi',
            $order->recipient_name.' • '.$order->address_city, $order->buyer_id, 'bi-geo-alt');

        $this->notify($order->seller, $order, 'Teslimat adresi girildi',
            $order->order_number.' için alıcı adresini girdi.', 'bi-geo-alt', '#3b82f6');
    }

    /** Satıcı kargoya verdi. paid -> shipped */
    public function markShipped(Order $order, string $carrier, string $trackingNumber, ?string $trackingUrl = null): void
    {
        DB::transaction(function () use ($order, $carrier, $trackingNumber, $trackingUrl) {
            $order->update([
                'status'          => 'shipped',
                'carrier'         => $carrier,
                'tracking_number' => $trackingNumber,
                'tracking_url'    => $trackingUrl,
                'shipped_at'      => now(),
                'auto_release_at' => now()->addDays(15), // Görev 6.3: alıcı onaylamazsa muafiyetle serbest bırakma (15 gün)
            ]);

            $this->event($order, 'shipped', 'Kargoya verildi',
                $carrier.' • Takip No: '.$trackingNumber, $order->seller_id, 'bi-truck');

            $this->notify($order->buyer, $order, 'Siparişiniz kargoya verildi 🚚',
                $carrier.' • Takip No: '.$trackingNumber, 'bi-truck', '#8b5cf6');
        });
    }

    /** Alıcı teslim aldı (veya süre dolunca otomatik). shipped -> completed + para satıcıya. */
    public function confirmDelivered(Order $order, bool $auto = false): void
    {
        DB::transaction(function () use ($order, $auto) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || ! in_array($locked->status, ['shipped', 'delivered'], true)) {
                return;
            }

            // Çift ödeme koruması: para YALNIZCA emanette tutuluyorsa (held) satıcıya aktarılır.
            // Zaten serbest bırakılmış (released) veya iade edilmiş (refunded) escrow tekrar ödenmez.
            $canPayout = $locked->escrow_status === 'held';

            $payout = (float) $locked->amount - (float) $locked->commission_amount;

            $locked->update([
                'status'        => 'completed',
                'escrow_status' => $canPayout ? 'released' : $locked->escrow_status,
                'delivered_at'  => $locked->delivered_at ?? now(),
                'completed_at'  => now(),
                'dispute_window_ends_at' => now()->addDays(15), // Görev 6.3: serbest bırakma sonrası itiraz penceresi
            ]);

            if ($canPayout && $payout > 0) {
                $this->balance->credit(
                    user: $locked->seller,
                    amount: $payout,
                    paymentMethod: 'escrow_release',
                    description: 'Satış geliri — '.$locked->order_number,
                    reference: 'ESC-'.$locked->order_number,
                    meta: ['order_id' => $locked->id, 'commission' => (float) $locked->commission_amount],
                );
            }

            $this->event($locked, 'completed', $auto ? 'Otomatik teslim onayı' : 'Teslimat onaylandı',
                'Ödeme satıcıya aktarıldı (komisyon: '.number_format((float) $locked->commission_amount, 0, ',', '.').' ₺).',
                $auto ? null : $locked->buyer_id, 'bi-check-circle-fill');

            $this->notify($locked->seller, $locked, 'Ödemeniz serbest bırakıldı 💰',
                number_format($payout, 0, ',', '.').' ₺ bakiyenize eklendi.', 'bi-cash-stack', '#10b981');
            $this->notify($locked->buyer, $locked, 'Sipariş tamamlandı',
                'Teslimatı onayladığınız için teşekkürler.', 'bi-check-circle', '#10b981');
        });
    }

    /** Süresi dolan (7 gün) kargolanmış siparişleri otomatik tamamla. */
    public function autoReleaseExpired(): int
    {
        $count = 0;
        Order::where('status', 'shipped')
            ->whereNotNull('auto_release_at')
            ->where('auto_release_at', '<=', now())
            ->get()
            ->each(function (Order $order) use (&$count) {
                $this->confirmDelivered($order, auto: true);
                $count++;
            });
        return $count;
    }

    /** İptal / iade — para alıcıya geri döner. */
    public function cancelAndRefund(Order $order, string $reason, ?int $actorId = null): void
    {
        DB::transaction(function () use ($order, $reason, $actorId) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || in_array($locked->status, ['completed', 'cancelled'], true)) {
                return;
            }

            if ($locked->escrow_status === 'held') {
                $this->balance->credit(
                    user: $locked->buyer,
                    amount: (float) $locked->amount,
                    paymentMethod: 'refund',
                    description: 'Sipariş iadesi — '.$locked->order_number,
                    reference: 'RFN-'.$locked->order_number,
                    meta: ['order_id' => $locked->id],
                );
            }

            $locked->update([
                'status'         => 'cancelled',
                'escrow_status'  => $locked->escrow_status === 'held' ? 'refunded' : $locked->escrow_status,
                'cancelled_at'   => now(),
                'dispute_status' => $locked->dispute_status ? 'resolved_buyer' : $locked->dispute_status,
            ]);

            $this->event($locked, 'cancelled', 'Sipariş iptal edildi / iade',
                $reason, $actorId, 'bi-arrow-counterclockwise');

            $this->notify($locked->buyer, $locked, 'Siparişiniz iade edildi',
                number_format((float) $locked->amount, 0, ',', '.').' ₺ bakiyenize geri yüklendi.', 'bi-arrow-counterclockwise', '#6b7280');
            $this->notify($locked->seller, $locked, 'Sipariş iptal edildi', $reason, 'bi-x-circle', '#6b7280');
        });
    }

    /** Alıcı anlaşmazlık açar. */
    public function openDispute(Order $order, string $reason): void
    {
        $order->update([
            'status'         => 'disputed',
            'dispute_reason' => $reason,
            'dispute_status' => 'open',
            'disputed_at'    => now(),
        ]);

        $this->event($order, 'disputed', 'Anlaşmazlık açıldı', $reason, $order->buyer_id, 'bi-exclamation-octagon');

        $this->notify($order->seller, $order, 'Sipariş için anlaşmazlık açıldı',
            'Yönetici inceleyecek: '.Str::limit($reason, 60), 'bi-exclamation-octagon', '#ef4444');

        // Adminlere bildir
        User::role('admin')->get()->each(fn ($admin) => $this->notify(
            $admin, $order, 'Yeni anlaşmazlık — '.$order->order_number, Str::limit($reason, 60), 'bi-exclamation-octagon', '#ef4444'
        ));
    }

    /** Admin anlaşmazlığı çözer: 'buyer' => iade, 'seller' => satıcıya öde. */
    public function resolveDispute(Order $order, string $decision, ?int $adminId = null): void
    {
        if ($decision === 'buyer') {
            $this->cancelAndRefund($order, 'Anlaşmazlık alıcı lehine sonuçlandı (yönetici kararı).', $adminId);
            $order->update(['dispute_status' => 'resolved_buyer']);
        } else {
            $order->update(['status' => 'shipped']); // release akışına geri al
            $this->confirmDelivered($order, auto: true);
            $order->update(['dispute_status' => 'resolved_seller']);
            $this->event($order, 'completed', 'Anlaşmazlık satıcı lehine sonuçlandı', 'Yönetici kararı.', $adminId, 'bi-gavel');
        }
    }

    // Yardımcılar

    /** Görev 6.1: 48 saat ödenmeyen siparişleri işler; sıradaki teklife devreder (yoksa iptal eder).
     *  NOT: orders.auction_id UNIQUE olduğu için ilan başına tek sipariş satırı vardır; bu yüzden
     *  yeni satır oluşturmak yerine aynı sipariş satırı sıradaki uygun teklif sahibine DEVREDİLİR. */
    public function expireUnpaidOrders(int $hours = 48): int
    {
        $count = 0;
        Order::where('status', 'awaiting_payment')
            ->where('created_at', '<=', now()->subHours($hours))
            ->get()
            ->each(function (Order $order) use (&$count) {
                DB::transaction(function () use ($order) {
                    $locked = Order::whereKey($order->id)->lockForUpdate()->first();
                    if (! $locked || $locked->status !== 'awaiting_payment') {
                        return;
                    }

                    $failedBuyer   = $locked->buyer;
                    $failedBuyerId = (int) $locked->buyer_id;
                    $auction       = $locked->auction;

                    // Ödeme kaçırma sayacı (otomatik askıya alma YOK — sadece kayıt)
                    $failedBuyer?->increment('payment_misses');
                    $this->event($locked, 'cancelled', 'Ödeme yapılmadı (48 saat)',
                        'Süre içinde ödeme alınmadı.', null, 'bi-clock-history');
                    $this->notify($failedBuyer, $locked, 'Ödeme süreniz doldu',
                        'Kazandığınız ilan için ödeme yapılmadı.', 'bi-clock-history', '#ef4444');

                    $nextBid = $auction?->bids()->reorder()
                        ->where('user_id', '!=', $failedBuyerId)
                        ->where('user_id', '!=', $auction->user_id)
                        ->orderByDesc('amount')->orderBy('created_at')
                        ->first();

                    $reserve = (float) ($auction?->reserve_price ?? 0);

                    // Sıradaki uygun teklif yoksa → siparişi iptal et, ilan yeniden listelenebilir
                    if (! $auction || ! $nextBid || ($reserve > 0 && (float) $nextBid->amount < $reserve)) {
                        $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                        $auction?->forceFill(['status' => 'ended', 'winning_bid_id' => null])->save();
                        if ($auction) {
                            $this->notify($auction->user, $locked, 'Alıcı ödeme yapmadı',
                                $auction->title.' için kazanan ödemedi ve sıradaki uygun teklif yok. İlanı yeniden listeleyebilirsiniz.',
                                'bi-arrow-repeat', '#f59e0b');
                        }
                        return;
                    }

                    // Aynı sipariş satırını sıradaki teklif sahibine DEVRET
                    $rate       = (float) (function_exists('setting') ? setting('commission_rate', 10) : 10);
                    $amount     = (float) $nextBid->amount;
                    $commission = round($amount * $rate / 100, 2);

                    $locked->update([
                        'buyer_id'          => $nextBid->user_id,
                        'winning_bid_id'    => $nextBid->id,
                        'reoffer_of'        => $failedBuyerId, // ödemeyen önceki alıcı
                        'amount'            => $amount,
                        'commission_amount' => $commission,
                        'status'            => 'awaiting_payment',
                        'escrow_status'     => 'none',
                        'paid_at'           => null,
                        'ship_by_at'        => null,
                    ]);

                    $auction->forceFill([
                        'status'         => 'sold',
                        'winning_bid_id' => $nextBid->id,
                        'sold_at'        => now(),
                        'current_price'  => $amount,
                    ])->save();

                    $this->event($locked, 'awaiting_payment', 'Sıradaki teklife devredildi',
                        'Önceki kazanan ödemedi. '.number_format($amount, 0, ',', '.').' ₺ ile kazanan sizsiniz.', $nextBid->user_id, 'bi-trophy');

                    $this->tryHoldEscrow($locked);

                    $this->notify($locked->fresh()->buyer, $locked, 'Sıradaki teklif sizin! 🎉',
                        $auction->title.' — önceki alıcı ödemedi. '.number_format($amount, 0, ',', '.').' ₺ ile kazandınız, 48 saat içinde ödeyebilirsiniz.',
                        'bi-trophy', '#f59e0b');
                });
                $count++;
            });

        return $count;
    }

    /** Görev 6.2: Kargolama son tarihi geçmiş (paid) siparişleri iptal et + tam iade. */
    public function cancelUnshippedOrders(): int
    {
        $count = 0;
        Order::where('status', 'paid')
            ->whereNotNull('ship_by_at')
            ->where('ship_by_at', '<=', now())
            ->get()
            ->each(function (Order $order) use (&$count) {
                $this->cancelAndRefund($order, 'Satıcı süresi içinde kargoya vermedi; sipariş iptal edildi ve tam iade yapıldı.');
                $order->fresh()->seller?->increment('shipping_violations');
                $count++;
            });

        return $count;
    }

    /** Görev 6.3: Serbest bırakılmış (completed) siparişte itiraz penceresi içinde admin geri alır. */
    public function reverseRelease(Order $order, string $reason, ?int $adminId = null): bool
    {
        return (bool) DB::transaction(function () use ($order, $reason, $adminId) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'completed' || $locked->escrow_status !== 'released') {
                return false;
            }
            if (! $locked->dispute_window_ends_at || $locked->dispute_window_ends_at->isPast()) {
                return false;
            }

            $payout = (float) $locked->amount - (float) $locked->commission_amount;
            try {
                $this->balance->debit(
                    user: $locked->seller, amount: $payout,
                    description: 'Serbest bırakma geri alındı — '.$locked->order_number,
                    meta: ['order_id' => $locked->id, 'type' => 'release_reversal'],
                );
            } catch (\RuntimeException $e) {
                report($e); // satıcı bakiyesi yetersiz — platform riski, iade yine de yapılır
            }

            $this->balance->credit(
                user: $locked->buyer, amount: (float) $locked->amount,
                paymentMethod: 'refund', description: 'İtiraz sonrası iade — '.$locked->order_number,
                reference: 'REV-'.$locked->order_number, meta: ['order_id' => $locked->id],
            );

            $locked->update([
                'status'         => 'cancelled',
                'escrow_status'  => 'refunded',
                'cancelled_at'   => now(),
                'dispute_status' => 'resolved_buyer',
            ]);

            $this->event($locked, 'cancelled', 'Serbest bırakma geri alındı (itiraz)', $reason, $adminId, 'bi-arrow-counterclockwise');
            $this->notify($locked->buyer, $locked, 'İade yapıldı',
                number_format((float) $locked->amount, 0, ',', '.').' ₺ bakiyenize geri yüklendi.', 'bi-arrow-counterclockwise', '#10b981');
            $this->notify($locked->seller, $locked, 'Serbest bırakma geri alındı', $reason, 'bi-x-circle', '#ef4444');

            return true;
        });
    }

    private function tryHoldEscrowSafe(Order $order): void
    {
        try { $this->tryHoldEscrow($order); } catch (\Throwable $e) { report($e); }
    }

    private function event(Order $order, string $status, string $title, ?string $desc, ?int $actorId, string $icon): OrderEvent
    {
        return OrderEvent::create([
            'order_id'    => $order->id,
            'status'      => $status,
            'title'       => $title,
            'description' => $desc,
            'actor_id'    => $actorId,
            'icon'        => $icon,
        ]);
    }

    private function notify(User $user, Order $order, string $title, string $message, string $icon, string $color): void
    {
        try {
            $user->notify(new OrderNotification($order, $title, $message, $icon, $color));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ART-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
