<?php

namespace App\Core\Consents\Contracts;

use App\Core\Consents\Enums\ConsentPurpose;

/**
 * Una Notification che può raggiungere un Patient deve implementare questa
 * interfaccia. È l'unico modo per farla passare da
 * App\Core\Consents\Listeners\EnforceConsentGate — che blocca di default
 * (fail-closed) qualunque notifica a un Patient che non la implementi.
 */
interface RequiresConsent
{
    public function consentPurpose(): ConsentPurpose;

    /**
     * Nome dell'attributo di contatto su Patient da verificare non vuoto
     * (es. 'mobile_phone', 'email'), o null per non richiedere alcun
     * canale specifico.
     */
    public function consentChannel(): ?string;
}
