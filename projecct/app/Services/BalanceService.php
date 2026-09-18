<?php

namespace App\Services;

use App\Models\BalanceTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BalanceService
{
    /**
     *
     * @throws \Throwable
     */
    public function credit(
        User   $user,
        float  $amount,
        string $paymentMethod = 'credit_card',
        string $description   = 'Bakiye Yükleme',
        ?array $meta          = null,
        ?string $reference    = null,
    ): BalanceTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Yükleme tutarı 0\'dan büyük olmalıdır.');
        }

        return DB::transaction(function () use ($user, $amount, $paymentMethod, $description, $meta, $reference) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            $balanceBefore = (float) $user->balance;
            $balanceAfter  = $balanceBefore + $amount;

            $user->increment('balance', $amount);

            return BalanceTransaction::create([
                'user_id'        => $user->id,
                'type'           => 'credit',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => $description,
                'reference'      => $reference ?? 'BAL-' . strtoupper(Str::random(12)),
                'status'         => 'completed',
                'payment_method' => $paymentMethod,
                'meta'           => $meta,
            ]);
        });
    }

    /**
     * Kullanıcı bakiyesinden para düş.
     *
     * @throws \Throwable
     * @throws \RuntimeException  Yetersiz bakiye
     */
    public function debit(
        User   $user,
        float  $amount,
        string $description = 'Bakiye Kullanımı',
        ?array $meta        = null,
    ): BalanceTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Düşüm tutarı 0\'dan büyük olmalıdır.');
        }

        return DB::transaction(function () use ($user, $amount, $description, $meta) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            if ((float) $user->balance < $amount) {
                throw new \RuntimeException('Yetersiz bakiye.');
            }

            $balanceBefore = (float) $user->balance;
            $balanceAfter  = $balanceBefore - $amount;

            $user->decrement('balance', $amount);

            return BalanceTransaction::create([
                'user_id'        => $user->id,
                'type'           => 'debit',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => $description,
                'status'         => 'completed',
                'reference'      => 'DBT-' . strtoupper(Str::random(12)),
                'meta'           => $meta,
            ]);
        });
    }

    public function history(User $user, int $perPage = 15)
    {
        return BalanceTransaction::where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Banka havalesi/EFT ile bakiye yükleme TALEBİ oluşturur. Bakiye DEĞİŞMEZ; işlem
     * 'pending' olarak kaydedilir ve admin onayı beklenir. Kullanıcıya özel bir referans
     * kodu üretilir (havale açıklamasına yazması için).
     */
    public function requestBankTransfer(User $user, float $amount): BalanceTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Yükleme tutarı 0\'dan büyük olmalıdır.');
        }

        $referenceCode = 'EFT-' . $user->id . '-' . strtoupper(Str::random(6));

        return BalanceTransaction::create([
            'user_id'        => $user->id,
            'type'           => 'credit',
            'amount'         => $amount,
            'balance_before' => (float) $user->balance,
            'balance_after'  => (float) $user->balance, // henüz eklenmedi
            'description'    => 'Banka Havalesi ile Bakiye Yükleme (onay bekliyor)',
            'reference'      => 'BAL-' . strtoupper(Str::random(12)),
            'reference_code' => $referenceCode,
            'status'         => 'pending',
            'payment_method' => 'bank_transfer',
            'meta'           => null,
        ]);
    }

    /**
     * Bekleyen bir EFT talebini ONAYLAR — kilit altında ve idempotent. Aynı talep iki kez
     * (veya iki admin tarafından aynı anda) onaylanmaya çalışılsa bile bakiye YALNIZCA bir kez eklenir.
     *
     * @return bool  Bakiye eklendiyse true; talep zaten işlenmişse false.
     */
    public function approvePendingTopUp(BalanceTransaction $tx, User $admin): bool
    {
        return DB::transaction(function () use ($tx, $admin) {
            $locked = BalanceTransaction::whereKey($tx->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'pending') {
                return false; // zaten onaylanmış/reddedilmiş → hiçbir şey yapma
            }

            $user = User::lockForUpdate()->findOrFail($locked->user_id);
            $balanceBefore = (float) $user->balance;
            $balanceAfter  = $balanceBefore + (float) $locked->amount;

            $user->increment('balance', (float) $locked->amount);

            $locked->update([
                'status'         => 'completed',
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'approved_by'    => $admin->id,
                'approved_at'    => now(),
            ]);

            return true;
        });
    }

    /** Bekleyen bir EFT talebini REDDEDER — bakiye değişmez. */
    public function rejectPendingTopUp(BalanceTransaction $tx, User $admin, string $reason): bool
    {
        return DB::transaction(function () use ($tx, $admin, $reason) {
            $locked = BalanceTransaction::whereKey($tx->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'pending') {
                return false;
            }

            $locked->update([
                'status'           => 'rejected',
                'approved_by'      => $admin->id,
                'approved_at'      => now(),
                'rejection_reason' => $reason,
            ]);

            return true;
        });
    }
}
