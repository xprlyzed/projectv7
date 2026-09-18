<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalStatusNotification extends Notification
{
    /**
     * @param string $status  paid | rejected
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

        if ($this->status === 'paid') {
            return (new MailMessage)
                ->subject('Para Çekme Talebiniz Ödendi — ' . config('app.name'))
                ->greeting('Merhaba ' . $notifiable->name . ',')
                ->line($amountFmt . ' tutarındaki para çekme talebiniz onaylandı ve IBAN adresinize gönderildi.')
                ->action('Bakiyemi görüntüle', route('general.balance.index'));
        }

        return (new MailMessage)
            ->subject('Para Çekme Talebiniz Reddedildi — ' . config('app.name'))
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line($amountFmt . ' tutarındaki para çekme talebiniz reddedildi ve tutar bakiyenize geri yüklendi.')
            ->line($this->reason ? ('Sebep: ' . $this->reason) : '')
            ->action('Bakiyemi görüntüle', route('general.balance.index'));
    }

    public function toArray(object $notifiable): array
    {
        $paid = $this->status === 'paid';

        return [
            'type'    => 'withdrawal',
            'title'   => $paid ? 'Para çekme ödendi' : 'Para çekme reddedildi',
            'message' => $paid
                ? number_format($this->amount, 0, ',', '.') . ' ₺ IBAN adresinize gönderildi.'
                : number_format($this->amount, 0, ',', '.') . ' ₺ talebiniz reddedildi, tutar bakiyenize geri yüklendi.' . ($this->reason ? ' Sebep: ' . $this->reason : ''),
            'icon'    => $paid ? 'bi-cash-stack' : 'bi-x-circle',
            'color'   => $paid ? '#10b981' : '#ef4444',
            'url'     => route('general.balance.index'),
        ];
    }
}
