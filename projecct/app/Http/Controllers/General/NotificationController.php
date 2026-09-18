<?php


namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        $mapped = collect($notifications->items())->map(function ($notif) {
            $data   = $notif->data;
            $type   = $data['type'] ?? 'follow';
            $unread = is_null($notif->read_at);

            $meta = match ($type) {
                'follow'           => ['bi-person-plus-fill',  '#155eef', 'rgba(127,119,221,.12)'],
                'new_bid'          => ['bi-currency-lira',     '#10b981', 'rgba(16,185,129,.12)'],
                'outbid'           => ['bi-arrow-up-circle-fill', '#ef4444', 'rgba(239,68,68,.12)'],
                'ending_soon'      => ['bi-hourglass-split',   '#f59e0b', 'rgba(245,158,11,.12)'],
                'seller_live'      => ['bi-broadcast',         '#e11d48', 'rgba(225,29,72,.12)'],
                'order'            => ['bi-box-seam',          '#3b82f6', 'rgba(59,130,246,.12)'],
                'ticket_reply'     => ['bi-life-preserver',    '#0ea5e9', 'rgba(14,165,233,.12)'],
                'auction_approved' => ['bi-check-circle-fill', '#22c55e', 'rgba(34,197,94,.12)'],
                'auction_rejected' => ['bi-x-circle-fill',     '#ef4444', 'rgba(239,68,68,.12)'],
                'auction_ended'    => ['bi-flag-fill',         '#6b7280', 'rgba(107,114,128,.12)'],
                'buy_now'          => ['bi-lightning-fill',    '#f59e0b', 'rgba(245,158,11,.12)'],
                default            => ['bi-bell-fill',         '#155eef', 'rgba(127,119,221,.12)'],
            };
            // Bildirimin kendi ikon/rengi varsa onu kullan (yeni tipler zaten taşıyor)
            [$icon, $color, $iconBg] = $meta;
            $icon  = $data['icon'] ?? $icon;
            $color = $data['color'] ?? $color;

            $avatarName = $data['follower_name'] ?? $data['bidder_name'] ?? $data['buyer_name'] ?? null;
            $avatarImg  = $data['follower_avatar'] ?? $data['bidder_avatar'] ?? $data['buyer_avatar'] ?? null;
            $avatarUser = $data['follower_username'] ?? $data['bidder_username'] ?? $data['buyer_username'] ?? null;

            // Link: bildirim verisinde hazır bir 'url' varsa onu tercih et (order/ticket_reply/yeni tipler),
            // yoksa tipe göre türet.
            $link = $data['url'] ?? match ($type) {
                'follow' => $avatarUser ? route('profile.public', $avatarUser) : '#',
                'outbid', 'ending_soon', 'seller_live'
                    => isset($data['auction_slug']) ? route('auctions.show', $data['auction_slug']) : '#',
                'new_bid', 'auction_approved', 'auction_rejected', 'auction_ended', 'buy_now'
                    => isset($data['auction_slug']) ? route('seller.auctions.show', $data['auction_slug']) : '#',
                default => '#',
            };

            return [
                'id'          => $notif->id,
                'unread'      => $unread,
                'icon'        => $icon,
                'color'       => $color,
                'icon_bg'     => $iconBg,
                'message'     => $data['message'] ?? '',
                'reason'      => $data['reason'] ?? null,
                'link'        => $link,
                'avatar_img'  => $avatarImg,
                'avatar_char' => $avatarName ? mb_strtoupper(mb_substr($avatarName, 0, 1)) : null,
                'time'        => $notif->created_at->diffForHumans(),
            ];
        })->values();

        auth()->user()->unreadNotifications->markAsRead();

        return Inertia::render('General/Notifications', [
            'notifications' => [
                'data'      => $mapped,
                'links'     => $notifications->linkCollection()->toArray(),
                'has_pages' => $notifications->hasPages(),
                'total'     => $notifications->total(),
            ],
        ]);
    }

    public function markAsRead(string $id)
    {
        $notification = auth()->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
