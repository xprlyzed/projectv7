<?php

namespace Tests\Feature;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'seller', 'buyer'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $user = User::factory()->create(['email' => 'victim@test.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'victim@test.com', 'password' => 'wrong']);
        }

        // 6. deneme adlandırılmış 'login' limiter (email+ip) ile 429 döner.
        $response = $this->post('/login', ['email' => 'victim@test.com', 'password' => 'wrong']);
        $response->assertStatus(429);
    }

    public function test_login_throttle_is_per_account_not_only_ip(): void
    {
        User::factory()->create(['email' => 'a@test.com']);
        User::factory()->create(['email' => 'b@test.com']);

        // a@ hesabını kilitle
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'a@test.com', 'password' => 'wrong']);
        }

        // b@ farklı hesap → aynı IP olsa bile bağımsız, kilitli DEĞİL (429 değil)
        $response = $this->post('/login', ['email' => 'b@test.com', 'password' => 'wrong']);
        $this->assertNotEquals(429, $response->getStatusCode());
    }

    public function test_unverified_user_cannot_place_bid(): void
    {
        $seller = User::factory()->create();
        $seller->assignRole('seller');

        $buyer = User::factory()->create(['email_verified_at' => null, 'is_verified' => false]);
        $buyer->assignRole('buyer');

        $auction = Auction::create([
            'user_id' => $seller->id,
            'title' => 'Doğrulama Testi',
            'description' => 'Test',
            'starting_price' => 100,
            'current_price' => 100,
            'min_bid_increment' => 10,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($buyer)
            ->post("/auctions/{$auction->slug}/bid", ['amount' => 110]);

        // verified.account middleware doğrulanmamış kullanıcıyı doğrulama sayfasına yönlendirir.
        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('bids', 0);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@test.com',
            'password' => bcrypt('password'),
            'suspended_at' => now(),
            'suspension_reason' => 'Test',
        ]);
        $user->assignRole('buyer');

        $response = $this->post('/login', ['email' => 'suspended@test.com', 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_security_headers_present_on_response(): void
    {
        $response = $this->get('/login');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
    }
}
