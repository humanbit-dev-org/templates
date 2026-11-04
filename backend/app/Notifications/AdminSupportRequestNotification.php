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
		protected array $screenshots = []
	) {}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @return array<int, string>
	 */
	public function via(object $notifiable): array
	{
		return ["mail"];
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

<<<<<<< HEAD
		$mail = (new MailMessage())
			->subject("[RAYS Admin] " . $categoryLabel . " — " . $this->user->email)
			->view("mail.admin-support", [
				"user" => $this->user,
				"roleName" => $this->user->backpackRole?->name ?? "—",
				"bancaLabel" => $this->resolveBancaLabel(),
				"categoryKey" => $this->category,
				"categoryLabel" => $categoryLabel,
				"messageBody" => $this->message,
				"pageUrl" => $this->pageUrl,
				"screenshotNames" => array_column($this->screenshots, "name"),
				// The app runs in UTC: show the time in Italian local time, as the
				// other admin screens do (logs, dashboard).
				"sentAt" => now("Europe/Rome"),
			]);
=======
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
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		if (filled($this->user->email)) {
			$mail->replyTo($this->user->email);
		}

		foreach ($this->screenshots as $screenshot) {
			$mail->attachData($screenshot["data"], $screenshot["name"], ["mime" => $screenshot["mime"]]);
		}

		return $mail;
	}

<<<<<<< HEAD
	/**
	 * Bank the admin user belongs to, as "Name (ABI)", or null when the user
	 * has no bank.
	 */
	protected function resolveBancaLabel(): ?string
	{
		$banca = $this->user->banca;

		if (!$banca) {
			return $this->user->banca_abi ?: null;
		}

		return filled($banca->nome) ? $banca->nome . " (" . $banca->banca_abi . ")" : (string) $banca->banca_abi;
	}

	/**
	 * Translated label of the category, falling back to the raw key when the
	 * translation is missing.
	 */
	public function categoryLabel(): string
	{
		$key = "backpack::support.categories." . $this->category;
		$label = __($key);
=======
    /**
     * Translated label of the category, falling back to the raw key when the
     * translation is missing.
     */
    public function categoryLabel(): string
    {
        $key = 'backpack::support.categories.'.$this->category;
        $label = __($key);
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

		return $label === $key ? $this->category : (string) $label;
	}
}
