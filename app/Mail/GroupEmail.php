<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class GroupEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $group;
    public $email;
    public $to_email;

    /**
     * Create a new message instance.
     */
    public function __construct($group, $email, $toEmail)
    {
        $this->group = $group;
        $this->email = $email;
        $this->to_email = $toEmail;
    }

    /**
     * Get the message envelope.
     */
    public function build()
    {
        return $this->from('info@alltogetherpay.com')
                    ->subject('Group Email')
                    ->view('emails.group') // Specify the email view
                    ->with([
                        'group' => $this->group,
                        'email' => $this->email,
                        'toEmail' => $this->to_email
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
