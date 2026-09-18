<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TopUpStatusNotification extends Notification
{
    /**
     * @param string $status  approved | rejected
     */
    public function __construct(
        public string $status,
        public float $amount,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ($notifiable->email_notifications ?? true) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amountFmt = number_format($this->amount, 2, ',', '.') . ' ₺';

        if ($this->status === 'approved') {
            return (new MailMessage)
                ->subject('Bakiye Yükleme Onaylandı — ' . config('app.name'))
                ->greeting('Merhaba ' . $notifiable->name . ',')
                ->line($amountFmt . ' tutarındaki banka havalesi bakiye yükleme talebiniz onaylandı ve bakiyenize eklendi.')
                ->action('Bakiyemi görüntüle', route('general.balance.index'));
        }

        return (new MailMessage)
            ->subject('Bakiye Yükleme Reddedildi — ' . config('app.name'))
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line($amountFmt . ' tutarındaki banka havalesi bakiye yükleme talebiniz reddedildi.')
            ->line($this->reason ? ('Sebep: ' . $this->reason) : '')
            ->action('Bakiyemi görüntüle', route('general.balance.index'));
    }

    public function toArray(object $notifiable): array
    {
        $approved = $this->status === 'approved';

        return [
            'type'    => 'topup',
            'title'   => $approved ? 'Bakiye yüklendi' : 'Bakiye yükleme reddedildi',
            'message' => $approved
                ? number_format($this->amount, 0, ',', '.') . ' ₺ bakiyenize eklendi.'
                : number_format($this->amount, 0, ',', '.') . ' ₺ talebiniz reddedildi.' . ($this->reason ? ' Sebep: ' . $this->reason : ''),
            'icon'    => $approved ? 'bi-wallet2' : 'bi-x-circle',
            'color'   => $approved ? '#10b981' : '#ef4444',
            'url'     => route('general.balance.index'),
        ];
    }
}
