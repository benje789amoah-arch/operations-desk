<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password link for both new accounts (sent when an administrator adds
 * someone) and forgotten passwords. Laravel's default text assumes the
 * person asked for a reset, which reads wrongly for an invitation.
 */
class SetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Set your '.config('app.name').' password')
            ->greeting('Hello,')
            ->line('Use the button below to choose a password for your '.config('app.name').' account. You will need it to sign in.')
            ->action('Choose a password', $url)
            ->line("This link expires in {$minutes} minutes. If it has expired, use \"Forgot password?\" on the sign-in page or ask an administrator to send a new one.")
            ->line('If you were not expecting this email, you can ignore it; your account will not change.');
    }
}
