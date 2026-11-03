<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesBankMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordUpdatedNotification extends Notification
{
    use Queueable, UsesBankMailBranding;

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
        $bancaNome = $notifiable->banca?->nome ?? '';
        $isEnglish = $this->lang === 'en';

        if ($isEnglish) {
            return $this->withBankMailBranding(
                (new MailMessage)
                    ->subject("{$bancaNome} - Password updated")
                    ->greeting('Hello,')
                    ->line('Your password has been updated successfully.')
                    ->line('If you did not make this change, please contact support immediately.')
                    ->salutation("Regards,\n{$bancaNome}"),
                $notifiable->banca
            );
        }

        return $this->withBankMailBranding(
            (new MailMessage)
                ->subject("{$bancaNome} - Password aggiornata")
                ->greeting('Gentile utente,')
                ->line('La tua password è stata aggiornata con successo.')
                ->line('Se non hai effettuato questa modifica, contatta immediatamente l\'assistenza.')
                ->salutation("Cordiali saluti,\n{$bancaNome}"),
            $notifiable->banca
        );
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
