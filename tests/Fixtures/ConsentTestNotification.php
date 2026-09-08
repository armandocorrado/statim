<?php

namespace Tests\Fixtures;

use App\Core\Consents\Contracts\RequiresConsent;
use App\Core\Consents\Enums\ConsentPurpose;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConsentTestNotification extends Notification implements RequiresConsent
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Test')->line('Test message.');
    }

    public function consentPurpose(): ConsentPurpose
    {
        return ConsentPurpose::Marketing;
    }

    public function consentChannel(): ?string
    {
        return 'email';
    }
}
