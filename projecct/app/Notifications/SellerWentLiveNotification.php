<?php

namespace App\Notifications;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * "Takip ettiğiniz satıcı canlı yayına geçti" (Görev 4.3) — takipçilere.
 *
 * Anlık bir olay olduğu için yalnızca database kanalı kullanılır (mail spam'i önlenir).
 */
class SellerWentLiveNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $seller,
        public Auction $auction,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'seller_live',
            'seller_id'       => $this->seller->id,
            'seller_name'     => $this->seller->name,
            'seller_username' => $this->seller->username,
            'auction_id'      => $this->auction->id,
            'auction_slug'    => $this->auction->slug,
            'auction_title'   => $this->auction->title,
            'title'           => 'Canlı yayın başladı',
            'message'         => $this->seller->name . ' canlı yayına geçti: ' . \Illuminate\Support\Str::limit($this->auction->title, 40),
            'icon'            => 'bi-broadcast',
            'color'           => '#e11d48',
            'url'             => route('auctions.show', $this->auction->slug),
        ];
    }
}
