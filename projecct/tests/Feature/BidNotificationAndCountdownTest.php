<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Notifications\OutbidNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BidNotificationAndCountdownTest extends TestCase
{
    use RefreshDatabase;

    private function activeAuction(User $seller): Auction
    {
        return Auction::create([
            'user_id'           => $seller->id,
            'title'             => 'Aktif İlan',
            'description'       => 'Test',
            'starting_price'    => 100,
            'current_price'     => 100,
            'min_bid_increment' => 10,
            'starts_at'         => now()->subDay(),
            'ends_at'           => now()->addDay(),
            'status'            => 'active',
        ]);
    }

    public function test_previous_top_bidder_is_notified_on_outbid(): void
    {
        Notification::fake();

        $seller = User::factory()->create();
        $b1 = User::factory()->create();
        $b2 = User::factory()->create();
        $auction = $this->activeAuction($seller);

        // b1 ilk teklifi verir
        Bid::create(['auction_id' => $auction->id, 'user_id' => $b1->id, 'amount' => 150]);
        $auction->update(['current_price' => 150]);

        // b2 daha yüksek teklif verir → b1 "geçildi" bildirimi almalı
        $this->actingAs($b2)
            ->postJson(route('bids.store', $auction->slug), ['amount' => 200])
            ->assertOk();

        Notification::assertSentTo($b1, OutbidNotification::class);
        Notification::assertNotSentTo($b2, OutbidNotification::class);
    }

    public function test_new_bid_cancels_active_countdown(): void
    {
        $seller = User::factory()->create();
        $b1 = User::factory()->create();
        $b2 = User::factory()->create();
        $auction = $this->activeAuction($seller);
        Bid::create(['auction_id' => $auction->id, 'user_id' => $b1->id, 'amount' => 150]);
        $auction->update([
            'current_price'        => 150,
            'countdown_started_at' => now(),
            'countdown_ends_at'    => now()->addSeconds(10),
        ]);

        $this->actingAs($b2)
            ->postJson(route('bids.store', $auction->slug), ['amount' => 200])
            ->assertOk();

        $this->assertNotNull($auction->fresh()->countdown_cancelled_at, 'Yeni teklif geri sayımı iptal etmeli');
    }
}
