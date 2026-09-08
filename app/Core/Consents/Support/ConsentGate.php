<?php

namespace App\Core\Consents\Support;

use App\Core\Consents\Enums\ConsentPurpose;
use App\Core\Consents\Models\Consent;
use App\Core\Patients\Models\Patient;

/**
 * Punto di verifica centrale: qualunque modulo (es. il futuro CRM) deve
 * interrogare questo gate prima di usare un contatto del paziente per una
 * data finalità — vedi App\Core\Consents\Listeners\EnforceConsentGate per
 * come questo viene imposto a livello di codice, non solo di UI.
 */
class ConsentGate
{
    public static function allows(Patient $patient, ConsentPurpose $purpose, ?string $channel = null): bool
    {
        $consent = Consent::query()
            ->where('patient_id', $patient->id)
            ->where('purpose', $purpose)
            ->latest('granted_at')
            ->first();

        if (! $consent || ! $consent->isActive()) {
            return false;
        }

        if ($channel !== null && blank($patient->{$channel} ?? null)) {
            return false;
        }

        return true;
    }
}
