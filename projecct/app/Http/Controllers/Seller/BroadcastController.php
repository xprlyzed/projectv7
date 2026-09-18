<?php

namespace App\Http\Controllers\Seller;

use App\Events\AuctionSold;
use App\Http\Controllers\Controller;
use App\Jobs\FinalizeSaleJob;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\SellerWentLiveNotification;
use App\Services\OrderService;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function show(Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);
        abort_unless($auction->canBroadcast(), 403, 'Bu ilan yayına uygun değil. Yalnızca onaylı (aktif) ilanlar canlı yayına başlayabilir.');

        // Yayın moduna geç (canlı). is_live YALNIZCA kamera başlatılınca true olur
        // (liveStatus endpoint'i ile) — böylece izleyici "Canlı İzle" sekmesini
        // yalnızca gerçekten yayın varken görür.
        if (! $auction->hasFinished() && $auction->stream_mode !== 'live') {
            $auction->update(['stream_mode' => 'live']);
        }

        $bids = $auction->bids()->with('user:id,name')->orderByDesc('amount')->take(50)->get();
        $topBid = $bids->first();

        return \Inertia\Inertia::render('Seller/Broadcast', [
            'auction' => [
                'id'          => $auction->id,
                'slug'        => $auction->slug,
                'title'       => $auction->title,
                'is_live'     => (bool) $auction->is_live,
                'has_finished' => $auction->hasFinished(),
                'top_amount'  => $topBid?->amount,
                'current_price' => (float) $auction->current_price,
                'ends_at_ts'  => optional($auction->ends_at)->getTimestamp(),
                'bid_count'   => $bids->count(),
            ],
            'bids' => $bids->map(fn (Bid $b) => [
                'id'     => $b->id,
                'amount' => (float) $b->amount,
                'name'   => $b->user?->name ?? 'Kullanıcı',
                'time'   => optional($b->created_at)->format('H:i'),
            ])->values(),
            'routes' => [
                'sell'        => route('seller.auctions.sell', $auction),
                'start_countdown' => route('seller.auctions.start-countdown', $auction),
                'end'         => route('seller.auctions.end-broadcast', $auction),
                'live_status' => route('seller.auctions.live-status', $auction),
                'chat_poll'   => route('auctions.chat.poll', $auction),
                'chat_store'  => route('auctions.chat.store', $auction),
                'view_public' => route('auctions.show', $auction),
            ],
        ]);
    }


    /**
     * Satıcı kamerayı başlatınca/durdurunca canlı durumu günceller.
     * Bu sayede izleyici tarafındaki "Canlı İzle" sekmesi yalnızca yayın açıkken görünür.
     */
    public function liveStatus(Request $request, Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);

        $live = $request->boolean('live');

        // Yayına yalnızca onaylı (aktif) ilan geçebilir. Kapatma (live=false) her zaman serbest.
        if ($live && ! $auction->canBroadcast()) {
            return response()->json(['success' => false, 'message' => 'Bu ilan yayına uygun değil.'], 403);
        }

        if ($auction->hasFinished()) {
            $live = false;
        }

        $wasLive = (bool) $auction->is_live;

        $auction->update([
            'is_live'         => $live,
            'live_started_at' => $live ? ($auction->live_started_at ?? now()) : $auction->live_started_at,
            'live_ended_at'   => $live ? null : now(),
            'last_heartbeat_at' => $live ? now() : $auction->last_heartbeat_at,
        ]);

        // Görev 4.3: Yayın YENİ açıldıysa (kapalı→açık) takipçilere bildir (kuyruğa atılır).
        if ($live && ! $wasLive) {
            $this->notifyFollowersWentLive($auction);
        }

        return response()->json(['success' => true, 'is_live' => $live]);
    }

    /**
     * Görev 3: Canlı yayın kalp atışı. İstemci ~20 sn'de bir çağırır; son görülme
     * zamanını günceller. streams:reap komutu bu zamana göre kopmuş yayınları kapatır.
     */
    public function heartbeat(Request $request, Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);

        $auction->forceFill(['last_heartbeat_at' => now()])->save();

        return response()->json(['ok' => true]);
    }

    /** Yayına yeni geçen satıcının takipçilerine bildirim gönderir. */
    private function notifyFollowersWentLive(Auction $auction): void
    {
        $seller = $auction->user;
        if (! $seller) {
            return;
        }

        $followerIds = $seller->followers()->pluck('follower_id');
        if ($followerIds->isEmpty()) {
            return;
        }

        User::whereIn('id', $followerIds)->get()->each(function (User $follower) use ($seller, $auction) {
            try {
                $follower->notify(new SellerWentLiveNotification($seller, $auction));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Yayın modunu ve tanıtım videosunu günceller.
     * Satıcı canlı yayın yerine ürün tanıtım videosu ekleyebilir.
     */
    public function streamSettings(Request $request, Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);

        $data = $request->validate([
            'stream_mode'     => ['required', 'in:live,video'],
            'promo_video_url' => ['nullable', 'url', 'max:2048', 'required_if:stream_mode,video'],
        ], [
            'promo_video_url.required_if' => 'Tanıtım videosu modu için bir video linki gir.',
            'promo_video_url.url'         => 'Geçerli bir video linki gir (YouTube, Vimeo veya .mp4).',
        ]);

        $update = ['stream_mode' => $data['stream_mode']];

        if ($data['stream_mode'] === 'video') {
            $update['promo_video_url'] = $data['promo_video_url'];
            // Video moduna geçince canlı yayını kapat
            $update['is_live']       = false;
            $update['live_ended_at'] = $auction->is_live ? now() : $auction->live_ended_at;
        } else {
            $update['promo_video_url'] = $request->input('promo_video_url') ?: null;
        }

        $auction->update($update);

        return back()->with('profile_success', 'Yayın ayarların güncellendi.');
    }

    public function sell(Request $request, Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);
        abort_unless($auction->canBroadcast(), 403, 'Bu ilan için satış yapılamaz (yalnızca aktif ilan).');

        $validated = $request->validate([
            'bid_id' => ['required', 'integer', 'exists:bids,id'],
        ]);

        $bid = Bid::where('id', $validated['bid_id'])
            ->where('auction_id', $auction->id)
            ->firstOrFail();

        // Emanet tabanlı sipariş oluştur (kazananı belirle, açık artırmayı kapat)
        $order = $this->orders->createFromWinningBid($auction, $bid);

        // Satış sonrası canlı yayını da kapat
        $auction->update(['is_live' => false, 'live_ended_at' => now()]);

        broadcast(new AuctionSold(
            auction      : $auction,
            buyerName    : $bid->user->name,
            amount       : $bid->amount,
            displayPrice : number_format($bid->amount, 0, ',', '.').' ₺',
        ));

        // Gerçek-zamanlı: odaya "satıldı" bildir (büyük konfeti + "İlan Satıldı")
        \App\Services\LiveKitPublisher::publish($auction->id, 'auction-sold', [
            'winner_name' => $bid->user->name,
            'amount'      => (float) $bid->amount,
            'display'     => number_format($bid->amount, 0, ',', '.').' ₺',
        ]);

        return response()->json([
            'success'      => true,
            'winner_name'  => $bid->user->name,
            'amount'       => $bid->amount,
            'order_number' => $order->order_number,
        ]);
    }

    /**
     * "İlanı Sat" → izleyici ve satıcı ekranında 10 sn geri sayım başlatır.
     * Bu sürede yeni teklif gelirse (new-bid) istemciler sayacı iptal eder.
     * Süre dolunca satıcı ekranı en yüksek teklif sahibine satışı tetikler.
     */
    public function startSellCountdown(Request $request, Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);
        abort_unless($auction->canBroadcast(), 403, 'Bu ilan için satış başlatılamaz.');

        $top = $auction->bids()->with('user:id,name')->orderByDesc('amount')->first();
        if (! $top) {
            return response()->json(['message' => 'Satış başlatmak için en az bir teklif olmalı.'], 422);
        }

        $seconds = 10;
        $endsAtDt = now()->addSeconds($seconds);
        $endsAt   = (int) round(microtime(true) * 1000) + $seconds * 1000;

        // Sunucu-otoriter geri sayım durumu (geç katılan izleyici senkronu + job token'ı)
        $auction->forceFill([
            'countdown_started_at'   => now(),
            'countdown_ends_at'      => $endsAtDt,
            'countdown_bid_id'       => $top->id,
            'countdown_cancelled_at' => null,
        ])->save();

        \App\Services\LiveKitPublisher::publish($auction->id, 'sell-countdown', [
            'seconds' => $seconds,
            'ends_at' => $endsAt,
            'bid_id'  => $top->id,
            'amount'  => (float) $top->amount,
            'display' => number_format($top->amount, 0, ',', '.').' ₺',
            'name'    => $top->user?->name ?? 'Kullanıcı',
        ]);

        // Görev 2: Satışı SUNUCU sonlandırır. İstemci geri sayımı yalnızca görseldir.
        FinalizeSaleJob::dispatch($auction->id, $endsAtDt->getTimestamp())
            ->delay($endsAtDt);

        return response()->json([
            'success' => true,
            'bid_id'  => $top->id,
            'seconds' => $seconds,
            'ends_at' => $endsAt,
            'server_authoritative' => true,
        ]);
    }

    // web.php'de 'end-broadcast' route'u bu metoda bağlı
    public function endBroadcast(Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);

        // Yayını her durumda "canlı değil" yap ki izleyici tarafında socket/polling dursun
        $auction->update([
            'is_live'       => false,
            'live_ended_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
