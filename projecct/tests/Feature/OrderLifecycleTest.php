<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function soldAuction(User $seller): Auction
    {
        return Auction::create([
            'user_id' => $seller->id,
            'title' => 'Satılan İlan',
            'description' => 'Test',
            'starting_price' => 100,
            'current_price' => 300,
            'min_bid_increment' => 10,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            'status' => 'sold',
        ]);
    }

    public function test_unpaid_order_expires_and_offers_to_second_bidder(): void
    {
        $seller = User::factory()->create();
        $winner = User::factory()->create(['balance' => 0]);
        $second = User::factory()->create(['balance' => 0]);
        $auction = $this->soldAuction($seller);

        $winBid = Bid::create(['auction_id' => $auction->id, 'user_id' => $winner->id, 'amount' => 300]);
        Bid::create(['auction_id' => $auction->id, 'user_id' => $second->id, 'amount' => 250]);
        $auction->update(['winning_bid_id' => $winBid->id]);

        $order = Order::create([
            'order_number' => 'ART-TEST-1', 'auction_id' => $auction->id,
            'buyer_id' => $winner->id, 'seller_id' => $seller->id, 'winning_bid_id' => $winBid->id,
            'amount' => 300, 'commission_amount' => 30, 'status' => 'awaiting_payment', 'escrow_status' => 'none',
        ]);
        $order->created_at = now()->subHours(49);
        $order->save();

        app(OrderService::class)->expireUnpaidOrders();

        $this->assertSame(1, (int) $winner->fresh()->payment_misses);

        // orders.auction_id UNIQUE → aynı sipariş satırı ikinci teklife devredilir
        $reoffer = $order->fresh();
        $this->assertSame('awaiting_payment', $reoffer->status);
        $this->assertSame($second->id, $reoffer->buyer_id);
        $this->assertEquals(250.0, (float) $reoffer->amount);
        $this->assertSame($winner->id, (int) $reoffer->reoffer_of);
    }

    public function test_unshipped_paid_order_is_cancelled_and_refunded(): void
    {
        $seller = User::factory()->create(['balance' => 0]);
        $buyer  = User::factory()->create(['balance' => 0]);
        $auction = $this->soldAuction($seller);

        $order = Order::create([
            'order_number' => 'ART-TEST-2', 'auction_id' => $auction->id,
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'amount' => 300, 'commission_amount' => 30,
            'status' => 'paid', 'escrow_status' => 'held',
            'paid_at' => now()->subDays(4), 'ship_by_at' => now()->subDay(),
        ]);

        app(OrderService::class)->cancelUnshippedOrders();

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('refunded', $fresh->escrow_status);
        $this->assertEquals(300.0, (float) $buyer->fresh()->balance, 'Alıcıya tam iade yapılmalı');
        $this->assertSame(1, (int) $seller->fresh()->shipping_violations);
    }

    public function test_admin_can_reverse_release_within_dispute_window(): void
    {
        $seller = User::factory()->create(['balance' => 270]);
        $buyer  = User::factory()->create(['balance' => 0]);
        $auction = $this->soldAuction($seller);

        $order = Order::create([
            'order_number' => 'ART-TEST-3', 'auction_id' => $auction->id,
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'amount' => 300, 'commission_amount' => 30,
            'status' => 'completed', 'escrow_status' => 'released',
            'completed_at' => now(), 'dispute_window_ends_at' => now()->addDays(10),
        ]);

        $ok = app(OrderService::class)->reverseRelease($order, 'Ürün hasarlı geldi (test).');

        $this->assertTrue($ok);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertEquals(300.0, (float) $buyer->fresh()->balance);
        $this->assertEquals(0.0, (float) $seller->fresh()->balance, 'Satıcıdan net tutar geri alınmalı');
    }

    public function test_reverse_release_blocked_after_window(): void
    {
        $seller = User::factory()->create(['balance' => 270]);
        $buyer  = User::factory()->create(['balance' => 0]);
        $auction = $this->soldAuction($seller);

        $order = Order::create([
            'order_number' => 'ART-TEST-4', 'auction_id' => $auction->id,
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'amount' => 300, 'commission_amount' => 30,
            'status' => 'completed', 'escrow_status' => 'released',
            'completed_at' => now()->subDays(20), 'dispute_window_ends_at' => now()->subDays(5),
        ]);

        $ok = app(OrderService::class)->reverseRelease($order, 'Geç itiraz (test).');

        $this->assertFalse($ok);
        $this->assertSame('completed', $order->fresh()->status);
    }
}
