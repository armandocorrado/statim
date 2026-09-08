<?php

namespace App\Core\Consents\Policies;

use App\Core\Consents\Models\Consent;
use App\Core\Patients\Models\Patient;
use App\Models\User;

class ConsentPolicy
{
    /**
     * Defense in depth: ri-verifica sempre il tenant match esplicitamente,
     * indipendentemente dalla global scope — stesso principio di PatientPolicy.
     * Non esiste un ability "view": consultare lo stato dei consensi è già
     * coperto dall'autorizzazione a vedere la scheda paziente stessa.
     */
    public function record(User $user, Patient $patient): bool
    {
        return $patient->tenant_id === $user->tenant_id && $user->can('consents.manage');
    }

    public function revoke(User $user, Consent $consent): bool
    {
        return $consent->tenant_id === $user->tenant_id && $user->can('consents.manage');
    }
}
