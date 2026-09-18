<?php
namespace App\Notifications;

use App\Models\Auction;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuctionStatusNotification extends Notification
{
    public function __construct(
        public Auction  $auction,
        public string   $status,
        public ?string  $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ($notifiable->email_notifications ?? true) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = $this->status === 'approved'
            ? "\"{$this->auction->title}\" ilanınız onaylandı ve yayına alındı."
            : "\"{$this->auction->title}\" ilanınız reddedildi." . ($this->reason ? " Gerekçe: {$this->reason}" : '');

        return (new MailMessage)
            ->subject('İlan durumu güncellendi — ' . config('app.name'))
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line($msg);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'auction_id'    => $this->auction->id,
            'auction_slug'  => $this->auction->slug,
            'auction_title' => $this->auction->title,
            'status'        => $this->status,
            'reason'        => $this->reason,
            'message'       => $this->status === 'approved'
                ? "\"{$this->auction->title}\" ilanınız onaylandı ve yayına alındı."
                : "\"{$this->auction->title}\" ilanınız reddedildi." . ($this->reason ? " Gerekçe: {$this->reason}" : ''),
        ];
    }
}
