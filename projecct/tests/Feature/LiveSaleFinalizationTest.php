<?php

namespace Tests\Feature;

use App\Jobs\FinalizeSaleJob;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveSaleFinalizationTest extends TestCase
{
    use RefreshDatabase;

    private function liveAuction(User $seller, float $reserve = 0): Auction
    {
        $endsAt = now()->addSeconds(10);

        return Auction::create([
            'user_id'           => $seller->id,
            'title'             => 'Canlı Satış İlanı',
            'description'       => 'Test',
            'starting_price'    => 100,
            'current_price'     => 100,
            'min_bid_increment' => 10,
            'reserve_price'     => $reserve ?: null,
            'starts_at'         => now()->subDay(),
            'ends_at'           => now()->addDay(),
            'status'            => 'active',
            'is_live'           => true,
            'countdown_started_at' => now(),
            'countdown_ends_at'    => $endsAt,
        ]);
    }

    private function token(Auction $a): int
    {
        return $a->countdown_ends_at->getTimestamp();
    }

    public function test_job_finalizes_with_current_highest_bid_not_the_starting_one(): void
    {
        $seller = User::factory()->create();
        $b1 = User::factory()->create();
        $b2 = User::factory()->create();
        $auction = $this->liveAuction($seller);
        $auction->forceFill(['countdown_bid_id' => null])->save();

        // İlk teklif (geri sayım bunun için başlamış gibi)
        $lowBid = Bid::create(['auction_id' => $auction->id, 'user_id' => $b1->id, 'amount' => 200]);
        $auction->forceFill(['countdown_bid_id' => $lowBid->id, 'current_price' => 200])->save();

        // Geri sayım sırasında daha yüksek teklif geldi (iptal edilmeden)
        $highBid = Bid::create(['auction_id' => $auction->id, 'user_id' => $b2->id, 'amount' => 500]);
        $auction->forceFill(['current_price' => 500])->save();

        (new FinalizeSaleJob($auction->id, $this->token($auction)))->handle(app(\App\Services\OrderService::class));

        $order = Order::where('auction_id', $auction->id)->first();
        $this->assertNotNull($order, 'Sipariş oluşmalı');
        $this->assertSame($highBid->id, $order->winning_bid_id, 'En güncel en yüksek teklif kazanmalı');
        $this->assertSame($b2->id, $order->buyer_id);
        $this->assertSame('sold', $auction->fresh()->status);
    }

    public function test_cancelled_countdown_does_not_finalize(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = $this->liveAuction($seller);
        Bid::create(['auction_id' => $auction->id, 'user_id' => $buyer->id, 'amount' => 300]);
        $auction->forceFill(['countdown_cancelled_at' => now()])->save();

        (new FinalizeSaleJob($auction->id, $this->token($auction)))->handle(app(\App\Services\OrderService::class));

        $this->assertSame(0, Order::where('auction_id', $auction->id)->count());
        $this->assertSame('active', $auction->fresh()->status);
    }

    public function test_stale_token_is_ignored(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = $this->liveAuction($seller);
        Bid::create(['auction_id' => $auction->id, 'user_id' => $buyer->id, 'amount' => 300]);

        // Eski/yanlış token → yeni bir geri sayım başlamış demektir, iş çalışmamalı
        (new FinalizeSaleJob($auction->id, $this->token($auction) + 999))->handle(app(\App\Services\OrderService::class));

        $this->assertSame(0, Order::where('auction_id', $auction->id)->count());
    }

    public function test_reserve_price_blocks_sale(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = $this->liveAuction($seller, reserve: 1000);
        Bid::create(['auction_id' => $auction->id, 'user_id' => $buyer->id, 'amount' => 300]);

        (new FinalizeSaleJob($auction->id, $this->token($auction)))->handle(app(\App\Services\OrderService::class));

        $this->assertSame(0, Order::where('auction_id', $auction->id)->count());
        $this->assertSame('active', $auction->fresh()->status);
    }
}
