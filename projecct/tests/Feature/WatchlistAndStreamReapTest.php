<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchlistAndStreamReapTest extends TestCase
{
    use RefreshDatabase;

    private function auction(User $seller, array $overrides = []): Auction
    {
        return Auction::create(array_merge([
            'user_id'           => $seller->id,
            'title'             => 'İzlenecek İlan',
            'description'       => 'Test',
            'starting_price'    => 100,
            'current_price'     => 100,
            'min_bid_increment' => 10,
            'starts_at'         => now()->subDay(),
            'ends_at'           => now()->addDay(),
            'status'            => 'active',
        ], $overrides));
    }

    public function test_watch_toggle_adds_and_removes(): void
    {
        $seller = User::factory()->create();
        $buyer  = User::factory()->create();
        $auction = $this->auction($seller);

        $this->actingAs($buyer)
            ->postJson(route('auctions.watch', $auction->slug))
            ->assertOk()
            ->assertJson(['watching' => true, 'count' => 1]);

        $this->assertTrue($buyer->watchlist()->where('auctions.id', $auction->id)->exists());

        $this->actingAs($buyer)
            ->postJson(route('auctions.watch', $auction->slug))
            ->assertOk()
            ->assertJson(['watching' => false, 'count' => 0]);

        $this->assertFalse($buyer->fresh()->watchlist()->where('auctions.id', $auction->id)->exists());
    }

    public function test_cannot_watch_own_auction(): void
    {
        $seller = User::factory()->create();
        $auction = $this->auction($seller);

        $this->actingAs($seller)
            ->postJson(route('auctions.watch', $auction->slug))
            ->assertStatus(422);
    }

    public function test_reap_closes_stale_live_streams(): void
    {
        $seller = User::factory()->create();

        $stale = $this->auction($seller, [
            'is_live'           => true,
            'live_started_at'   => now()->subMinutes(5),
            'last_heartbeat_at' => now()->subMinutes(3),
        ]);

        $fresh = $this->auction($seller, [
            'is_live'           => true,
            'live_started_at'   => now()->subSeconds(5),
            'last_heartbeat_at' => now()->subSeconds(5),
        ]);

        $this->artisan('streams:reap')->assertSuccessful();

        $this->assertFalse((bool) $stale->fresh()->is_live, 'Kopmuş yayın kapatılmalı');
        $this->assertTrue((bool) $fresh->fresh()->is_live, 'Taze yayın açık kalmalı');
    }
}
