<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request)
    {
        $status = $request->get('status');

        $query = Order::with(['auction', 'buyer', 'seller'])->latest();

        if ($status === 'disputed') {
            $query->where('status', 'disputed');
        } elseif ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20)->withQueryString();

        $counts = [
            'all'      => Order::count(),
            'disputed' => Order::where('status', 'disputed')->count(),
            'active'   => Order::whereIn('status', ['awaiting_payment', 'paid', 'shipped'])->count(),
            'completed'=> Order::where('status', 'completed')->count(),
        ];

        return Inertia::render('Admin/Orders/Index', [
            'status' => $status,
            'counts' => $counts,
            'orders' => [
                'data' => collect($orders->items())->map(fn ($o) => [
                    'id'           => $o->id,
                    'order_number' => $o->order_number,
                    'title'        => Str::limit($o->auction?->title ?? 'İlan', 28),
                    'buyer_name'   => $o->buyer?->name,
                    'seller_name'  => $o->seller?->name,
                    'amount'       => number_format($o->amount, 0, ',', '.') . ' ₺',
                    'status_label' => $o->statusLabel(),
                    'status_color' => $o->statusColor(),
                    'status_icon'  => $o->statusIcon(),
                    'show_url'     => route('admin.orders.show', $o),
                ])->values(),
                'links'     => $orders->linkCollection()->toArray(),
                'has_pages' => $orders->hasPages(),
            ],
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['auction.cover', 'buyer', 'seller', 'events.actor', 'winningBid']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id'                   => $order->id,
                'order_number'         => $order->order_number,
                'status'               => $order->status,
                'status_label'         => $order->statusLabel(),
                'status_color'         => $order->statusColor(),
                'status_icon'          => $order->statusIcon(),
                'escrow_status'        => $order->escrow_status,
                'amount'               => number_format($order->amount, 0, ',', '.') . ' ₺',
                'commission_amount'    => number_format($order->commission_amount, 0, ',', '.') . ' ₺',
                'progress_steps'       => $order->progressSteps(),
                'is_cancelled'         => in_array($order->status, ['cancelled', 'disputed'], true),
                'auction_title'        => $order->auction?->title,
                'cover_url'            => $order->auction?->cover?->url() ?? asset('assets/media/placeholder.svg'),
                'buyer_name'           => $order->buyer?->name,
                'seller_name'          => $order->seller?->name,
                'carrier'              => $order->carrier,
                'tracking_number'      => $order->tracking_number,
                'has_shipping_address' => $order->hasShippingAddress(),
                'recipient_name'       => $order->recipient_name,
                'shipping_address'     => $order->shipping_address,
                'address_city'         => $order->address_city,
                'dispute_reason'       => $order->dispute_reason,
                'index_url'            => route('admin.orders.index'),
                'resolve_url'          => route('admin.orders.resolve', $order),
                'cancel_url'           => route('admin.orders.cancel', $order),
                'reverse_url'          => route('admin.orders.reverse', $order),
                'can_cancel'           => in_array($order->status, ['awaiting_payment', 'paid', 'shipped', 'disputed'], true),
                'can_reverse'          => $order->status === 'completed'
                                            && $order->escrow_status === 'released'
                                            && $order->dispute_window_ends_at
                                            && $order->dispute_window_ends_at->isFuture(),
                'events'               => $order->events->map(fn ($ev) => [
                    'id'          => $ev->id,
                    'title'       => $ev->title,
                    'description' => $ev->description,
                    'icon'        => $ev->icon ?? 'bi-dot',
                    'color'       => Order::STATUSES[$ev->status]['color'] ?? '#3b82f6',
                    'time'        => $ev->created_at->format('d.m.Y H:i'),
                    'actor'       => $ev->actor?->name,
                ])->values(),
            ],
        ]);
    }

    public function resolve(Request $request, Order $order)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:buyer,seller'],
        ]);

        abort_unless($order->status === 'disputed', 422);

        $this->orders->resolveDispute($order, $data['decision'], $request->user()->id);

        return back()->with('success', 'Anlaşmazlık '.($data['decision'] === 'buyer' ? 'alıcı' : 'satıcı').' lehine sonuçlandırıldı.');
    }

    /** Görev 6.4: Admin sıkışmış siparişi iptal eder ve iade yapar (sebep zorunlu). */
    public function cancel(Request $request, Order $order)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        abort_unless(in_array($order->status, ['awaiting_payment', 'paid', 'shipped', 'disputed'], true), 422);

        $this->orders->cancelAndRefund($order, $data['reason'], $request->user()->id);

        return back()->with('success', 'Sipariş iptal edildi ve (ödeme alınmışsa) iade yapıldı.');
    }

    /** Görev 6.3: Serbest bırakılmış siparişte itiraz penceresi içinde geri alma. */
    public function reverse(Request $request, Order $order)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $ok = $this->orders->reverseRelease($order, $data['reason'], $request->user()->id);

        return back()->with($ok ? 'success' : 'error',
            $ok ? 'Serbest bırakma geri alındı, alıcıya iade yapıldı.' : 'Bu sipariş için geri alma yapılamıyor (pencere kapanmış olabilir).');
    }
}
