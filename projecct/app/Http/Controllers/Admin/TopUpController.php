<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BalanceTransaction;
use App\Notifications\TopUpStatusNotification;
use App\Services\BalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TopUpController extends Controller
{
    public function __construct(private readonly BalanceService $balance) {}

    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $query = BalanceTransaction::with('user:id,name,email')
            ->where('payment_method', 'bank_transfer')
            ->where('type', 'credit');

        if (in_array($status, ['pending', 'completed', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $txs = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/TopUps/Index', [
            'filters' => ['status' => $status],
            'stats' => [
                'pending'   => BalanceTransaction::where('payment_method', 'bank_transfer')->where('status', 'pending')->count(),
                'completed' => BalanceTransaction::where('payment_method', 'bank_transfer')->where('status', 'completed')->count(),
                'rejected'  => BalanceTransaction::where('payment_method', 'bank_transfer')->where('status', 'rejected')->count(),
            ],
            'requests' => [
                'data' => collect($txs->items())->map(fn ($tx) => [
                    'id'               => $tx->id,
                    'user_name'        => $tx->user?->name,
                    'user_email'       => $tx->user?->email,
                    'amount'           => number_format((float) $tx->amount, 2, ',', '.') . ' ₺',
                    'reference_code'   => $tx->reference_code,
                    'status'           => $tx->status,
                    'status_label'     => $tx->status_label,
                    'status_color'     => match ($tx->status) { 'pending' => '#fbbf24', 'completed' => '#10b981', default => '#ef4444' },
                    'status_icon'      => match ($tx->status) { 'pending' => 'bi-hourglass-split', 'completed' => 'bi-check-circle', default => 'bi-x-circle' },
                    'rejection_reason' => $tx->rejection_reason,
                    'created_at'       => $tx->created_at->format('d.m.Y H:i'),
                    'approve_url'      => route('admin.topups.approve', $tx->id),
                    'reject_url'       => route('admin.topups.reject', $tx->id),
                ])->values(),
                'links'     => $txs->linkCollection()->toArray(),
                'has_pages' => $txs->hasPages(),
                'total'     => $txs->total(),
                'from'      => $txs->firstItem(),
                'to'        => $txs->lastItem(),
            ],
        ]);
    }

    public function approve(Request $request, BalanceTransaction $transaction): RedirectResponse
    {
        $ok = $this->balance->approvePendingTopUp($transaction, $request->user());

        if (! $ok) {
            return back()->with('error', 'Bu talep zaten işlenmiş (onaylanmış veya reddedilmiş).');
        }

        activity('finance')
            ->causedBy($request->user())
            ->performedOn($transaction)
            ->withProperties(['amount' => (float) $transaction->amount, 'reference_code' => $transaction->reference_code])
            ->log('EFT bakiye yükleme onaylandı');

        try {
            $transaction->user?->notify(new TopUpStatusNotification('approved', (float) $transaction->amount));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Talep onaylandı ve bakiye eklendi.');
    }

    public function reject(Request $request, BalanceTransaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'reason.required' => 'Ret sebebi zorunludur.',
        ]);

        $ok = $this->balance->rejectPendingTopUp($transaction, $request->user(), $data['reason']);

        if (! $ok) {
            return back()->with('error', 'Bu talep zaten işlenmiş.');
        }

        activity('finance')
            ->causedBy($request->user())
            ->performedOn($transaction)
            ->withProperties(['amount' => (float) $transaction->amount, 'reason' => $data['reason']])
            ->log('EFT bakiye yükleme reddedildi');

        try {
            $transaction->user?->notify(new TopUpStatusNotification('rejected', (float) $transaction->amount, $data['reason']));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Talep reddedildi.');
    }
}
