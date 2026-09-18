<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'reference',
        'status',
        'payment_method',
        'meta',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'reference_code',
    ];

    protected $casts = [
        'amount'         => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after'  => 'decimal:2',
        'approved_at'    => 'datetime',
        'meta'           => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->isCredit() ? '+' : '-';
        return $sign . number_format($this->amount, 2, ',', '.') . ' ₺';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'   => 'Onay Bekliyor',
            'completed' => 'Tamamlandı',
            'failed'    => 'Başarısız',
            'rejected'  => 'Reddedildi',
            'refunded'  => 'İade Edildi',
            default     => $this->status,
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'credit' => 'Yükleme',
            'debit'  => 'Harcama',
            'refund' => 'İade',
            default  => $this->type,
        };
    }
}
