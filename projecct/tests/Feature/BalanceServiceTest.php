<?php

namespace Tests\Feature;

use App\Models\BalanceTransaction;
use App\Models\User;
use App\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_increases_balance_and_logs_transaction(): void
    {
        $svc = app(BalanceService::class);
        $user = User::factory()->create(['balance' => 0]);

        $svc->credit($user, 500);

        $this->assertEquals(500.0, (float) $user->fresh()->balance);
        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 500,
        ]);
    }

    public function test_debit_decreases_balance_and_logs_transaction(): void
    {
        $svc = app(BalanceService::class);
        $user = User::factory()->create(['balance' => 500]);

        $svc->debit($user, 200);

        $this->assertEquals(300.0, (float) $user->fresh()->balance);
        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $user->id,
            'type' => 'debit',
            'amount' => 200,
        ]);
    }

    public function test_balance_cannot_go_negative(): void
    {
        $svc = app(BalanceService::class);
        $user = User::factory()->create(['balance' => 300]);

        try {
            $svc->debit($user, 1000);
            $this->fail('Yetersiz bakiyede istisna bekleniyordu.');
        } catch (\RuntimeException $e) {
            // beklenen
        }

        // Bakiye değişmemeli, başarısız düşüm için işlem kaydı oluşmamalı
        $this->assertEquals(300.0, (float) $user->fresh()->balance);
        $this->assertSame(0, BalanceTransaction::where('user_id', $user->id)->where('type', 'debit')->count());
    }

    public function test_credit_rejects_non_positive_amount(): void
    {
        $svc = app(BalanceService::class);
        $user = User::factory()->create(['balance' => 0]);

        $this->expectException(\InvalidArgumentException::class);
        $svc->credit($user, 0);
    }
}
