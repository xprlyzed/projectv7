<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRepliedNotification extends Notification
{
    public function __construct(public SupportTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ($notifiable->email_notifications ?? true) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Destek talebinize yanıt geldi — ' . config('app.name'))
            ->greeting('Merhaba ' . $notifiable->name . ',')
            ->line('"' . \Str::limit($this->ticket->subject, 40) . '" talebinize yanıt geldi.')
            ->action('Talebi görüntüle', route('support.show', $this->ticket->id));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'ticket_reply',
            'message' => '"'.\Str::limit($this->ticket->subject, 40).'" talebine yanıt geldi.',
            'ticket_id' => $this->ticket->id,
            'subject' => $this->ticket->subject,
        ];
    }
}
