<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Notifications\WithdrawalStatusNotification;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class WithdrawalController extends Controller
{
    public function __construct(private readonly BalanceService $balance) {}

    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = WithdrawalRequest::with('user:id,name,email');
        if (in_array($status, ['pending', 'paid', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $items = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Withdrawals/Index', [
            'filters' => ['status' => $status],
            'stats' => [
                'pending' => WithdrawalRequest::where('status', 'pending')->count(),
                'paid'    => WithdrawalRequest::where('status', 'paid')->count(),
                'rejected'=> WithdrawalRequest::where('status', 'rejected')->count(),
            ],
            'requests' => [
                'data' => collect($items->items())->map(fn ($w) => [
                    'id'               => $w->id,
                    'user_name'        => $w->user?->name,
                    'user_email'       => $w->user?->email,
                    'amount'           => number_format((float) $w->amount, 2, ',', '.') . ' ₺',
                    'iban'             => $w->iban,
                    'status'           => $w->status,
                    'status_label'     => $w->statusLabel(),
                    'status_color'     => match ($w->status) { 'pending' => '#fbbf24', 'paid' => '#10b981', default => '#ef4444' },
                    'status_icon'      => match ($w->status) { 'pending' => 'bi-hourglass-split', 'paid' => 'bi-check-circle', default => 'bi-x-circle' },
                    'rejection_reason' => $w->rejection_reason,
                    'created_at'       => $w->created_at->format('d.m.Y H:i'),
                    'approve_url'      => route('admin.withdrawals.approve', $w->id),
                    'reject_url'       => route('admin.withdrawals.reject', $w->id),
                ])->values(),
                'links'     => $items->linkCollection()->toArray(),
                'has_pages' => $items->hasPages(),
                'total'     => $items->total(),
                'from'      => $items->firstItem(),
                'to'        => $items->lastItem(),
            ],
        ]);
    }

    /** Onayla + ödendi işaretle. Bakiye zaten talep anında rezerve (debit) edildiğinden burada tekrar düşülmez. */
    public function approve(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $reference = (string) $request->input('payment_reference', '');

        $ok = DB::transaction(function () use ($withdrawal, $request, $reference) {
            $locked = WithdrawalRequest::whereKey($withdrawal->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                return false;
            }
            $locked->update([
                'status'            => 'paid',
                'approver_id'       => $request->user()->id,
                'approved_at'       => now(),
                'payment_reference' => $reference ?: null,
            ]);
            return true;
        });

        if (! $ok) {
            return back()->with('error', 'Bu talep zaten işlenmiş.');
        }

        activity('finance')
            ->causedBy($request->user())
            ->performedOn($withdrawal)
            ->withProperties(['amount' => (float) $withdrawal->amount, 'iban' => $withdrawal->iban])
            ->log('Para çekme talebi ödendi');

        try {
            $withdrawal->user?->notify(new WithdrawalStatusNotification('paid', (float) $withdrawal->amount));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Talep onaylandı ve ödendi olarak işaretlendi.');
    }

    /** Reddet → rezerve edilen bakiye kullanıcıya geri yüklenir. */
    public function reject(Request $request, WithdrawalRequest $withdrawal): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], ['reason.required' => 'Ret sebebi zorunludur.']);

        $ok = DB::transaction(function () use ($withdrawal, $request, $data) {
            $locked = WithdrawalRequest::whereKey($withdrawal->id)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'pending') {
                return false;
            }

            // Rezerve edilen tutarı geri yükle
            $this->balance->credit(
                user: $locked->user,
                amount: (float) $locked->amount,
                paymentMethod: 'withdraw_refund',
                description: 'Para çekme talebi reddi — bakiye iadesi',
                meta: ['withdrawal_id' => $locked->id],
            );

            $locked->update([
                'status'           => 'rejected',
                'approver_id'      => $request->user()->id,
                'approved_at'      => now(),
                'rejection_reason' => $data['reason'],
            ]);
            return true;
        });

        if (! $ok) {
            return back()->with('error', 'Bu talep zaten işlenmiş.');
        }

        activity('finance')
            ->causedBy($request->user())
            ->performedOn($withdrawal)
            ->withProperties(['amount' => (float) $withdrawal->amount, 'reason' => $data['reason']])
            ->log('Para çekme talebi reddedildi');

        try {
            $withdrawal->user?->notify(new WithdrawalStatusNotification('rejected', (float) $withdrawal->amount, $data['reason']));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Talep reddedildi ve bakiye kullanıcıya geri yüklendi.');
    }
}
