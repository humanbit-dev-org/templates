<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailCustom extends Notification
{
	use Queueable;

	/**
	 * Create a new notification instance.
	 */
	public function __construct()
	{
		//
	}

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
	 */
	public function toMail(object $notifiable): MailMessage
	{
		$verificationUrl = $this->verificationUrl($notifiable);

		return (new MailMessage())
			->subject("Verify Email Address")
			->line("Please click the button below to verify your email address.")
			->action("Verify Email Address", $verificationUrl)
			->line("If you did not create an account, no further action is required.");
	}

	protected function verificationUrl($notifiable)
	{
		return URL::temporarySignedRoute("user.verification.verify", Carbon::now()->addMinutes(60), [
			"id" => $notifiable->getKey(),
			"hash" => sha1($notifiable->getEmailForVerification()),
		]);
	}

	/**
	 * Get the array representation of the notification.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(object $notifiable): array
	{
		return [
				//
			];
	}
}
