<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuctionClosingTest extends TestCase
{
    use RefreshDatabase;

    private function dueAuction(User $seller, float $reserve = 0): Auction
    {
        return Auction::create([
            'user_id' => $seller->id,
            'title' => 'Kapanan İlan',
            'description' => 'Test',
            'starting_price' => 100,
            'current_price' => 100,
            'min_bid_increment' => 10,
            'reserve_price' => $reserve ?: null,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subMinute(),
            'status' => 'active',
        ]);
    }

    public function test_auction_below_reserve_ends_without_order(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = $this->dueAuction($seller, reserve: 1000);

        Bid::create(['auction_id' => $auction->id, 'user_id' => $buyer->id, 'amount' => 150]);

        app(OrderService::class)->closeDueAuctions();

        $this->assertSame('ended', $auction->fresh()->status);
        $this->assertSame(0, Order::where('auction_id', $auction->id)->count());
    }

    public function test_auction_above_reserve_creates_order_and_marks_sold(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = $this->dueAuction($seller, reserve: 100);

        $bid = Bid::create(['auction_id' => $auction->id, 'user_id' => $buyer->id, 'amount' => 300]);

        $result = app(OrderService::class)->closeDueAuctions();

        $this->assertSame(1, $result['sold']);
        $this->assertSame('sold', $auction->fresh()->status);

        $order = Order::where('auction_id', $auction->id)->first();
        $this->assertNotNull($order);
        $this->assertSame($buyer->id, $order->buyer_id);
        $this->assertSame($seller->id, $order->seller_id);
        $this->assertEquals(300.0, (float) $order->amount);
        $this->assertSame($bid->id, $order->winning_bid_id);
    }

    public function test_auction_with_no_bids_ends_without_order(): void
    {
        $seller = User::factory()->create();
        $auction = $this->dueAuction($seller);

        app(OrderService::class)->closeDueAuctions();

        $this->assertSame('ended', $auction->fresh()->status);
        $this->assertSame(0, Order::where('auction_id', $auction->id)->count());
    }
}
