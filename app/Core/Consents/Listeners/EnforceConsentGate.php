<?php

namespace App\Core\Consents\Listeners;

use App\Core\Consents\Contracts\RequiresConsent;
use App\Core\Consents\Support\ConsentGate;
use App\Core\Patients\Models\Patient;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Intercetta ogni invio di Notification verso un Patient. Fail-closed per
 * design: se la notifica non dichiara la propria finalità (non implementa
 * RequiresConsent), viene bloccata di default — dimenticarsene blocca
 * l'invio, non lo lascia passare. Vedi CLAUDE.md per il confine noto
 * (codice che scavalca il sistema Notification di Laravel non passa qui).
 */
class EnforceConsentGate
{
    public function handle(NotificationSending $event): bool
    {
        if (! $event->notifiable instanceof Patient) {
            return true;
        }

        if (! $event->notification instanceof RequiresConsent) {
            return false;
        }

        return ConsentGate::allows(
            $event->notifiable,
            $event->notification->consentPurpose(),
            $event->notification->consentChannel(),
        );
    }
}
