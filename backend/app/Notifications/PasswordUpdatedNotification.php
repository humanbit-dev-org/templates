<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail sent to a user after their password changed, in Italian or English.
 */
class PasswordUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(protected string $lang = 'it') {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $brand = (string) config('mail.brand_name');

        if ($this->lang === 'en') {
            return (new MailMessage)
                ->subject("{$brand} - Password updated")
                ->greeting('Hello,')
                ->line('Your password has been updated successfully.')
                ->line('If you did not make this change, please contact support immediately.')
                ->salutation("Regards,\n{$brand}");
        }

        return (new MailMessage)
            ->subject("{$brand} - Password aggiornata")
            ->greeting('Gentile utente,')
            ->line('La tua password è stata aggiornata con successo.')
            ->line('Se non hai effettuato questa modifica, contatta immediatamente l\'assistenza.')
            ->salutation("Cordiali saluti,\n{$brand}");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
