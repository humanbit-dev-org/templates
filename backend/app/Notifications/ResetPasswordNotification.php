<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset email for platform and console users.
 *
 * Same content as Laravel's ResetPassword (texts translated through the JSON
 * lang files, URL built by the ResetPassword::createUrlUsing() callbacks), but
 * sent under the mail brand name (config mail.brand_name) instead of the
 * environment-wide MAIL_FROM_NAME, so that it carries the same sender name as
 * the other customer emails.
 */
class ResetPasswordNotification extends ResetPassword
{
    /**
     * Build the reset email and set the branded sender.
     *
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return parent::buildMailMessage($url)
            ->from(config('mail.from.address'), config('mail.brand_name'));
    }
}
