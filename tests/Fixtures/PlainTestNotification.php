<?php

namespace Tests\Fixtures;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Deliberately does NOT implement RequiresConsent — used to prove
 * EnforceConsentGate blocks by default when a notification to a Patient
 * forgets to declare its consent purpose.
 */
class PlainTestNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Test')->line('Test message.');
    }
}
