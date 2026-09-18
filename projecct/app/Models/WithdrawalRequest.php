<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'user_id', 'amount', 'iban', 'status', 'approver_id',
        'approved_at', 'rejection_reason', 'payment_reference', 'reserve_transaction_id',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function reserveTransaction(): BelongsTo
    {
        return $this->belongsTo(BalanceTransaction::class, 'reserve_transaction_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'  => 'Onay Bekliyor',
            'approved' => 'Onaylandı',
            'rejected' => 'Reddedildi',
            'paid'     => 'Ödendi',
            default    => $this->status,
        };
    }
}
