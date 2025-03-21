<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\App;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class GroupEmail extends Mailable
{
	use Queueable, SerializesModels;

	public $group;
	public $username;
	public $toEmail;
	public $lang;
	public $view;

	/**
	 * Create a new message instance.
	 */
	public function __construct($group, $username, $toEmail, $lang, $view)
	{
		$this->group = $group;
		$this->username = $username;
		$this->toEmail = $toEmail;
		$this->lang = $lang;
		$this->view = $view;
		App::setLocale($lang);
	}

	/**
	 * Get the message envelope.
	 */
	public function build()
	{
		return $this->from("info@alltogetherpay.com")
			->subject("New invitation")
			->view("emails." . $this->view)
			->with([
				"group" => $this->group,
				"username" => $this->username,
				"toEmail" => $this->toEmail,
				"lang" => $this->lang,
			]);
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
