<?php

namespace App\Notifications;

use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "İlan bitmek üzere" (Görev 4.2) — teklif verenlere ve izleyenlere.
 */
class EndingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Auction $auction,
        public int $minutesLeft,
    ) {}

    public function via(object $notifiable): array
    {
        return ($notifiable->email_notifications ?? true) ? ['database', 'mail'] : ['database'];
    }

    private function label(): string
    {
        return $this->minutesLeft >= 60
            ? 'yaklaşık 1 saat'
            : $this->minutesLeft . ' dakika';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('İlan bitmek üzere — ' . config('app.name'))
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line('"' . $this->auction->title . '" ilanının bitmesine ' . $this->label() . ' kaldı.')
            ->line('Güncel fiyat: ' . $this->auction->displayPrice())
            ->action('İlana git', route('auctions.show', $this->auction->slug));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'ending_soon',
            'auction_id'    => $this->auction->id,
            'auction_slug'  => $this->auction->slug,
            'auction_title' => $this->auction->title,
            'minutes_left'  => $this->minutesLeft,
            'title'         => 'İlan bitmek üzere',
            'message'       => '"' . \Illuminate\Support\Str::limit($this->auction->title, 40) . '" ilanının bitmesine ' . $this->label() . ' kaldı.',
            'icon'          => 'bi-hourglass-split',
            'color'         => '#f59e0b',
            'url'           => route('auctions.show', $this->auction->slug),
        ];
    }
}
