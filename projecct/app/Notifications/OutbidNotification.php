<?php

namespace App\Notifications;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Teklifiniz geçildi" (Görev 4.1) — önceki en yüksek teklif sahibine.
 */
class OutbidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Auction $auction,
        public Bid $newTopBid,
    ) {}

    public function via(object $notifiable): array
    {
        return ($notifiable->email_notifications ?? true) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->newTopBid->amount, 0, ',', '.') . ' ₺';

        return (new MailMessage)
            ->subject('Teklifiniz geçildi — ' . config('app.name'))
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line('"' . $this->auction->title . '" ilanındaki teklifiniz geçildi.')
            ->line('Yeni en yüksek teklif: ' . $amount)
            ->action('İlana git ve tekrar teklif ver', route('auctions.show', $this->auction->slug));
    }

    public function toArray(object $notifiable): array
    {
        $amount = number_format($this->newTopBid->amount, 0, ',', '.') . ' ₺';

        return [
            'type'          => 'outbid',
            'auction_id'    => $this->auction->id,
            'auction_slug'  => $this->auction->slug,
            'auction_title' => $this->auction->title,
            'amount'        => (float) $this->newTopBid->amount,
            'title'         => 'Teklifiniz geçildi',
            'message'       => '"' . \Illuminate\Support\Str::limit($this->auction->title, 40) . '" için yeni en yüksek teklif ' . $amount,
            'icon'          => 'bi-arrow-up-circle-fill',
            'color'         => '#ef4444',
            'url'           => route('auctions.show', $this->auction->slug),
        ];
    }
}
