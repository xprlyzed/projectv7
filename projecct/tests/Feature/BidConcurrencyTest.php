<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BidConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function activeAuction(User $seller, array $overrides = []): Auction
    {
        return Auction::create(array_merge([
            'user_id' => $seller->id,
            'title' => 'Konkurent Teklif İlanı',
            'description' => 'Test açıklaması',
            'starting_price' => 100,
            'current_price' => 100,
            'min_bid_increment' => 10,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'status' => 'active',
        ], $overrides));
    }

    public function test_valid_bid_is_accepted_and_updates_current_price(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = $this->activeAuction($seller);

        $response = $this->actingAs($buyer)
            ->postJson(route('bids.store', $auction), ['amount' => 110]);

        $response->assertOk();
        $this->assertEquals(110.0, (float) $auction->fresh()->current_price);
        $this->assertSame(1, Bid::where('auction_id', $auction->id)->count());
    }

    public function test_bid_below_minimum_increment_is_rejected(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $auction = $this->activeAuction($seller);

        // İlk geçerli teklif: current 100 -> 110
        $this->actingAs($buyer)->postJson(route('bids.store', $auction), ['amount' => 110])->assertOk();

        // Yeni minimum 110 + 10 = 120; 115 reddedilmeli
        $response = $this->actingAs($buyer)
            ->postJson(route('bids.store', $auction), ['amount' => 115]);

        $response->assertStatus(422);
        $this->assertEquals(110.0, (float) $auction->fresh()->current_price);
        $this->assertSame(1, Bid::where('auction_id', $auction->id)->count());
    }

    public function test_owner_cannot_bid_on_own_auction(): void
    {
        $seller = User::factory()->create();
        $auction = $this->activeAuction($seller);

        $response = $this->actingAs($seller)
            ->postJson(route('bids.store', $auction), ['amount' => 500]);

        $response->assertStatus(422);
        $this->assertSame(0, Bid::where('auction_id', $auction->id)->count());
    }

    public function test_sequential_bids_serialize_and_only_valid_ones_win(): void
    {
        $seller = User::factory()->create();
        $b1 = User::factory()->create();
        $b2 = User::factory()->create();
        $auction = $this->activeAuction($seller);

        $this->actingAs($b1)->postJson(route('bids.store', $auction), ['amount' => 110])->assertOk();
        // b2 aynı fiyatı verirse (110) reddedilmeli, min 120
        $this->actingAs($b2)->postJson(route('bids.store', $auction), ['amount' => 110])->assertStatus(422);
        // b2 geçerli yüksek teklif
        $this->actingAs($b2)->postJson(route('bids.store', $auction), ['amount' => 120])->assertOk();

        $this->assertSame(2, Bid::where('auction_id', $auction->id)->count());
        $this->assertEquals(120.0, (float) $auction->fresh()->current_price);
    }
}
