<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\BalanceTransaction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\BalanceService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'seller', 'buyer'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    public function test_bank_transfer_topup_stays_pending_and_does_not_credit(): void
    {
        $user = User::factory()->create(['balance' => 0]);
        $tx = app(BalanceService::class)->requestBankTransfer($user, 500);

        $this->assertEquals('pending', $tx->status);
        $this->assertNotNull($tx->reference_code);
        $this->assertEquals(0, (float) $user->fresh()->balance);
    }

    public function test_topup_approval_credits_once_even_if_called_twice(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create(['balance' => 0]);

        $tx = app(BalanceService::class)->requestBankTransfer($user, 500);

        $first  = app(BalanceService::class)->approvePendingTopUp($tx, $admin);
        $second = app(BalanceService::class)->approvePendingTopUp($tx->fresh(), $admin);

        $this->assertTrue($first);
        $this->assertFalse($second); // idempotent — ikinci çağrı hiçbir şey yapmaz
        $this->assertEquals(500, (float) $user->fresh()->balance);
        $this->assertEquals('completed', $tx->fresh()->status);
    }

    public function test_topup_rejection_does_not_credit(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create(['balance' => 0]);

        $tx = app(BalanceService::class)->requestBankTransfer($user, 500);
        $ok = app(BalanceService::class)->rejectPendingTopUp($tx, $admin, 'Ödeme bulunamadı');

        $this->assertTrue($ok);
        $this->assertEquals('rejected', $tx->fresh()->status);
        $this->assertEquals('Ödeme bulunamadı', $tx->fresh()->rejection_reason);
        $this->assertEquals(0, (float) $user->fresh()->balance);
    }

    public function test_escrow_release_pays_seller_once_and_only_when_held(): void
    {
        $orders = app(OrderService::class);
        $balance = app(BalanceService::class);

        $seller = User::factory()->create(['balance' => 0]);
        $seller->assignRole('seller');
        $buyer = User::factory()->create(['balance' => 1000]);
        $buyer->assignRole('buyer');

        $auction = Auction::create([
            'user_id' => $seller->id, 'title' => 'Escrow', 'description' => 'x',
            'starting_price' => 100, 'current_price' => 500, 'min_bid_increment' => 10,
            'starts_at' => now()->subDay(), 'ends_at' => now()->subMinute(), 'status' => 'active',
        ]);
        $bid = Bid::create(['auction_id' => $auction->id, 'user_id' => $buyer->id, 'amount' => 500]);

        $order = $orders->createFromWinningBid($auction, $bid); // bakiye yeterli → escrow held
        $this->assertEquals('held', $order->fresh()->escrow_status);

        $order->update(['status' => 'shipped']);

        $orders->confirmDelivered($order->fresh());
        $orders->confirmDelivered($order->fresh()); // ikinci çağrı çift ödeme YAPMAMALI

        // commission default %10 → payout 450, tek sefer
        $this->assertEquals(450, (float) $seller->fresh()->balance);
        $this->assertEquals('released', $order->fresh()->escrow_status);
    }

    public function test_withdrawal_rejection_refunds_reserved_balance(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $seller = User::factory()->create(['balance' => 1000]);
        $seller->assignRole('seller');
        \App\Models\SellerProfile::create([
            'user_id' => $seller->id,
            'company_name' => 'Test A.Ş.',
            'tax_number' => '1234567890',
            'iban' => 'TR120000000000000000000000',
            'verification_status' => 'approved',
            'verified_at' => now(),
        ]);

        // Talep oluştur (bakiye rezerve = debit)
        $this->actingAs($seller)->post(route('general.balance.withdraw'), [
            'amount' => 300,
            'iban' => 'TR12 3456 7890 1234 5678 9012 34',
        ]);

        $this->assertEquals(700, (float) $seller->fresh()->balance);
        $w = WithdrawalRequest::first();
        $this->assertNotNull($w);
        $this->assertEquals('pending', $w->status);

        // Admin reddeder → bakiye geri yüklenir
        $this->actingAs($admin)->post(route('admin.withdrawals.reject', $w->id), [
            'reason' => 'Hatalı IBAN',
        ]);

        $this->assertEquals('rejected', $w->fresh()->status);
        $this->assertEquals(1000, (float) $seller->fresh()->balance);
    }

    public function test_user_with_active_auction_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $seller = User::factory()->create();
        $seller->assignRole('seller');

        Auction::create([
            'user_id' => $seller->id, 'title' => 'Aktif', 'description' => 'x',
            'starting_price' => 100, 'current_price' => 100, 'min_bid_increment' => 10,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $seller->id));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $seller->id, 'deleted_at' => null]);
    }
}
