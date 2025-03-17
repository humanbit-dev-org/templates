<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class ContactEmail extends Mailable
{
	use Queueable, SerializesModels;

	public $ecommerce;
	public $user;
	public $lang;
	public $view;
	public $plainPassword;
	public $isNewUser;
	public $rejectReason;

	/**
	 * Create a new message instance.
	 */
	public function __construct(
		$ecommerce,
		$user,
		$lang,
		$view,
		$plainPassword = null,
		$isNewUser = false,
		$rejectReason = null
	) {
		$this->ecommerce = $ecommerce;
		$this->user = $user;
		$this->lang = $lang;
		$this->view = $view;
		$this->plainPassword = $plainPassword;
		$this->isNewUser = $isNewUser;
		$this->rejectReason = $rejectReason;
		App::setLocale($lang);
	}

	public function build()
	{
		return $this->from("corporate@alltogetherpay.com")
			->subject($this->lang == "en" ? "Outcome of your request" : "Esito della tua richiesta")
			->view("emails." . $this->view)
			->with([
				"ecommerce" => $this->ecommerce,
				"user" => $this->user,
				"lang" => $this->lang,
				"plainPassword" => $this->plainPassword,
				"isNewUser" => $this->isNewUser,
				"rejectReason" => $this->rejectReason,
			]);
	}

	/**
	 * Get the message envelope.
	 */
	public function envelope(): Envelope
	{
		return new Envelope(
			from: "corporate@alltogetherpay.com",
			subject: $this->lang == "en" ? "Outcome of your request" : "Esito della tua richiesta"
		);
	}

	/**
	 * Get the attachments for the message.
	 *
	 * @return array<int, \Illuminate\Mail\Mailables\Attachment>
	 */
	public function attachments(): array
	{
		return [];
	}
}
