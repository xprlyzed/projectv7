<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $title,
        public string $message,
        public string $icon = 'bi-bell',
        public string $color = '#3b82f6',
    ) {}

    public function via(object $notifiable): array
    {
        return ($notifiable->email_notifications ?? true) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title . ' — ' . config('app.name'))
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line($this->message)
            ->action('Siparişi görüntüle', route('orders.show', $this->order->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'order',
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'title'        => $this->title,
            'message'      => $this->message,
            'icon'         => $this->icon,
            'color'        => $this->color,
            'url'          => route('orders.show', $this->order->id),
        ];
    }
}
