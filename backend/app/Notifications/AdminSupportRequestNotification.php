<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminSupportRequestNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  string  $category  Category key from config('backpack.support.categories').
     * @param  array<int, array{name: string, mime: string, data: string}>  $screenshots  Images attached to the email.
     */
    public function __construct(
        protected User $user,
        protected string $category,
        protected string $message,
        protected string $pageUrl,
        protected array $screenshots = [],
    ) {}

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
     *
     * The admin user is identified by email only. The subject carries both
     * the category and that email, so that requests of the same category from
     * different users do not end up grouped in a single thread by the mail
     * client. Reply-To is the sender, so the support team can answer the
     * admin user straight from the inbox.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $categoryLabel = $this->categoryLabel();

        $mail = (new MailMessage)
            ->subject('['.config('app.name').' Admin] '.$categoryLabel.' — '.$this->user->email)
            ->view('mail.admin-support', [
                'user' => $this->user,
                'roleName' => $this->user->backpackRole?->name ?? '—',
                'categoryKey' => $this->category,
                'categoryLabel' => $categoryLabel,
                'messageBody' => $this->message,
                'pageUrl' => $this->pageUrl,
                'screenshotNames' => array_column($this->screenshots, 'name'),
                // The app runs in UTC: show the time in Italian local time, as the
                // other admin screens do (logs, dashboard).
                'sentAt' => now('Europe/Rome'),
            ]);

        if (filled($this->user->email)) {
            $mail->replyTo($this->user->email);
        }

        foreach ($this->screenshots as $screenshot) {
            $mail->attachData($screenshot['data'], $screenshot['name'], ['mime' => $screenshot['mime']]);
        }

        return $mail;
    }

    /**
     * Translated label of the category, falling back to the raw key when the
     * translation is missing.
     */
    public function categoryLabel(): string
    {
        $key = 'backpack::support.categories.'.$this->category;
        $label = __($key);

        return $label === $key ? $this->category : (string) $label;
    }
}
