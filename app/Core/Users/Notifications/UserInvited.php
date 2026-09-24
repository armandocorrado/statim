<?php

namespace App\Core\Users\Notifications;

use App\Core\Tenancy\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvited extends Notification
{
    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $plainTextToken,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Invito a unirti a {$this->tenant->name} su MedCare")
            ->greeting('Sei stato invitato su MedCare')
            ->line("Sei stato invitato a unirti allo studio \"{$this->tenant->name}\" su MedCare.")
            ->action('Accetta invito', route('invitations.accept', ['tenant' => $this->tenant->id, 'token' => $this->plainTextToken]))
            ->line('Il link scade tra 7 giorni.');
    }
}
